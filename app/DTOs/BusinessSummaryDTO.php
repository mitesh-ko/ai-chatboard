<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class BusinessSummaryDTO
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $summary,
        public string $systemPrompt,
        public ?array $meta = null,
    ) {}
}
