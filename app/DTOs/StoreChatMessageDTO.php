<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\ChatRole;

readonly class StoreChatMessageDTO
{
    public function __construct(
        public int $chatSessionId,
        public ChatRole $role,
        public string $content,
        public ?int $tokensUsed = null,
    ) {}
}
