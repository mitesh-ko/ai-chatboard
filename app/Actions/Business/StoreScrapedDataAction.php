<?php

declare(strict_types=1);

namespace App\Actions\Business;

use App\DTOs\ScrapeResultDTO;
use App\Models\Business;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class StoreScrapedDataAction
{
    /**
     * Store scraped pages for a business.
     */
    public function execute(Business $business, ScrapeResultDTO $dto): void
    {
        DB::transaction(function () use ($business, $dto): void {
            // Remove previous scraped data if re-scraping
            $business->scrapedData()->delete();

            $pages = $dto->pages;
            if (empty($pages)) {
                // Store combined text as single page if no breakdown
                $business->scrapedData()->create([
                    'url' => $dto->rootUrl,
                    'page_title' => $dto->businessName,
                    'clean_text' => $dto->combinedText,
                    'scraped_at' => Carbon::now(),
                ]);

                return;
            }

            foreach ($pages as $page) {
                $business->scrapedData()->create([
                    'url' => $page->url,
                    'page_title' => $page->pageTitle,
                    'clean_text' => $page->cleanText,
                    'scraped_at' => $page->scrapedAt ? Carbon::parse($page->scrapedAt) : Carbon::now(),
                ]);
            }

            // Update business meta with scraped meta if available
            if ($dto->meta) {
                $existingMeta = $business->meta ?? [];
                $business->update([
                    'meta' => array_merge($existingMeta, $dto->meta),
                ]);
            }
        });
    }
}
