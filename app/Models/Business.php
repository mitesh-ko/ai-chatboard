<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BusinessStatus;
use Database\Factories\BusinessFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string $slug
 * @property string $website_url
 * @property BusinessStatus $status
 * @property string|null $status_message
 * @property string|null $summary
 * @property string|null $system_prompt
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Business extends Model
{
    /** @use HasFactory<BusinessFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'slug',
        'website_url',
        'status',
        'status_message',
        'summary',
        'system_prompt',
        'meta',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => BusinessStatus::class,
            'meta' => 'array',
        ];
    }

    /**
     * Boot the model to auto-generate slug if not present.
     */
    protected static function booted(): void
    {
        static::creating(function (Business $business): void {
            if (empty($business->slug)) {
                $baseSlug = Str::slug($business->name);
                $slug = $baseSlug;
                $counter = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = $baseSlug.'-'.$counter;
                    $counter++;
                }

                $business->slug = $slug;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<BusinessScrapedData, $this>
     */
    public function scrapedData(): HasMany
    {
        return $this->hasMany(BusinessScrapedData::class);
    }

    /**
     * @return HasMany<ChatSession, $this>
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    /**
     * Scope query to only businesses ready for chat.
     *
     * @param  Builder<$this>  $query
     */
    public function scopeReady(Builder $query): void
    {
        $query->where('status', BusinessStatus::Ready->value);
    }

    public function isReady(): bool
    {
        return $this->status === BusinessStatus::Ready;
    }
}
