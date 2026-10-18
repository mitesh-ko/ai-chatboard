<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BusinessScrapedDataFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $business_id
 * @property string $url
 * @property string|null $page_title
 * @property string $clean_text
 * @property Carbon $scraped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class BusinessScrapedData extends Model
{
    /** @use HasFactory<BusinessScrapedDataFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'business_id',
        'url',
        'page_title',
        'clean_text',
        'scraped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scraped_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Business, $this>
     */
    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
