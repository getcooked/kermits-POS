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

    public function test_super_admin_stock_adjustment_is_audited(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $product = Product::query()->create(['name' => 'Inventory Item', 'price' => 20, 'stock' => 5, 'active' => true]);

        $this->actingAs($superAdmin)->post('/inventory/'.$product->id, [
            'type' => 'stock_in',
            'quantity' => 4,
            'note' => 'Delivery',
        ])->assertRedirect();

        $this->assertSame(9, $product->fresh()->stock);
        $this->assertDatabaseHas('stock_movements', [
            'product_id' => $product->id,
            'user_id' => $superAdmin->id,
            'stock_before' => 5,
            'stock_after' => 9,
        ]);
    }

    public function test_super_admin_stock_out_cannot_exceed_available_stock(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $product = Product::query()->create(['name' => 'Inventory Item', 'price' => 20, 'stock' => 2, 'active' => true]);

        $this->actingAs($superAdmin)->post('/inventory/'.$product->id, [
            'type' => 'stock_out',
            'quantity' => 3,
        ])->assertSessionHasErrors('quantity');

        $this->assertSame(2, $product->fresh()->stock);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_super_admin_can_create_products_and_update_menu_pictures(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->post('/products', [
            'name' => 'Manual Product',
            'description' => 'Added in the browser',
            'price' => 99.50,
            'stock' => 12,
            'active' => 1,
        ])->assertRedirect();

        $product = Product::query()->where('name', 'Manual Product')->firstOrFail();
        $this->actingAs($superAdmin)->get('/products')->assertOk();
        $this->actingAs($superAdmin)->put('/products/'.$product->id, [
            'name' => $product->name,
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'active' => 1,
            'image' => $this->fakePng('menu-picture.png'),
        ])->assertRedirect();

        Storage::disk('public')->assertExists($product->fresh()->image_path);
        $this->actingAs($superAdmin)->post('/products', [
            'name' => 'Super Admin Product',
            'category' => 'Drinks',
            'description' => 'Created by a super administrator',
            'price' => 75,
            'stock' => 8,
            'active' => 1,
        ])->assertRedirect();

        $this->assertDatabaseHas('products', ['name' => 'Super Admin Product']);
    }

    public function test_admin_cannot_access_super_admin_product_or_inventory_routes(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $product = Product::query()->create([
            'name' => 'Protected Product',
            'category' => 'Drinks',
            'price' => 120,
            'stock' => 5,
            'active' => true,
        ]);

        $this->actingAs($admin)->get('/products')->assertForbidden();
        $this->actingAs($admin)->get('/inventory')->assertForbidden();
        $this->actingAs($admin)->post('/products', [
            'name' => 'Unauthorized Product',
            'category' => 'Drinks',
            'price' => 75,
            'stock' => 8,
            'active' => 1,
        ])->assertForbidden();
        $this->actingAs($admin)->put('/products/'.$product->id, [
            'name' => 'Unauthorized Update',
            'category' => $product->category,
            'price' => $product->price,
            'stock' => $product->stock,
            'active' => 1,
        ])->assertForbidden();
        $this->actingAs($admin)->post('/inventory/'.$product->id, [
            'type' => 'stock_in',
            'quantity' => 4,
            'note' => 'Unauthorized delivery',
        ])->assertForbidden();
        $this->actingAs($admin)->delete('/products/'.$product->id)->assertForbidden();

        $this->assertDatabaseMissing('products', ['name' => 'Unauthorized Product']);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'name' => 'Protected Product',
            'stock' => 5,
        ]);
        $this->assertDatabaseCount('stock_movements', 0);
    }

    public function test_super_admin_can_search_products_by_name_or_category(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $latte = Product::query()->create(['name' => 'Iced Spanish Latte', 'category' => 'Drinks', 'price' => 120, 'stock' => 10]);
        $almondRoca = Product::query()->create(['name' => 'Almond Roca', 'category' => 'Starters', 'price' => 260, 'stock' => 10]);
        $celebrationSlice = Product::query()->create(['name' => 'Celebration Slice', 'category' => 'Junior Size Cake', 'price' => 150, 'stock' => 10]);

        $this->actingAs($superAdmin)
            ->get('/products?search=Spanish')
            ->assertOk()
            ->assertSee('Iced Spanish Latte')
            ->assertSee(route('products.update', $latte), false)
            ->assertDontSee(route('products.update', $almondRoca), false);

        $this->actingAs($superAdmin)
            ->get('/products?search=Starters')
            ->assertOk()
            ->assertSee('Almond Roca')
            ->assertSee(route('products.update', $almondRoca), false)
            ->assertDontSee(route('products.update', $latte), false);

        $this->actingAs($superAdmin)
            ->get('/products?search=Junior+Cake+Size')
            ->assertOk()
            ->assertSee('Celebration Slice')
            ->assertSee(route('products.update', $celebrationSlice), false)
            ->assertDontSee(route('products.update', $almondRoca), false);
    }

    public function test_new_categories_created_by_super_admin_are_immediately_searchable_and_available_in_catalogs(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        $this->actingAs($superAdmin)->post('/products', [
            'name' => 'Seasonal Cooler',
            'category' => 'Seasonal   Cold Drinks',
            'description' => 'A newly added menu category.',
            'price' => 95,
            'stock' => 12,
            'active' => 1,
        ])->assertRedirect('/products');

        $this->assertDatabaseHas('products', [
            'name' => 'Seasonal Cooler',
            'category' => 'Seasonal Cold Drinks',
        ]);

        $this->actingAs($superAdmin)
            ->get('/products?search=Drinks+Seasonal+Cold')
            ->assertOk()
            ->assertSee('Seasonal Cooler')
            ->assertSee('role="combobox"', false)
            ->assertSee('aria-label="Show products and categories"', false)
            ->assertSee('data-search-value="Seasonal Cold Drinks"', false)
            ->assertSee('data-search-value="Seasonal Cooler"', false);

        $this->actingAs($superAdmin)
            ->get('/cashier')
            ->assertOk()
            ->assertSee('data-category-filter="Seasonal Cold Drinks"', false);

        $this->actingAs($cashier)
            ->get('/cashier')
            ->assertOk()
            ->assertSee('data-category-filter="Seasonal Cold Drinks"', false);

        $this->actingAs($customer)
            ->get('/shop')
            ->assertOk()
            ->assertSee('data-shop-category="Seasonal Cold Drinks"', false);
    }

    public function test_super_admin_editing_a_product_category_moves_it_across_all_catalogs(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $cashier = User::factory()->create(['role' => User::ROLE_CASHIER]);
        $customer = User::factory()->create(['role' => User::ROLE_CUSTOMER]);

        Product::query()->create([
            'name' => 'Target Category Item',
            'category' => 'Pasta',
            'category_order' => 8,
            'price' => 250,
            'stock' => 10,
            'active' => true,
        ]);
        $product = Product::query()->create([
            'name' => 'Beef Stroganoff',
            'category' => 'Old Specials',
            'category_order' => 2,
            'description' => 'Tender beef strips.',
            'price' => 310,
            'stock' => 50,
            'active' => true,
        ]);

        $this->actingAs($superAdmin)->put('/products/'.$product->id, [
            'name' => $product->name,
            'category' => 'Pasta',
            'description' => $product->description,
            'price' => $product->price,
            'stock' => $product->stock,
            'active' => 1,
        ])->assertRedirect('/products');

        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'category' => 'Pasta',
            'category_order' => 8,
            'active' => true,
        ]);

        $this->actingAs($superAdmin)->get('/products')
            ->assertOk()
            ->assertSee('data-search-value="Pasta"', false)
            ->assertDontSee('data-search-value="Old Specials"', false);

        foreach ([$superAdmin, $cashier] as $staff) {
            $this->actingAs($staff)->get('/cashier')
                ->assertOk()
                ->assertSee('data-category-filter="Pasta"', false)
                ->assertSee('Beef Stroganoff')
                ->assertDontSee('data-category-filter="Old Specials"', false);
        }

        $this->actingAs($customer)->get('/shop')
            ->assertOk()
            ->assertSee('data-shop-category="Pasta"', false)
            ->assertSee('Beef Stroganoff')
            ->assertDontSee('data-shop-category="Old Specials"', false);

        $this->actingAs($customer)->get('/book')
            ->assertOk()
            ->assertSee('Pasta')
            ->assertSee('Beef Stroganoff')
            ->assertDontSee('Old Specials');
    }

    public function test_super_admin_add_product_form_is_collapsed_until_needed_and_reopens_after_validation_errors(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $collapsedPage = $this->actingAs($superAdmin)
            ->get('/products')
            ->assertOk()
            ->assertSee('id="product-create-toggle"', false)
            ->assertSee('aria-expanded="false"', false);

        $this->assertMatchesRegularExpression(
            '/<section id="product-create-panel" class="welcome product-create-panel"\s+hidden\s*>/',
            $collapsedPage->getContent(),
        );

        $invalidCreatePage = $this->actingAs($superAdmin)
            ->followingRedirects()
            ->post('/products', ['form_context' => 'create']);

        $invalidCreatePage->assertSee('aria-expanded="true"', false);
        $this->assertDoesNotMatchRegularExpression(
            '/<section id="product-create-panel" class="welcome product-create-panel"\s+hidden\s*>/',
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
