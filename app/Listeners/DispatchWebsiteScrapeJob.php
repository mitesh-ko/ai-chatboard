<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\BusinessCreated;
use App\Jobs\ScrapeBusinessWebsiteJob;

class DispatchWebsiteScrapeJob
{
    /**
     * Handle the event.
     */
    public function handle(BusinessCreated $event): void
    {
        ScrapeBusinessWebsiteJob::dispatch($event->business);
    }
}
