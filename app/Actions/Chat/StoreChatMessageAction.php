<?php

declare(strict_types=1);

namespace App\Actions\Chat;

use App\DTOs\StoreChatMessageDTO;
use App\Models\ChatMessage;

class StoreChatMessageAction
{
    /**
     * Store a message in the chat session.
     */
    public function execute(StoreChatMessageDTO $dto): ChatMessage
    {
        return ChatMessage::create([
            'chat_session_id' => $dto->chatSessionId,
            'role' => $dto->role,
            'content' => $dto->content,
            'tokens_used' => $dto->tokensUsed,
        ]);
    }
}
