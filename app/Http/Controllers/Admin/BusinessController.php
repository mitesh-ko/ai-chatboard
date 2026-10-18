<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Business\CreateBusinessAction;
use App\DTOs\CreateBusinessDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBusinessRequest;
use App\Jobs\ScrapeBusinessWebsiteJob;
use App\Models\Business;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BusinessController extends Controller
{
    /**
     * Display a listing of businesses registered by the authenticated user.
     */
    public function index(Request $request): Response
    {
        $businesses = $request->user()
            ?->businesses()
            ->withCount(['scrapedData', 'chatSessions'])
            ->latest()
            ->get();

        return Inertia::render('admin/businesses/index', [
            'businesses' => $businesses,
        ]);
    }

    /**
     * Store a newly created business and dispatch web scraper pipeline.
     */
    public function store(StoreBusinessRequest $request, CreateBusinessAction $createBusinessAction): RedirectResponse
    {
        $dto = CreateBusinessDTO::fromRequest($request);
        $createBusinessAction->execute($dto);

        return back()->with('success', 'Business registered successfully! Website scraping has been initiated.');
    }

    /**
     * Manually re-trigger scraping pipeline for a business.
     */
    public function rescrape(Request $request, Business $business): RedirectResponse
    {
        if ($business->user_id !== $request->user()?->id) {
            abort(403);
        }

        ScrapeBusinessWebsiteJob::dispatch($business);

        return back()->with('success', "Re-scraping initiated for {$business->name}.");
    }

    /**
     * Remove the specified business from storage.
     */
    public function destroy(Request $request, Business $business): RedirectResponse
    {
        if ($business->user_id !== $request->user()?->id) {
            abort(403);
        }

        $business->delete();

        return back()->with('success', 'Business deleted successfully.');
    }
}
