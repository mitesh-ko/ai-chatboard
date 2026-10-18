<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\DTOs\UpdateBusinessStatusDTO;
use App\Events\BusinessStatusUpdated;
use App\Models\Business;
use Throwable;

class UpdateBusinessStatusAction
{
    /**
     * Update business status and status message.
     */
    public function execute(Business $business, UpdateBusinessStatusDTO $dto): Business
    {
        $business->update([
            'status' => $dto->status,
            'status_message' => $dto->statusMessage,
        ]);

        try {
            broadcast(new BusinessStatusUpdated($business))->toOthers();
        } catch (Throwable) {
            // Silently continue if broadcaster is unconfigured in development
        }

        return $business;
    }
}
