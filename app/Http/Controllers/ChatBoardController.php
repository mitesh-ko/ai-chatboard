<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Chat\GetOrCreateChatSessionAction;
use App\Actions\Chat\ProcessChatMessageAction;
use App\DTOs\CreateChatSessionDTO;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ChatBoardController extends Controller
{
    /**
     * Display the public AI Chat Board landing page.
     */
    public function index(Request $request): Response
    {
        $businesses = Business::query()
            ->ready()
            ->latest()
            ->get(['id', 'name', 'slug', 'website_url', 'status', 'summary', 'meta']);

        $selectedSlug = $request->query('business');
        $selectedBusiness = $businesses->firstWhere('slug', $selectedSlug) ?? $businesses->first();

        return Inertia::render('welcome', [
            'businesses' => $businesses,
            'selectedBusiness' => $selectedBusiness,
        ]);
    }

    /**
     * Initialize or retrieve a chat session for a business board.
     */
    public function init(
        Request $request,
        GetOrCreateChatSessionAction $getOrCreateSessionAction,
    ): JsonResponse {
        $validated = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'session_token' => ['required', 'string', 'max:64'],
        ]);

        $business = Business::findOrFail($validated['business_id']);

        $dto = new CreateChatSessionDTO(
            businessId: (int) $validated['business_id'],
            sessionToken: (string) $validated['session_token'],
            visitorIp: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $session = $getOrCreateSessionAction->execute($dto);

        $messages = $session->messages()
            ->latest('id')
            ->take(50)
            ->get()
            ->reverse()
            ->values();

        return response()->json([
            'business' => [
                'id' => $business->id,
                'name' => $business->name,
                'slug' => $business->slug,
                'website_url' => $business->website_url,
                'status' => $business->status->value,
                'summary' => $business->summary,
                'meta' => $business->meta,
            ],
            'session' => [
                'id' => $session->id,
                'session_token' => $session->session_token,
                'status' => $session->status->value,
            ],
            'messages' => $messages,
        ]);
    }

    /**
     * Process an incoming message and return the AI assistant reply.
     */
    public function sendMessage(
        Request $request,
        GetOrCreateChatSessionAction $getOrCreateSessionAction,
        ProcessChatMessageAction $processChatMessageAction,
    ): JsonResponse {
        $validated = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'session_token' => ['required', 'string', 'max:64'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        $business = Business::findOrFail($validated['business_id']);

        $dto = new CreateChatSessionDTO(
            businessId: (int) $validated['business_id'],
            sessionToken: (string) $validated['session_token'],
            visitorIp: $request->ip(),
            userAgent: $request->userAgent(),
        );

        $session = $getOrCreateSessionAction->execute($dto);

        $pair = $processChatMessageAction->executeWithPair(
            $business,
            $session,
            (string) $validated['message']
        );

        $userMessage = $pair['user_message'];
        $assistantMessage = $pair['assistant_message'];

        return response()->json([
            'status' => 'success',
            'user_message' => [
                'id' => $userMessage->id,
                'role' => $userMessage->role->value,
                'content' => $userMessage->content,
                'created_at' => $userMessage->created_at?->toISOString(),
            ],
            'message' => [
                'id' => $assistantMessage->id,
                'role' => $assistantMessage->role->value,
                'content' => $assistantMessage->content,
                'tokens_used' => $assistantMessage->tokens_used,
                'created_at' => $assistantMessage->created_at?->toISOString(),
            ],
        ]);
    }
}
