<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Http\Requests\StoreBusinessRequest;

readonly class CreateBusinessDTO
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function __construct(
        public string $name,
        public string $websiteUrl,
        public ?int $userId = null,
        public ?array $meta = null,
    ) {}

    public static function fromRequest(StoreBusinessRequest $request, ?int $userId = null): self
    {
        return new self(
            name: (string) $request->validated('name'),
            websiteUrl: (string) $request->validated('website_url'),
            userId: $userId ?? $request->user()?->id,
            meta: $request->validated('meta'),
        );
    }

    /**
     * @param  array{name: string, website_url: string, user_id?: int|null, meta?: array<string, mixed>|null}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            websiteUrl: $data['website_url'],
            userId: $data['user_id'] ?? null,
            meta: $data['meta'] ?? null,
        );
    }

    /**
     * @return array{name: string, website_url: string, user_id: int|null, meta: array<string, mixed>|null}
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'website_url' => $this->websiteUrl,
            'user_id' => $this->userId,
            'meta' => $this->meta,
        ];
    }
}
