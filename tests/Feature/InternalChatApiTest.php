<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Enums\ChatRole;
use App\Enums\ChatSessionStatus;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalChatApiTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'test-internal-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.chatboard.internal_secret' => $this->secret]);
    }

    public function test_rejects_unauthorized_internal_requests(): void
    {
        $response = $this->postJson(route('internal.chat.init'), [
            'business_id' => 1,
            'session_token' => 'dummy-token',
        ]);

        $response->assertUnauthorized();
    }

    public function test_initializes_chat_session_with_valid_secret(): void
    {
        $user = User::factory()->create();
        $business = Business::create([
            'user_id' => $user->id,
            'name' => 'Demo Business',
            'website_url' => 'https://example.com',
            'status' => BusinessStatus::Ready,
            'summary' => 'Sample summary',
            'system_prompt' => 'Sample prompt',
        ]);

        $response = $this->withHeader('X-Internal-Secret', $this->secret)
            ->postJson(route('internal.chat.init'), [
                'business_id' => $business->id,
                'session_token' => 'session-uuid-12345',
            ]);

        $response->assertOk();
        $response->assertJsonPath('business.name', 'Demo Business');
        $response->assertJsonPath('session.session_token', 'session-uuid-12345');
        $response->assertJsonPath('session.status', ChatSessionStatus::Active->value);

        $this->assertDatabaseHas('chat_sessions', [
            'business_id' => $business->id,
            'session_token' => 'session-uuid-12345',
        ]);
    }

    public function test_stores_chat_messages_via_internal_api(): void
    {
        $user = User::factory()->create();
        $business = Business::create([
            'user_id' => $user->id,
            'name' => 'Demo Business',
            'website_url' => 'https://example.com',
            'status' => BusinessStatus::Ready,
        ]);

        // Init session first
        $initResponse = $this->withHeader('X-Internal-Secret', $this->secret)
            ->postJson(route('internal.chat.init'), [
                'business_id' => $business->id,
                'session_token' => 'session-token-abc',
            ]);

        $sessionId = $initResponse->json('session.id');

        // Store user message
        $userMsgResponse = $this->withHeader('X-Internal-Secret', $this->secret)
            ->postJson(route('internal.chat.message'), [
                'chat_session_id' => $sessionId,
                'role' => ChatRole::User->value,
                'content' => 'Hello, tell me about your services.',
            ]);

        $userMsgResponse->assertCreated();
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $sessionId,
            'role' => ChatRole::User->value,
            'content' => 'Hello, tell me about your services.',
        ]);

        // Store assistant message
        $assistantMsgResponse = $this->withHeader('X-Internal-Secret', $this->secret)
            ->postJson(route('internal.chat.message'), [
                'chat_session_id' => $sessionId,
                'role' => ChatRole::Assistant->value,
                'content' => 'We offer cloud AI workflows and real-time chat boards.',
            ]);

        $assistantMsgResponse->assertCreated();
        $this->assertDatabaseHas('chat_messages', [
            'chat_session_id' => $sessionId,
            'role' => ChatRole::Assistant->value,
            'content' => 'We offer cloud AI workflows and real-time chat boards.',
        ]);
    }
}
