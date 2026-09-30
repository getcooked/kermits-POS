<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationAdminViewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2030, 1, 1)->setTime(9, 0));
    }

    private function reservation(string $at, array $attributes = []): Reservation
    {
        return Reservation::query()->create(array_merge([
            'reference' => 'KRM-'.strtoupper(bin2hex(random_bytes(4))),
            'type' => 'table',
            'customer_name' => 'Guest',
            'email' => 'guest@example.com',
            'phone' => '09171234567',
            'reservation_at' => $at,
            'guests' => 2,
            'table_size' => 2,
            'total_amount' => 100,
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'confirmed',
        ], $attributes));
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    }

    public function test_list_shows_upcoming_bookings_by_default_and_past_ones_on_request(): void
    {
        $this->reservation('2030-01-03 18:00:00', ['customer_name' => 'Future Guest']);
        $this->reservation('2029-12-20 18:00:00', ['customer_name' => 'Old Guest', 'status' => 'completed']);

        $this->actingAs($this->admin())->get('/reservations')->assertOk()
            ->assertSee('Future Guest')
            ->assertDontSee('Old Guest');

        $this->get('/reservations?when=past')->assertOk()
            ->assertSee('Old Guest')
            ->assertDontSee('Future Guest');

        $this->get('/reservations?when=all')->assertOk()
            ->assertSee('Old Guest')
            ->assertSee('Future Guest');
    }

    public function test_list_is_paginated(): void
    {
        foreach (range(1, 17) as $day) {
            $this->reservation(sprintf('2030-02-%02d 18:00:00', $day), ['customer_name' => 'Guest Day '.$day]);
        }

        $this->actingAs($this->admin())->get('/reservations')->assertOk()
            ->assertSee('Guest Day 15')
            ->assertDontSee('Guest Day 16')
            ->assertSee('Showing 1–15 of 17');

        $this->get('/reservations?page=2')->assertOk()
            ->assertSee('Guest Day 17')
            ->assertDontSee('Guest Day 3<');
    }

    public function test_search_finds_bookings_by_name_or_reference_across_all_pages(): void
    {
        $this->reservation('2030-01-05 18:00:00', ['customer_name' => 'Maria Santos', 'reference' => 'KRM-FINDME']);
        $this->reservation('2030-01-06 18:00:00', ['customer_name' => 'Someone Else']);

        $this->actingAs($this->admin())->get('/reservations?search=santos')->assertOk()
            ->assertSee('Maria Santos')
            ->assertDontSee('Someone Else');

        $this->get('/reservations?search=KRM-FINDME')->assertOk()->assertSee('Maria Santos');
    }

    public function test_status_filter_separates_pending_from_expired_requests(): void
    {
        $this->reservation('2030-01-05 18:00:00', ['customer_name' => 'Waiting Guest', 'status' => 'pending', 'hold_expires_at' => '2030-01-01 09:30:00']);
        $this->reservation('2030-01-05 19:00:00', ['customer_name' => 'Lapsed Guest', 'status' => 'pending', 'hold_expires_at' => '2030-01-01 08:30:00']);

        $this->actingAs($this->admin())->get('/reservations?status=pending')->assertOk()
            ->assertSee('Waiting Guest')
            ->assertDontSee('Lapsed Guest');

        $this->get('/reservations?status=expired')->assertOk()
            ->assertSee('Lapsed Guest')
            ->assertDontSee('Waiting Guest');
    }

    public function test_sidebar_shows_how_many_reservations_need_approval(): void
    {
        $this->reservation('2030-01-05 18:00:00', ['status' => 'pending', 'hold_expires_at' => '2030-01-01 09:30:00']);
        $this->reservation('2030-01-05 19:00:00', ['status' => 'pending', 'hold_expires_at' => '2030-01-01 09:30:00']);
        $this->reservation('2030-01-05 20:00:00', ['status' => 'pending', 'hold_expires_at' => '2030-01-01 08:30:00']);

        $this->actingAs($this->admin())->get('/dashboard')->assertOk()
            ->assertSee('2 reservations waiting for approval');
    }

    public function test_sidebar_badge_is_hidden_when_nothing_needs_approval(): void
    {
        $this->actingAs($this->admin())->get('/dashboard')->assertOk()
            ->assertSee('aria-label="0 reservations waiting for approval"  hidden', false);
    }

    public function test_timeline_places_bookings_on_their_table_and_flags_overlaps(): void
    {
        $table = DiningTable::query()->where('number', 5)->firstOrFail();
        $this->reservation('2030-01-04 18:00:00', ['customer_name' => 'First Party', 'dining_table_id' => $table->id, 'reservation_end_at' => '2030-01-04 20:00:00']);
        $this->reservation('2030-01-04 19:00:00', ['customer_name' => 'Second Party', 'dining_table_id' => $table->id, 'reservation_end_at' => '2030-01-04 21:00:00']);
        $this->reservation('2030-01-04 12:00:00', ['customer_name' => 'Flexible Party']);
        $this->reservation('2030-01-04 13:00:00', ['customer_name' => 'Declined Party', 'status' => 'rejected']);

        $this->actingAs($this->admin())->get('/reservations?view=timeline&date=2030-01-04')->assertOk()
            ->assertSee('January 04, 2030')
            ->assertSee('First Party')
            ->assertSee('Second Party')
            ->assertSee('Overlapping bookings')
            ->assertSee('Any Available Table')
            ->assertSee('Flexible Party')
            ->assertDontSee('Declined Party');
    }

    public function test_timeline_defaults_to_today(): void
    {
        $this->reservation('2030-01-01 18:00:00', ['customer_name' => 'Tonight Guest']);

        $this->actingAs($this->admin())->get('/reservations?view=timeline')->assertOk()
            ->assertSee('Today, January 01, 2030')
            ->assertSee('Tonight Guest');
    }
}
