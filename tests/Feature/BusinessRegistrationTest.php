<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BusinessStatus;
use App\Events\BusinessCreated;
use App\Models\Business;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BusinessRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_cannot_register_a_business(): void
    {
        $response = $this->post(route('businesses.store'), [
            'name' => 'Acme Inc',
            'website_url' => 'https://example.com',
        ]);

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_register_a_business(): void
    {
        Event::fake([BusinessCreated::class]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('businesses.store'), [
            'name' => 'Tech Corp',
            'website_url' => 'https://example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('businesses', [
            'user_id' => $user->id,
            'name' => 'Tech Corp',
            'website_url' => 'https://example.com',
            'status' => BusinessStatus::Pending->value,
        ]);

        Event::assertDispatched(BusinessCreated::class);
    }

    public function test_business_registration_requires_valid_url(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('businesses.store'), [
            'name' => 'Bad Url Corp',
            'website_url' => 'not-a-valid-url',
        ]);

        $response->assertSessionHasErrors('website_url');
    }

    public function test_user_cannot_delete_another_users_business(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        $business = Business::create([
            'user_id' => $owner->id,
            'name' => 'Owner Corp',
            'website_url' => 'https://example.com',
            'status' => BusinessStatus::Ready,
        ]);

        $response = $this->actingAs($otherUser)->delete(route('businesses.destroy', $business));

        $response->assertForbidden();
        $this->assertDatabaseHas('businesses', ['id' => $business->id]);
    }
}
