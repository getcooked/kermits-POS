<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
            ->assertUnprocessable()
            ->assertJsonPath('message', 'This mobile app is for customer accounts. Please use the website for staff access.');
    }

    public function test_unverified_customers_are_told_to_verify_before_mobile_login(): void
    {
        $customer = User::factory()->unverified()->create([
            'role' => User::ROLE_CUSTOMER,
            'password' => 'MobilePassword123!',
        ]);

        $this->postJson('/api/v1/login', [
            'login' => $customer->email,
            'password' => 'MobilePassword123!',
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Please verify your Gmail address before signing in.');
    }

    public function test_mobile_login_returns_retry_details_for_a_thirty_second_lockout(): void
    {
        $this->freezeSecond();
        $customer = User::factory()->create([
            'email' => 'mobile.lockout@example.com',
            'password' => 'CorrectMobilePassword123!',
            'role' => User::ROLE_CUSTOMER,
        ]);
        $invalidCredentials = [
            'login' => $customer->email,
            'password' => 'IncorrectMobilePassword123!',
            'device_name' => 'Lockout test phone',
        ];

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->postJson('/api/v1/login', $invalidCredentials)
                ->assertUnprocessable()
                ->assertExactJson([
                    'message' => 'The username/email or password is incorrect.',
                ]);
        }

        $this->postJson('/api/v1/login', $invalidCredentials)
            ->assertStatus(429)
            ->assertHeader('Retry-After', '30')
            ->assertExactJson([
                'message' => 'Too many login attempts. Try again in 30 seconds.',
                'retry_after' => 30,
            ]);

        $this->travel(29)->seconds();

        $this->postJson('/api/v1/login', [
            'login' => $customer->email,
            'password' => 'CorrectMobilePassword123!',
            'device_name' => 'Lockout test phone',
        ])->assertStatus(429)
            ->assertHeader('Retry-After', '1')
            ->assertExactJson([
                'message' => 'Too many login attempts. Try again in 1 second.',
                'retry_after' => 1,
            ]);
        $this->assertDatabaseMissing('mobile_api_tokens', ['user_id' => $customer->id]);

        $this->travel(1)->seconds();

        $response = $this->postJson('/api/v1/login', [
            'login' => $customer->email,
            'password' => 'CorrectMobilePassword123!',
            'device_name' => 'Lockout test phone',
        ])->assertOk()
            ->assertJsonPath('data.user.id', $customer->id);

        $this->assertNotEmpty($response->json('data.token'));
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

    public function test_catalog_returns_an_absolute_product_image_url(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/mobile-meal.png', 'image');
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!']);
        $product = Product::query()->create([
            'name' => 'Photographed Mobile Meal',
            'category' => 'Meals',
            'price' => 250,
            'stock' => 5,
            'active' => true,
            'image_path' => 'products/mobile-meal.png',
        ]);

        $this->withToken($this->login($customer))->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('data.products.0.image_url', route('products.image', $product));
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

    public function test_customer_can_submit_full_mobile_gcash_checkout_with_linked_reservation(): void
    {
        Storage::fake('local');
        $customer = User::factory()->create([
            'name' => 'Mobile Checkout', 'phone' => '09170000000',
            'role' => User::ROLE_CUSTOMER, 'password' => 'MobilePassword123!',
        ]);
        $product = Product::query()->create([
            'name' => 'Checkout Meal', 'category' => 'Meals', 'category_order' => 1,
            'price' => 300, 'stock' => 4, 'active' => true,
        ]);

        $order = $this->withToken($this->login($customer))->post('/api/v1/orders', [
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
            'payment_proof' => $this->fakePngUpload(),
            'table_size' => 4,
            'phone' => '09170000000',
            'reservation_at' => now()->addDays(2)->toIso8601String(),
            'notes' => 'Birthday lunch',
        ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.total', 600)
            ->assertJsonPath('data.payment_reference', '1234567890123')
            ->assertJsonPath('data.reservation.table_size', 4)
            ->assertJsonPath('data.reservation.status', 'pending')
            ->json('data');

        $this->assertDatabaseHas('orders', [
            'id' => $order['id'],
            'customer_id' => $customer->id,
            'payment_method' => 'gcash',
            'payment_reference' => '1234567890123',
        ]);
        $this->assertDatabaseHas('reservations', [
            'order_id' => $order['id'],
            'user_id' => $customer->id,
            'table_size' => 4,
            'payment_reference' => '1234567890123',
            'notes' => 'Birthday lunch',
        ]);
        $reservation = Reservation::query()->where('order_id', $order['id'])->firstOrFail();
        Storage::disk('local')->assertExists($reservation->payment_proof_path);
    }

    private function login(User $user): string
    {
        return $this->postJson('/api/v1/login', [
            'login' => $user->email, 'password' => 'MobilePassword123!', 'device_name' => 'Feature test',
        ])->assertOk()->json('data.token');
    }

    private function fakePngUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'gcash-proof-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        return new UploadedFile($path, 'gcash-proof.png', 'image/png', null, true);
    }
}
