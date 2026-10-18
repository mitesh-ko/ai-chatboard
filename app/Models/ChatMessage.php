<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ChatRole;
use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $chat_session_id
 * @property ChatRole $role
 * @property string $content
 * @property int|null $tokens_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'chat_session_id',
        'role',
        'content',
        'tokens_used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => ChatRole::class,
            'tokens_used' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ChatSession, $this>
     */
    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
}
