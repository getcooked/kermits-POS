<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_customers_can_log_in_and_tokens_are_stored_hashed(): void
    {
        $customer = User::factory()->create([
            'username' => 'mobile.customer', 'email' => 'mobile@gmail.com',
            'password' => 'MobilePassword123!', 'role' => User::ROLE_CUSTOMER,
        ]);
        $admin = User::factory()->create(['password' => 'MobilePassword123!', 'role' => User::ROLE_ADMIN]);

        $response = $this->postJson('/api/v1/login', [
            'login' => $customer->username, 'password' => 'MobilePassword123!', 'device_name' => 'Test phone',
        ])->assertOk()->assertJsonPath('data.user.id', $customer->id);
        $plainToken = $response->json('data.token');

        $this->assertNotEmpty($plainToken);
        $this->assertDatabaseHas('mobile_api_tokens', ['token_hash' => hash('sha256', $plainToken)]);
        $this->assertDatabaseMissing('mobile_api_tokens', ['token_hash' => $plainToken]);

        $this->postJson('/api/v1/login', ['login' => $admin->email, 'password' => 'MobilePassword123!'])
            ->assertUnprocessable();
    }

    public function test_mobile_token_protects_endpoints_and_can_be_revoked(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!']);
        $token = $this->login($customer);

        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $customer->id);
        $this->withToken($token)->postJson('/api/v1/logout')->assertOk();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_customer_can_load_products_place_an_order_and_view_only_their_history(): void
    {
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!']);
        $other = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!']);
        $product = Product::query()->create([
            'name' => 'Mobile Meal', 'category' => 'Meals', 'category_order' => 1,
            'description' => 'Prepared fresh.', 'price' => 250, 'stock' => 5, 'active' => true,
        ]);
        $token = $this->login($customer);

        $this->withToken($token)->getJson('/api/v1/products')
            ->assertOk()->assertJsonPath('data.products.0.stock', 5);
        $orderId = $this->withToken($token)->postJson('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'cash',
        ])->assertCreated()->assertJsonPath('data.total', 500)->json('data.id');

        $this->assertDatabaseHas('products', ['id' => $product->id, 'stock' => 3]);
        $this->withToken($token)->getJson('/api/v1/orders')
            ->assertOk()->assertJsonPath('data.0.id', $orderId);

        $otherToken = $this->login($other);
        $this->withToken($otherToken)->getJson('/api/v1/orders/'.$orderId)->assertForbidden();
    }

    public function test_customer_can_create_and_view_a_cash_reservation(): void
    {
        $customer = User::factory()->create([
            'name' => 'Mobile Guest', 'phone' => '09171234567',
            'role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!',
        ]);
        $token = $this->login($customer);

        $reservationId = $this->withToken($token)->postJson('/api/v1/reservations', [
            'type' => 'table', 'table_size' => 4, 'phone' => '09171234567',
            'reservation_at' => now()->addDays(2)->toIso8601String(),
            'payment_method' => 'cash', 'notes' => 'Near the window',
        ])->assertCreated()
            ->assertJsonPath('data.total_amount', 250)
            ->assertJsonPath('data.status', 'pending')
            ->json('data.id');

        $this->withToken($token)->getJson('/api/v1/reservations/'.$reservationId)
            ->assertOk()->assertJsonPath('data.notes', 'Near the window');
        $this->withToken($token)->getJson('/api/v1/reservations')
            ->assertOk()->assertJsonPath('data.0.id', $reservationId);
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/v1/login', [
            'login' => $user->email, 'password' => 'MobilePassword123!', 'device_name' => 'Feature test',
        ])->assertOk()->json('data.token');
    }
}
