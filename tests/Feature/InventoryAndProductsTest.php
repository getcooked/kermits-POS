<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryAndProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_stock_adjustment_is_audited(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $product = Product::query()->create(['name' => 'Inventory Item', 'price' => 20, 'stock' => 5, 'active' => true]);

        $this->actingAs($admin)->post('/inventory/'.$product->id, [
            'type' => 'stock_in',
            'quantity' => 4,
            'note' => 'Delivery',
        ])->assertRedirect();

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $admin->id,
            'stock_before' => 5,
            'stock_after' => 9,
        ]);
    }

    public function test_stock_out_cannot_exceed_available_stock(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $product = Product::query()->create(['name' => 'Inventory Item', 'price' => 20, 'stock' => 2, 'active' => true]);

        $this->actingAs($admin)->post('/inventory/'.$product->id, [
            'type' => 'stock_out',
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_super_admin_creates_products_and_admin_can_update_menu_pictures(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAs($superAdmin)->post('/products', [
            'name' => 'Manual Product',
            'description' => 'Added in the browser',
            'price' => 99.50,
            'stock' => 12,
            'active' => 1,
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Manual Product')->firstOrFail();
        $this->actingAs($admin)->get('/products')->assertOk();
        $this->actingAs($admin)->put('/products/'.$product->id, [
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'active' => 1,
            'image' => $this->fakePng('menu-picture.png'),
        ])->assertRedirect();

        Storage::disk('public')->assertExists($product->fresh()->image_path);
        $this->actingAs($admin)->post('/products', [])->assertForbidden();
        $this->actingAs($admin)->delete('/products/'.$product->id)->assertForbidden();
    }

    public function test_admin_and_super_admin_can_search_products_by_name_or_category(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        Product::query()->create(['name' => 'Iced Spanish Latte', 'category' => 'Drinks', 'price' => 120, 'stock' => 10]);
        Product::query()->create(['name' => 'Almond Roca', 'category' => 'Starters', 'price' => 260, 'stock' => 10]);

        $this->actingAs($admin)
            ->get('/products?search=Spanish')
            ->assertOk()
            ->assertSee('Iced Spanish Latte')
            ->assertDontSee('Almond Roca');

        $this->actingAs($superAdmin)
            ->get('/products?search=Starters')
            ->assertOk()
            ->assertSee('Almond Roca')
            ->assertDontSee('Iced Spanish Latte');
    }

    public function test_add_product_form_is_collapsed_until_needed_and_reopens_after_validation_errors(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $collapsedPage = $this->actingAs($superAdmin)
            ->get('/products')
            ->assertOk()
            ->assertSee('class="product-create"', false);

        $this->assertDoesNotMatchRegularExpression(
            '/<details class="product-create"\s+open\s*>/',
            $collapsedPage->getContent(),
        );

        $invalidCreatePage = $this->actingAs($superAdmin)
            ->followingRedirects()
            ->post('/products', ['form_context' => 'create']);

        $this->assertMatchesRegularExpression(
            '/<details class="product-create"\s+open\s*>/',
            $invalidCreatePage->getContent(),
        );
    }

    public function test_product_picture_is_served_through_laravel(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/menu-picture.png', $this->fakePng('menu-picture.png')->getContent());
        $product = Product::query()->create([
            'name' => 'Visible Menu Picture',
            'price' => 120,
            'stock' => 10,
            'active' => true,
            'image_path' => 'products/menu-picture.png',
        ]);

        $this->get('/menu-images/'.$product->id)->assertOk();

        $product->update(['image_path' => 'products/missing.png']);
        $this->get('/menu-images/'.$product->id)->assertNotFound();
    }

    public function test_customer_shop_uses_the_uploaded_product_picture_route(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/customer-menu.png', $this->fakePng('customer-menu.png')->getContent());
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);
        $product = Product::query()->create([
            'name' => 'Customer Menu Picture',
            'category' => 'Drinks',
            'price' => 120,
            'stock' => 10,
            'active' => true,
            'image_path' => 'products/customer-menu.png',
        ]);

        $this->actingAs($customer)
            ->get('/shop')
            ->assertOk()
            ->assertSee('/menu-images/'.$product->id, false)
            ->assertDontSee('/storage/products/customer-menu.png', false);
    }

    public function test_external_product_picture_url_is_not_prefixed_with_storage(): void
    {
        $product = Product::query()->create([
            'name' => 'External Menu Picture',
            'price' => 120,
            'stock' => 10,
            'active' => true,
            'image_path' => 'https://images.example.com/menu-picture.jpg',
        ]);

        $this->assertSame('https://images.example.com/menu-picture.jpg', $product->imageUrl());
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
