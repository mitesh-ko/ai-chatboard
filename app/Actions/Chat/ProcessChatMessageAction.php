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
use Throwable;

class ProcessChatMessageAction
{
    public function __construct(
        protected StoreChatMessageAction $storeMessageAction,
    ) {}

    /**
     * Process an incoming user message, store it, trigger AI inference, and broadcast responses.
     */
    public function execute(Business $business, ChatSession $session, string $userContent): ChatMessage
    {
        $sessionToken = $session->session_token;

        // 1. Store user message in DB
        $userDto = new StoreChatMessageDTO(
            chatSessionId: $session->id,
            role: ChatRole::User,
            content: $userContent,
        );
        $userMessage = $this->storeMessageAction->execute($userDto);

        // 2. Broadcast user message
        try {
            broadcast(new ChatMessageCreated($userMessage, $sessionToken))->toOthers();
            broadcast(new ChatTypingStatus($sessionToken, true))->toOthers();
        } catch (Throwable) {
            // Silently continue if broadcaster is unconfigured
        }

        // 3. Generate AI response
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

        return $assistantMessage;
    }

    /**
     * Generate response from OpenAI ChatGPT or local fallback synthesis.
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
            $systemPrompt = $business->system_prompt ?: "You are the helpful AI representative for {$business->name}.";

            // Load last 8 messages for context
            $recentMessages = $session->messages()
                ->latest('id')
                ->take(8)
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

            Log::warning('OpenAI chat request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->generateFallbackResponse($business, $userContent);

        } catch (Throwable $e) {
            Log::error('AI chat completion error', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return $this->generateFallbackResponse($business, $userContent);
        }
    }

    /**
     * Synthesize clean grounded response directly from business knowledge when OpenAI is offline.
     */
    protected function generateFallbackResponse(Business $business, string $userContent): string
    {
        $summary = $business->summary ?: 'Official business knowledge not yet summarized.';

        return "Hello! I am the official AI assistant representing **{$business->name}**.\n\n"
            ."Based on our indexed knowledge base:\n\n"
            ."{$summary}\n\n"
            .'*(Running in local mode. Set `OPENAI_API_KEY` in .env for live ChatGPT completions.)*';
    }
}
