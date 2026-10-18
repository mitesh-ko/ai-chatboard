<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ScrapedPageDTO
{
    public function __construct(
        public string $url,
        public ?string $pageTitle,
        public string $cleanText,
        public ?string $scrapedAt = null,
    ) {}

    /**
     * @param  array{url: string, page_title?: string|null, clean_text: string, scraped_at?: string|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: $data['url'],
            pageTitle: $data['page_title'] ?? null,
            cleanText: $data['clean_text'],
            scrapedAt: $data['scraped_at'] ?? null,
        );
    }
}
