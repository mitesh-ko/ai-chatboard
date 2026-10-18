<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Enums\BusinessStatus;

readonly class UpdateBusinessStatusDTO
{
    public function __construct(
        public BusinessStatus $status,
        public ?string $statusMessage = null,
    ) {}
}
