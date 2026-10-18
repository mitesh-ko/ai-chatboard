<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BusinessSummaryDTO;
use App\DTOs\ScrapeResultDTO;
use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
            Log::info('OpenAI API key not set, generating structured fallback summary for business', [
                'business_id' => $business->id,
                'business_name' => $business->name,
            ]);

            return $this->generateFallbackSummary($business, $scrapeResult);
        }

        try {
            $model = (string) config('services.openai.model', 'gpt-4o-mini');
            $timeout = (int) config('services.openai.timeout', 30);

            $systemInstruction = <<<'PROMPT'
You are an expert business analyst and AI knowledge architect.
Your task is to analyze scraped website text and synthesize it into two distinct parts:
1. "summary": A well-structured, professional business dossier in markdown format covering:
   - Overview & Core Mission
   - Main Products / Services / Offerings
   - Key Value Propositions
   - Target Audience / Ideal Customers
   - Contact, Location, & Support Channels (if found)
   - Important FAQs or Policies (if found)
2. "system_prompt": An instruction set for an AI Chat Board representative speaking on behalf of this business.
   It must command the AI to:
   - Greet visitors politely and represent the business accurately.
   - Answer questions strictly based on the extracted business information.
   - Refuse hallucinations and politely direct the user to official contact channels for missing details.
   - Maintain a helpful, professional, and brand-aligned tone.

Return valid JSON with the exact keys:
{
  "summary": "markdown string",
  "system_prompt": "instruction string",
  "tagline": "short catchy tagline"
}
PROMPT;

            $userPrompt = "Business Name: {$business->name}\nWebsite: {$business->website_url}\n\nScraped Website Content:\n{$scrapeResult->combinedText}";

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
                    return new BusinessSummaryDTO(
                        summary: $parsed['summary'],
                        systemPrompt: $parsed['system_prompt'],
                        meta: [
                            'tagline' => $parsed['tagline'] ?? null,
                            'summarized_by' => $model,
                        ]
                    );
                }
            }

            Log::warning('OpenAI returned unexpected format or error status', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return $this->generateFallbackSummary($business, $scrapeResult);

        } catch (Throwable $e) {
            Log::error('OpenAI summarization failed', [
                'business_id' => $business->id,
                'error' => $e->getMessage(),
            ]);

            return $this->generateFallbackSummary($business, $scrapeResult);
        }
    }

    /**
     * Generate a robust fallback summary directly from scraped data when OpenAI is unavailable.
     */
    protected function generateFallbackSummary(Business $business, ScrapeResultDTO $scrapeResult): BusinessSummaryDTO
    {
        $metaDesc = $scrapeResult->meta['description'] ?? 'No meta description provided.';
        $domain = $scrapeResult->meta['domain'] ?? parse_url($business->website_url, PHP_URL_HOST);
        $pagesCrawled = $scrapeResult->pagesCrawled;

        $snippet = mb_substr(trim($scrapeResult->combinedText), 0, 1500);

        $summary = <<<MARKDOWN
### Overview
**{$business->name}** is accessible online at [{$domain}]({$business->website_url}).

### Description & Mission
{$metaDesc}

### Scraped Highlights
{$snippet}

---
*Information compiled automatically from {$pagesCrawled} indexed page(s) on {$business->website_url}.*
MARKDOWN;

        $systemPrompt = <<<PROMPT
You are the dedicated AI representative for {$business->name}.
Website: {$business->website_url}

Your mission is to assist visitors who interact with this AI chat board by answering questions about {$business->name}.

VERIFIED BUSINESS KNOWLEDGE:
-----------------------------
{$summary}
-----------------------------

CONVERSATION GUIDELINES:
1. Always be polite, concise, and helpful.
2. Rely strictly on the verified knowledge above.
3. If an answer cannot be determined from the knowledge base, politely state that you do not have that specific information and encourage the user to visit {$business->website_url}.
4. Format responses cleanly using markdown (bullet points, bold text).
PROMPT;

        return new BusinessSummaryDTO(
            summary: $summary,
            systemPrompt: $systemPrompt,
            meta: [
                'tagline' => $metaDesc ? mb_substr($metaDesc, 0, 120) : "Official AI Board for {$business->name}",
                'summarized_by' => 'local_fallback',
            ]
        );
    }
}
