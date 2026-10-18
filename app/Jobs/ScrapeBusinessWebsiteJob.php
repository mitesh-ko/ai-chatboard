<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Business\SaveBusinessSummaryAction;
use App\Actions\Business\StoreScrapedDataAction;
use App\Actions\Business\UpdateBusinessStatusAction;
use App\DTOs\UpdateBusinessStatusDTO;
use App\Enums\BusinessStatus;
use App\Models\Business;
use App\Services\OpenAISummarizerService;
use App\Services\WebScraperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class ScrapeBusinessWebsiteJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Business $business,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        WebScraperService $scraper,
        OpenAISummarizerService $summarizer,
        UpdateBusinessStatusAction $updateStatusAction,
        StoreScrapedDataAction $storeScrapedDataAction,
        SaveBusinessSummaryAction $saveSummaryAction,
    ): void {
        Log::info('Starting ScrapeBusinessWebsiteJob', [
            'business_id' => $this->business->id,
            'url' => $this->business->website_url,
        ]);

        try {
            // Step 1: Update status to Scraping
            $updateStatusAction->execute(
                $this->business,
                new UpdateBusinessStatusDTO(BusinessStatus::Scraping, 'Crawling website pages...')
            );

            // Step 2: Scrape website content
            $scrapeResult = $scraper->scrape($this->business->website_url);

            // Step 3: Persist scraped pages
            $storeScrapedDataAction->execute($this->business, $scrapeResult);

            // Step 4: Update status to Summarizing
            $updateStatusAction->execute(
                $this->business,
                new UpdateBusinessStatusDTO(BusinessStatus::Summarizing, 'Synthesizing business knowledge with AI...')
            );

            // Step 5: Synthesize business knowledge with ChatGPT
            $summary = $summarizer->summarize($this->business, $scrapeResult);

            // Step 6: Save knowledge base and mark as Ready
            $saveSummaryAction->execute($this->business, $summary);

            Log::info('Completed ScrapeBusinessWebsiteJob successfully', [
                'business_id' => $this->business->id,
            ]);

        } catch (Throwable $e) {
            Log::error('ScrapeBusinessWebsiteJob failed', [
                'business_id' => $this->business->id,
                'error' => $e->getMessage(),
            ]);

            $updateStatusAction->execute(
                $this->business,
                new UpdateBusinessStatusDTO(BusinessStatus::Failed, mb_substr($e->getMessage(), 0, 500))
            );

            throw $e;
        }
    }
}
