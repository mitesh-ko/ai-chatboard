<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Chat\GetOrCreateChatSessionAction;
use App\Actions\Chat\StoreChatMessageAction;
use App\DTOs\CreateChatSessionDTO;
use App\DTOs\StoreChatMessageDTO;
use App\Enums\ChatRole;
use App\Http\Controllers\Controller;
use App\Models\Business;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpStatus;

class InternalChatController extends Controller
{
    /**
     * Verify internal secret token.
     */
    protected function verifySecret(Request $request): void
    {
        $expectedSecret = (string) config('services.chatboard.internal_secret');
        $providedSecret = $request->header('X-Internal-Secret') ?? $request->input('internal_secret');

        if (! hash_equals($expectedSecret, (string) $providedSecret)) {
            abort(HttpStatus::HTTP_UNAUTHORIZED, 'Unauthorized internal request');
        }
    }

    /**
     * Initialize or retrieve a chat session and load business context.
     */
    public function initSession(
        Request $request,
        GetOrCreateChatSessionAction $getOrCreateChatSessionAction,
    ): JsonResponse {
        $this->verifySecret($request);

        $validated = $request->validate([
            'business_id' => ['required', 'integer', 'exists:businesses,id'],
            'session_token' => ['required', 'string', 'max:64'],
            'visitor_ip' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string'],
        ]);

        $business = Business::findOrFail($validated['business_id']);

        $sessionDto = new CreateChatSessionDTO(
            businessId: (int) $validated['business_id'],
            sessionToken: (string) $validated['session_token'],
            visitorIp: $validated['visitor_ip'] ?? null,
            userAgent: $validated['user_agent'] ?? null,
        );

        $session = $getOrCreateChatSessionAction->execute($sessionDto);

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
                'system_prompt' => $business->system_prompt,
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
     * Store a message in the conversation.
     */
    public function storeMessage(
        Request $request,
        StoreChatMessageAction $storeChatMessageAction,
    ): JsonResponse {
        $this->verifySecret($request);

        $validated = $request->validate([
            'chat_session_id' => ['required', 'integer', 'exists:chat_sessions,id'],
            'role' => ['required', 'string', 'in:user,assistant,system'],
            'content' => ['required', 'string'],
            'tokens_used' => ['nullable', 'integer'],
        ]);

        $dto = new StoreChatMessageDTO(
            chatSessionId: (int) $validated['chat_session_id'],
            role: ChatRole::from($validated['role']),
            content: (string) $validated['content'],
            tokensUsed: isset($validated['tokens_used']) ? (int) $validated['tokens_used'] : null,
        );

        $message = $storeChatMessageAction->execute($dto);

        return response()->json([
            'message' => $message,
        ], HttpStatus::HTTP_CREATED);
    }
}
