<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\DTOs\BusinessSummaryDTO;
use App\Enums\BusinessStatus;
use App\Events\BusinessStatusUpdated;
use App\Models\Business;
use Illuminate\Support\Facades\DB;
use Throwable;

class SaveBusinessSummaryAction
{
    /**
     * Store AI summary, system prompt, and set status to Ready.
     */
    public function execute(Business $business, BusinessSummaryDTO $dto): Business
    {
        $updated = DB::transaction(function () use ($business, $dto): Business {
            $meta = $business->meta ?? [];
            if ($dto->meta) {
                $meta = array_merge($meta, $dto->meta);
            }

            $business->update([
                'summary' => $dto->summary,
                'system_prompt' => $dto->systemPrompt,
                'meta' => $meta,
                'status' => BusinessStatus::Ready,
                'status_message' => null,
            ]);

            return $business;
        });

        try {
            broadcast(new BusinessStatusUpdated($updated))->toOthers();
        } catch (Throwable) {
            // Silently continue if broadcaster is unconfigured in development
        }

        return $updated;
    }
}
