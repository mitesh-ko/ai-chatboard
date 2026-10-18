<?php

declare(strict_types=1);

namespace App\DTOs;

readonly class CreateChatSessionDTO
{
    public function __construct(
        public int $businessId,
        public string $sessionToken,
        public ?string $visitorIp = null,
        public ?string $userAgent = null,
    ) {}
}
