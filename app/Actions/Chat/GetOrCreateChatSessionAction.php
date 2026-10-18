<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\DTOs\CreateChatSessionDTO;
use App\Enums\ChatSessionStatus;
use App\Models\ChatSession;

class GetOrCreateChatSessionAction
{
    /**
     * Retrieve or create a single-human visitor chat session.
     */
    public function execute(CreateChatSessionDTO $dto): ChatSession
    {
        return ChatSession::firstOrCreate(
            [
                'business_id' => $dto->businessId,
                'session_token' => $dto->sessionToken,
            ],
            [
                'visitor_ip' => $dto->visitorIp,
                'user_agent' => $dto->userAgent,
                'status' => ChatSessionStatus::Active,
            ]
        );
    }
}
