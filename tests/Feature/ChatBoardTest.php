<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Enums\ChatRole;
use App\Events\ChatMessageCreated;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ChatBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_initialize_chat_session(): void
    {
        $user = User::factory()->create();
        $business = Business::create([
            'user_id' => $user->id,
            'name' => 'Acme Test Corp',
            'website_url' => 'https://example.com',
            'status' => BusinessStatus::Ready,
            'summary' => 'A technology company.',
            'system_prompt' => 'You are the AI representative.',
        ]);

        $response = $this->postJson(route('chat.init'), [
            'business_id' => $business->id,
            'session_token' => 'test-session-token-xyz',
        ]);

        $response->assertOk();
        $response->assertJsonPath('business.name', 'Acme Test Corp');
        $response->assertJsonPath('session.session_token', 'test-session-token-xyz');

        $this->assertDatabaseHas('chat_sessions', [
            'business_id' => $business->id,
            'session_token' => 'test-session-token-xyz',
        ]);
    }

    public function test_can_send_message_and_broadcasts_event(): void
    {
        Event::fake([ChatMessageCreated::class]);

        $user = User::factory()->create();
        $business = Business::create([
            'user_id' => $user->id,
            'name' => 'Acme Test Corp',
            'website_url' => 'https://example.com',
            'status' => BusinessStatus::Ready,
            'summary' => 'A technology company.',
            'system_prompt' => 'You are the AI representative.',
        ]);

        $response = $this->postJson(route('chat.message'), [
            'business_id' => $business->id,
            'session_token' => 'test-session-token-xyz',
            'message' => 'Hello, what services do you provide?',
        ]);

        $response->assertOk();
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('message.role', ChatRole::Assistant->value);

        // Check user message is saved in DB
        $this->assertDatabaseHas('chat_messages', [
            'role' => ChatRole::User->value,
            'content' => 'Hello, what services do you provide?',
        ]);

        // Check assistant message is saved in DB
        $this->assertDatabaseHas('chat_messages', [
            'role' => ChatRole::Assistant->value,
        ]);

        // Check Pusher broadcast event was fired
        Event::assertDispatched(ChatMessageCreated::class);
    }
}
