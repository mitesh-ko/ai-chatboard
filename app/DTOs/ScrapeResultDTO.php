<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class ScrapeResultDTO
{
    /**
     * @param  array<string, mixed>|null  $meta
     * @param  list<ScrapedPageDTO>  $pages
     */
    public function __construct(
        public string $status,
        public string $businessName,
        public string $rootUrl,
        public int $pagesCrawled,
        public string $combinedText,
        public ?array $meta = null,
        public array $pages = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $pages = [];
        if (isset($data['pages']) && is_array($data['pages'])) {
            foreach ($data['pages'] as $page) {
                if (is_array($page) && isset($page['url'], $page['clean_text'])) {
                    $pages[] = ScrapedPageDTO::fromArray($page);
                }
            }
        }

        return new self(
            status: (string) ($data['status'] ?? 'success'),
            businessName: (string) ($data['business_name'] ?? ''),
            rootUrl: (string) ($data['root_url'] ?? ''),
            pagesCrawled: (int) ($data['pages_crawled'] ?? count($pages)),
            combinedText: (string) ($data['combined_text'] ?? ''),
            meta: isset($data['meta']) && is_array($data['meta']) ? $data['meta'] : null,
            pages: $pages,
        );
    }
}
