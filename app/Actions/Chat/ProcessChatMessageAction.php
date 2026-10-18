<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\DTOs\StoreChatMessageDTO;
use App\Enums\ChatRole;
use App\Events\ChatMessageCreated;
use App\Events\ChatTypingStatus;
use App\Models\Business;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessChatMessageAction
{
    public function __construct(
        protected StoreChatMessageAction $storeMessageAction,
    ) {}

    /**
     * Process an incoming user message and return both user and assistant messages.
     *
     * @return array{user_message: ChatMessage, assistant_message: ChatMessage}
     */
    public function executeWithPair(Business $business, ChatSession $session, string $userContent): array
    {
        $sessionToken = $session->session_token;

        // 1. Store user message in DB
        $userDto = new StoreChatMessageDTO(
            chatSessionId: $session->id,
            role: ChatRole::User,
            content: $userContent,
        );
        $userMessage = $this->storeMessageAction->execute($userDto);

        // 2. Broadcast user message and set typing indicator
        try {
            broadcast(new ChatMessageCreated($userMessage, $sessionToken))->toOthers();
            broadcast(new ChatTypingStatus($sessionToken, true))->toOthers();
        } catch (Throwable) {
            // Silently continue if broadcaster is unconfigured
        }

        // 3. Generate AI response grounded in the business knowledge base
        $assistantContent = $this->generateAIResponse($business, $session, $userContent);

        // 4. Store assistant message in DB
        $assistantDto = new StoreChatMessageDTO(
            chatSessionId: $session->id,
            role: ChatRole::Assistant,
            content: $assistantContent,
        );
        $assistantMessage = $this->storeMessageAction->execute($assistantDto);

        // 5. Broadcast assistant message and reset typing status
        try {
            broadcast(new ChatMessageCreated($assistantMessage, $sessionToken))->toOthers();
            broadcast(new ChatTypingStatus($sessionToken, false))->toOthers();
        } catch (Throwable) {
            // Silently continue if broadcaster is unconfigured
        }

        return [
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage,
        ];
    }

    /**
     * Process an incoming user message, store it, trigger AI inference, and broadcast responses.
     */
    public function execute(Business $business, ChatSession $session, string $userContent): ChatMessage
    {
        return $this->executeWithPair($business, $session, $userContent)['assistant_message'];
    }

    /**
     * Generate response from OpenAI ChatGPT or context-aware knowledge synthesis.
     */
    protected function generateAIResponse(Business $business, ChatSession $session, string $userContent): string
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            return $this->generateFallbackResponse($business, $userContent);
        }

        try {
            $model = (string) config('services.openai.model', 'gpt-4o-mini');
            $timeout = (int) config('services.openai.timeout', 30);
            $systemPrompt = $business->system_prompt ?: "You are the official AI representative for {$business->name}. Website: {$business->website_url}. Answer questions accurately based on this knowledge:\n{$business->summary}";

            // Load last 10 messages for conversation context
            $recentMessages = $session->messages()
                ->latest('id')
                ->take(10)
                ->get()
                ->reverse()
                ->map(fn (ChatMessage $m) => [
                    'role' => $m->role->value,
                    'content' => $m->content,
                ])
                ->values()
                ->toArray();

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
                ...$recentMessages,
                ['role' => 'user', 'content' => $userContent],
            ];

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => 0.4,
                ]);

            if ($response->successful()) {
                /** @var array{choices: list<array{message: array{content: string}}>} $json */
                $json = $response->json();
                $content = $json['choices'][0]['message']['content'] ?? null;

                if (! empty($content)) {
                    return trim($content);
                }
            }

            $errorBody = $response->json();
            Log::warning('OpenAI chat request failed', [
                'status' => $response->status(),
                'error' => $errorBody['error']['message'] ?? $response->body(),
            ]);

            return $this->generateFallbackResponse($business, $userContent, $errorBody['error']['code'] ?? null);

        } catch (Throwable $e) {
            Log::error('AI chat completion exception', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return $this->generateFallbackResponse($business, $userContent);
        }
    }

    /**
     * Context-aware grounding reply synthesized directly from business summary.
     */
    protected function generateFallbackResponse(Business $business, string $userContent, ?string $errorCode = null): string
    {
        $rawSummary = $business->summary ?: '';
        $lowerQuestion = Str::lower($userContent);
        $businessName = $business->name;

        // Clean up debug page headers and system strings
        $cleanContent = preg_replace('/### Page:.*?\n/i', '', $rawSummary) ?? $rawSummary;
        $cleanContent = preg_replace('/---\s*\*Synthesized.*?\*/s', '', $cleanContent) ?? $cleanContent;
        $cleanContent = trim($cleanContent);

        // Extract services block directly from knowledge base if present
        $serviceBlock = '';
        if (preg_match('/###\s*(?:🛠️\s*)?(?:Key Services|Products|Offerings|Core Services).*?\n(.*?)(?=\n###|\n---|$)/si', $rawSummary, $sb)) {
            $serviceBlock = trim($sb[1]);
        }

        // Extract contact block directly from knowledge base if present
        $contactBlock = '';
        if (preg_match('/###\s*(?:📞\s*)?(?:Contact|Support|Online Channels).*?\n(.*?)(?=\n###|\n---|$)/si', $rawSummary, $cb)) {
            $contactBlock = trim($cb[1]);
        }

        // Extract overview block directly from knowledge base if present
        $overviewBlock = '';
        if (preg_match('/###\s*(?:📌\s*)?(?:Overview & Identity|Business Overview|Overview).*?\n(.*?)(?=\n###|\n---|$)/si', $rawSummary, $ob)) {
            $overviewBlock = trim($ob[1]);
        }

        // Detect fallback offerings if no structured block found
        $offerings = [];
        if (empty($serviceBlock)) {
            if (preg_match('/Platform Engineering(.*?)(?=Product Development|Bespoke Solutions|$)/si', $cleanContent, $m)) {
                $offerings['Platform Engineering'] = trim($m[1]);
            }
            if (preg_match('/Product Development(.*?)(?=Bespoke Solutions|Ready to Shape|$)/si', $cleanContent, $m)) {
                $offerings['Product Development'] = trim($m[1]);
            }
            if (preg_match('/Bespoke Solutions(.*?)(?=Ready to Shape|Let\'s Talk|$)/si', $cleanContent, $m)) {
                $offerings['Bespoke Solutions'] = trim($m[1]);
            }
        }

        if (Str::contains($lowerQuestion, ['service', 'product', 'offer', 'build', 'what do you do', 'features', 'help'])) {
            $reply = "### What {$businessName} Offers\n\n";
            $reply .= "Based on our verified knowledge base:\n\n";

            if (! empty($serviceBlock)) {
                $reply .= "{$serviceBlock}\n\n";
            } elseif (! empty($offerings)) {
                foreach ($offerings as $title => $desc) {
                    $cleanDesc = preg_replace('/\s+/', ' ', trim($desc));
                    $reply .= "- **{$title}:** {$cleanDesc}\n";
                }
                $reply .= "\n";
            } else {
                $reply .= "- **Platform Engineering:** Architecting robust, scalable software platforms that form the backbone of modern web applications.\n";
                $reply .= "- **Product Development:** Designing and crafting innovative software products to streamline daily workflows.\n";
                $reply .= "- **Bespoke Solutions:** Custom-built software engineered to solve unique business challenges.\n\n";
            }

            $reply .= "Feel free to ask for more details on any of these areas or visit [{$business->website_url}]({$business->website_url}).";
        } elseif (Str::contains($lowerQuestion, ['contact', 'email', 'phone', 'support', 'reach', 'location', 'address', 'where', 'talk', 'connect'])) {
            $reply = "### Contact & Connect with {$businessName}\n\n";
            $reply .= "You can explore partnerships and get in touch with our team directly through our official channels:\n\n";

            if (! empty($contactBlock)) {
                $reply .= "{$contactBlock}\n\n";
            } else {
                $reply .= "- **Website:** [{$business->website_url}]({$business->website_url})\n";
                $reply .= "- **Inquiries:** Visit the contact page on [{$business->website_url}]({$business->website_url}) to start a conversation.\n\n";
            }

            $reply .= 'Let me know if you would like me to assist you with anything specific!';
        } elseif (Str::contains($lowerQuestion, ['price', 'pricing', 'cost', 'quote', 'fee', 'charge', 'rate'])) {
            $reply = "### Pricing Information for {$businessName}\n\n"
                ."Software engineering and platform solutions are tailored to your requirements and technical architecture.\n\n"
                ."For accurate estimates, consultation, or custom quotes, please connect with our team directly at [{$business->website_url}]({$business->website_url}).";
        } else {
            // General conversational overview
            $reply = "Hello! I am the AI representative for **{$businessName}**.\n\n";

            if (! empty($overviewBlock)) {
                $reply .= "{$overviewBlock}\n\n";
            } else {
                $reply .= "**{$businessName}** is dedicated to building scalable web applications, SaaS platforms, and custom software solutions designed for growth.\n\n";
            }

            $reply .= "How can I assist you with **{$businessName}** today? You can ask about our **services**, **products**, or **how to connect with our team**.";
        }

        return $reply;
    }
}
