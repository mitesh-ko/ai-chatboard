<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\DTOs\CreateBusinessDTO;
use App\Enums\BusinessStatus;
use App\Events\BusinessCreated;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class CreateBusinessAction
{
    /**
     * Create a new business and dispatch the scraping event.
     */
    public function execute(CreateBusinessDTO $dto): Business
    {
        return DB::transaction(function () use ($dto): Business {
            $business = Business::create([
                'user_id' => $dto->userId,
                'name' => $dto->name,
                'website_url' => $dto->websiteUrl,
                'status' => BusinessStatus::Pending,
                'meta' => $dto->meta,
            ]);

            event(new BusinessCreated($business));

            return $business;
        });
    }
}
