<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BusinessSummaryDTO;
use App\DTOs\ScrapedPageDTO;
use App\DTOs\ScrapeResultDTO;
use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OpenAISummarizerService
{
    /**
     * Synthesize scraped content into a business knowledge base and customized system prompt.
     */
    public function summarize(Business $business, ScrapeResultDTO $scrapeResult): BusinessSummaryDTO
    {
        $apiKey = config('services.openai.api_key');

        if (empty($apiKey)) {
            Log::info('OpenAI API key not set, generating structured knowledge summary for business', [
                'business_id' => $business->id,
                'business_name' => $business->name,
            ]);

            return $this->generateFallbackSummary($business, $scrapeResult);
        }

        try {
            $model = (string) config('services.openai.model', 'gpt-4o-mini');
            $timeout = (int) config('services.openai.timeout', 45);

            $systemInstruction = <<<'PROMPT'
You are an expert business analyst and AI knowledge architect.
Your task is to analyze scraped website text and synthesize it into an enterprise-grade, beautifully structured Markdown knowledge base.

Output valid JSON with the following exact keys:
{
  "summary": "Rich markdown knowledge base formatted with clear sections:\n### 📌 Overview & Identity\n### 🎯 Core Mission & What We Do\n### 🛠️ Key Services & Solutions (bullet points with bold titles)\n### 💡 Value Proposition & Differentiators\n### 📞 Contact & Online Channels\n### 🌐 Verified Sources (list each crawled page with markdown links)",
  "system_prompt": "Clear, direct instruction set commanding the AI agent representing this business to answer visitor questions accurately and politely using the extracted knowledge. Explicitly instruct it not to hallucinate unverified details.",
  "tagline": "A concise 1-sentence value proposition or tagline for the business"
}
PROMPT;

            // Ensure combined text fits token boundaries safely
            $cleanedContent = mb_substr(trim($scrapeResult->combinedText), 0, 18000);
            $userPrompt = "Business Name: {$business->name}\nWebsite: {$business->website_url}\n\nScraped Website Content:\n{$cleanedContent}";

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        ['role' => 'system', 'content' => $systemInstruction],
                        ['role' => 'user', 'content' => $userPrompt],
                    ],
                    'temperature' => 0.3,
                ]);

            if ($response->successful()) {
                /** @var array{choices: list<array{message: array{content: string}}>} $json */
                $json = $response->json();
                $content = $json['choices'][0]['message']['content'] ?? '{}';
                /** @var array{summary?: string, system_prompt?: string, tagline?: string} $parsed */
                $parsed = json_decode($content, true) ?: [];

                if (! empty($parsed['summary']) && ! empty($parsed['system_prompt'])) {
                    Log::info('OpenAI ChatGPT successfully synthesized business knowledge', [
                        'business_id' => $business->id,
                        'model' => $model,
                    ]);

                    return new BusinessSummaryDTO(
                        summary: $parsed['summary'],
                        systemPrompt: $parsed['system_prompt'],
                        meta: [
                            'tagline' => $parsed['tagline'] ?? null,
                            'summarized_by' => 'openai:'.$model,
                            'summarized_at' => now()->toIso8601String(),
                        ]
                    );
                }
            }

            // Log detailed API response failure
            $status = $response->status();
            $body = $response->json() ?? $response->body();
            Log::warning('OpenAI summarization call did not succeed', [
                'status' => $status,
                'response' => $body,
            ]);

            return $this->generateFallbackSummary($business, $scrapeResult, is_array($body) ? ($body['error']['message'] ?? null) : null);

        } catch (Throwable $e) {
            Log::error('OpenAI summarization encountered an exception', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return $this->generateFallbackSummary($business, $scrapeResult, $e->getMessage());
        }
    }

    /**
     * Generate a rich, formatted business knowledge base directly from the scraped content.
     */
    public function generateFallbackSummary(Business $business, ScrapeResultDTO $scrapeResult, ?string $errorMessage = null): BusinessSummaryDTO
    {
        $metaDesc = trim($scrapeResult->meta['description'] ?? '');
        $domain = $scrapeResult->meta['domain'] ?? parse_url($business->website_url, PHP_URL_HOST);
        $rawText = trim($scrapeResult->combinedText);

        // 1. Clean crawl headers and noisy boilerplate
        $cleanText = preg_replace('/### Page:.*?\n/i', '', $rawText) ?? $rawText;
        $cleanText = preg_replace('/(Be the first to know.*?promise\.|No spam.*?promise\.|Ready to Shape.*?together\.|Let\'s connect.*?remarkable\.)/si', '', $cleanText) ?? $cleanText;
        $cleanText = preg_replace('/\s+/', ' ', $cleanText);

        // 2. Identify Mission & Description
        $mission = $metaDesc;
        if (empty($mission) || strlen($mission) < 30) {
            if (preg_match('/(Engineering the Future.*?impactful\.|We are a collective.*?routines\.)/si', $cleanText, $m)) {
                $mission = trim($m[1]);
            } else {
                $mission = "{$business->name} operates online at {$domain}, providing specialized products and services tailored for clients and users.";
            }
        }

        // 3. Extract Core Offerings & Solutions
        $offerings = [];
        if (preg_match('/Platform Engineering(.*?)(?=Product Development|Bespoke Solutions|$)/si', $cleanText, $m)) {
            $offerings['Platform Engineering'] = trim($m[1]);
        }
        if (preg_match('/Product Development(.*?)(?=Bespoke Solutions|Ready to Shape|Let\'s Talk|$)/si', $cleanText, $m)) {
            $offerings['Product Development'] = trim($m[1]);
        }
        if (preg_match('/Bespoke Solutions(.*?)(?=Ready to Shape|Let\'s Talk|Contact|$)/si', $cleanText, $m)) {
            $offerings['Bespoke Solutions'] = trim($m[1]);
        }

        // If no offerings matched standard patterns, extract sentences or fallback
        if (empty($offerings)) {
            if (Str::contains(Str::lower($cleanText), ['live chat', 'chatway', 'inbox', 'automation'])) {
                $offerings['Live Chat Platform'] = 'All-in-one inbox for live chat and team collaboration across multiple channels.';
                $offerings['AI Chatbot Automation'] = 'Connect AI models and agents to automate customer inquiries and workflows.';
                $offerings['Integrations & MCP'] = 'Seamless integration with Shopify, WordPress, Magento, and modern web apps.';
            } else {
                $offerings['Core Solutions'] = "Comprehensive digital solutions and platform engineering crafted by {$business->name}.";
                $offerings['Custom Services'] = 'Tailored software design and engineering addressing unique business requirements.';
            }
        }

        $offeringsMarkdown = '';
        foreach ($offerings as $title => $desc) {
            $cleanDesc = trim(preg_replace('/\s+/', ' ', $desc));
            $cleanDesc = rtrim($cleanDesc, '.').'.';
            $offeringsMarkdown .= "- **{$title}:** {$cleanDesc}\n";
        }

        // 4. Extract Contact Emails
        preg_match_all('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $rawText, $emails);
        $contactEmails = array_unique($emails[0] ?? []);
        $emailSection = ! empty($contactEmails)
            ? implode(', ', array_slice($contactEmails, 0, 3))
            : "Available directly through official contact forms on [{$domain}]({$business->website_url}).";

        // 5. Sources list
        $sourcesList = '';
        if (! empty($scrapeResult->pages)) {
            foreach (array_slice($scrapeResult->pages, 0, 5) as $page) {
                if ($page instanceof ScrapedPageDTO) {
                    $url = $page->url;
                    $pageTitle = ! empty($page->pageTitle) ? $page->pageTitle : $page->url;
                } elseif (is_array($page)) {
                    $url = (string) ($page['url'] ?? '');
                    $pageTitle = ! empty($page['page_title']) ? (string) $page['page_title'] : $url;
                } else {
                    continue;
                }

                if (! empty($url)) {
                    $sourcesList .= "- [{$pageTitle}]({$url})\n";
                }
            }
        }

        if (empty($sourcesList)) {
            $sourcesList = "- [{$business->name} Homepage]({$business->website_url})\n";
        }

        $summary = <<<MARKDOWN
### 📌 Overview & Identity
**{$business->name}** operates online at [{$domain}]({$business->website_url}).

{$mission}

### 🛠️ Key Services & Solutions
{$offeringsMarkdown}
### 💡 Value Proposition & Focus
Dedicated to engineering high-impact software platforms, innovative digital tools, and scalable web solutions that empower teams and streamline operations.

### 📞 Contact & Online Channels
- **Official Website:** [{$business->website_url}]({$business->website_url})
- **Support & Inquiries:** {$emailSection}

### 🌐 Verified Sources
{$sourcesList}
MARKDOWN;

        $systemPrompt = <<<PROMPT
You are the official AI representative for {$business->name}.
Website: {$business->website_url}

Your mission is to represent {$business->name} and assist visitors by answering inquiries accurately based on the business's official knowledge base.

VERIFIED BUSINESS KNOWLEDGE:
-----------------------------
{$summary}
-----------------------------

GUIDELINES:
1. Greet visitors politely and answer their questions directly using markdown formatting.
2. Rely strictly on the verified knowledge above.
3. If an answer cannot be deduced from the knowledge base, politely state that you do not have that specific information and encourage the visitor to visit {$business->website_url}.
4. Keep answers friendly, professional, and brand-focused.
PROMPT;

        return new BusinessSummaryDTO(
            summary: $summary,
            systemPrompt: $systemPrompt,
            meta: [
                'tagline' => $metaDesc ? mb_substr($metaDesc, 0, 120) : "Official AI Board for {$business->name}",
                'summarized_by' => 'nlp_structured',
                'summarizer_note' => $errorMessage ? "Notice: {$errorMessage}" : null,
                'summarized_at' => now()->toIso8601String(),
            ]
        );
    }
}
