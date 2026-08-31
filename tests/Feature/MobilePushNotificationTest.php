<?php

namespace Tests\Feature;

use App\Contracts\FcmMessageSender;
use App\Jobs\SendReservationUpdatedPush;
use App\Models\MobileApiToken;
use App\Models\MobilePushInstallation;
use App\Models\Reservation;
use App\Models\User;
use App\Observers\ReservationObserver;
use App\Support\FcmSendResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MobilePushNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_mobile_session_can_register_and_remove_an_encrypted_installation(): void
    {
        $customer = $this->customer('push-one@example.com');
        $token = $this->login($customer);
        $identifier = 'firebase-installation-id-0123456789';

        $this->putJson('/api/v1/push-installation', ['identifier' => $identifier])
            ->assertUnauthorized();

        $this->withToken($token)->putJson('/api/v1/push-installation', [
            'identifier' => $identifier,
            'provider' => 'fcm',
            'identifier_kind' => 'fid',
            'platform' => 'android',
            'app_version' => '1.0.3',
        ])->assertNoContent();

        $installation = MobilePushInstallation::query()->sole();
        $this->assertSame($customer->id, $installation->user_id);
        $this->assertSame($identifier, $installation->identifier);
        $this->assertSame(hash('sha256', $identifier), $installation->identifier_hash);
        $this->assertNotSame(
            $identifier,
            DB::table('mobile_push_installations')->value('identifier'),
        );

        $this->withToken($token)->deleteJson('/api/v1/push-installation')->assertNoContent();
        $this->assertDatabaseCount('mobile_push_installations', 0);
    }

    public function test_an_installation_is_safely_rebound_to_the_current_mobile_session(): void
    {
        $firstCustomer = $this->customer('push-first@example.com');
        $secondCustomer = $this->customer('push-second@example.com');
        $identifier = 'same-physical-app-installation';

        $this->withToken($this->login($firstCustomer))
            ->putJson('/api/v1/push-installation', ['identifier' => $identifier])
            ->assertNoContent();
        $secondToken = $this->login($secondCustomer);
        $this->withToken($secondToken)
            ->putJson('/api/v1/push-installation', ['identifier' => $identifier])
            ->assertNoContent();

        $installation = MobilePushInstallation::query()->sole();
        $currentToken = MobileApiToken::query()
            ->where('token_hash', hash('sha256', $secondToken))
            ->sole();
        $this->assertSame($secondCustomer->id, $installation->user_id);
        $this->assertSame($currentToken->id, $installation->mobile_api_token_id);
    }

    public function test_visible_reservation_update_queues_one_push_per_customer_installation(): void
    {
        Queue::fake();
        $customer = $this->customer('push-owner@example.com');
        $reservation = $this->reservation($customer);
        $installation = $this->installation($customer, 'owner-installation');

        $reservation->status = 'confirmed';
        $reservation->syncChanges();
        app(ReservationObserver::class)->updated($reservation);

        Queue::assertPushed(SendReservationUpdatedPush::class, function (SendReservationUpdatedPush $job) use ($installation, $reservation): bool {
            return $job->installationId === $installation->id
                && $job->reservationId === $reservation->id
                && $job->status === 'confirmed'
                && $job->changedFields === ['status']
                && $job->title === 'Reservation accepted'
                && $job->body === "Your reservation {$reservation->reference} was accepted by the admin.";
        });
        Queue::assertPushed(SendReservationUpdatedPush::class, 1);
    }

    public function test_internal_reservation_update_does_not_queue_a_customer_push(): void
    {
        Queue::fake();
        $customer = $this->customer('push-internal@example.com');
        $reservation = $this->reservation($customer);
        $this->installation($customer, 'internal-installation');

        $reservation->food_total = '999.00';
        $reservation->syncChanges();
        app(ReservationObserver::class)->updated($reservation);

        Queue::assertNothingPushed();
    }

    public function test_push_job_sends_privacy_limited_data_and_removes_an_invalid_installation(): void
    {
        $customer = $this->customer('push-job@example.com');
        $reservation = $this->reservation($customer);
        $installation = $this->installation($customer, 'invalid-installation');
        $sender = new class implements FcmMessageSender
        {
            /** @var array<string, string> */
            public array $data = [];

            public string $installationId = '';

            public function send(string $installationId, array $data): FcmSendResult
            {
                $this->installationId = $installationId;
                $this->data = $data;

                return FcmSendResult::InvalidInstallation;
            }
        };

        $job = new SendReservationUpdatedPush(
            installationId: $installation->id,
            reservationId: $reservation->id,
            eventId: 'event-123',
            title: 'Reservation Confirmed',
            body: 'Your reservation was confirmed.',
            status: 'confirmed',
            paymentStatus: 'pending',
            changedFields: ['status'],
        );
        $job->handle($sender);

        $this->assertSame('invalid-installation', $sender->installationId);
        $this->assertSame('reservation.updated', $sender->data['type']);
        $this->assertSame('event-123', $sender->data['event_id']);
        $this->assertSame((string) $reservation->id, $sender->data['reservation_id']);
        $this->assertSame($reservation->reference, $sender->data['reference']);
        $this->assertSame('confirmed', $sender->data['status']);
        $this->assertSame('pending', $sender->data['payment_status']);
        $this->assertSame('status', $sender->data['changed_fields']);
        $this->assertSame('Reservation Confirmed', $sender->data['title']);
        $this->assertSame('Your reservation was confirmed.', $sender->data['body']);
        $this->assertSame((string) $customer->id, $sender->data['user_id']);
        $this->assertArrayNotHasKey('customer_name', $sender->data);
        $this->assertArrayNotHasKey('email', $sender->data);
        $this->assertArrayNotHasKey('phone', $sender->data);
        $this->assertDatabaseMissing('mobile_push_installations', ['id' => $installation->id]);
    }

    public function test_deleting_a_customer_revokes_mobile_sessions_and_push_installations(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $customer = $this->customer('push-deleted@example.com');
        $installation = $this->installation($customer, 'deleted-customer-installation');

        $this->actingAs($superAdmin)
            ->delete(route('customers.destroy', $customer))
            ->assertRedirect(route('customers.index'));

        $this->assertDatabaseMissing('mobile_api_tokens', ['id' => $installation->mobile_api_token_id]);
        $this->assertDatabaseMissing('mobile_push_installations', ['id' => $installation->id]);
    }

    private function customer(string $email): User
    {
        return User::factory()->create([
            'email' => $email,
            'password' => 'MobilePassword123!',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/v1/login', [
            'login' => $user->email,
            'password' => 'MobilePassword123!',
            'device_name' => 'Push feature test',
        ])->assertOk()->json('data.token');
    }

    private function reservation(User $customer): Reservation
    {
        return Reservation::query()->create([
            'user_id' => $customer->id,
            'reference' => 'KRM-PUSH-'.str_pad((string) $customer->id, 4, '0', STR_PAD_LEFT),
            'type' => 'table',
            'table_size' => 4,
            'customer_name' => $customer->name,
            'email' => $customer->email,
            'phone' => '09171234567',
            'reservation_at' => now()->addDay(),
            'guests' => 4,
            'reservation_fee' => 250,
            'food_total' => 0,
            'total_amount' => 250,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
    }

    private function installation(User $customer, string $identifier): MobilePushInstallation
    {
        $token = MobileApiToken::query()->create([
            'user_id' => $customer->id,
            'name' => 'Push feature test',
            'token_hash' => hash('sha256', $identifier.'-api-token'),
            'expires_at' => now()->addDay(),
        ]);

        return MobilePushInstallation::query()->create([
            'user_id' => $customer->id,
            'mobile_api_token_id' => $token->id,
            'provider' => 'fcm',
            'identifier_kind' => 'fid',
            'identifier' => $identifier,
            'identifier_hash' => hash('sha256', $identifier),
            'platform' => 'android',
            'app_version' => '1.0.3',
            'last_seen_at' => now(),
        ]);
    }
}
