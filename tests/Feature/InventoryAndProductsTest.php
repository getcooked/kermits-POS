<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Services\ProductImageProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InventoryAndProductsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sidebar_shows_low_stock_count_at_ten_units_or_below(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Product::query()->create(['name' => 'At Threshold', 'price' => 20, 'stock' => 10, 'active' => true]);
        Product::query()->create(['name' => 'Empty', 'price' => 20, 'stock' => 0, 'active' => true]);
        Product::query()->create(['name' => 'Healthy', 'price' => 20, 'stock' => 11, 'active' => true]);
        Product::query()->create(['name' => 'Inactive Low', 'price' => 20, 'stock' => 1, 'active' => false]);

        $html = $this->actingAs($superAdmin)->get('/inventory')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<b id="low-stock-badge"[^>]*aria-label="2 low-stock products"\s*>2<\/b>/', $html);
    }

    public function test_admin_sidebar_hides_low_stock_badge_when_stock_is_healthy(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Product::query()->create(['name' => 'Healthy', 'price' => 20, 'stock' => 11, 'active' => true]);

        $html = $this->actingAs($superAdmin)->get('/inventory')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<b id="low-stock-badge"[^>]*\shidden\s*>0<\/b>/', $html);
    }

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
        $this->assertStringEndsWith('.webp', $product->fresh()->image_path);
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

    public function test_product_stock_cannot_exceed_fifty(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $payload = ['name' => 'Overstocked', 'category' => 'Drinks', 'price' => 95, 'active' => 1];

        $this->actingAs($superAdmin)->post('/products', [...$payload, 'stock' => 51])
            ->assertSessionHasErrors(['stock' => 'Stock cannot be more than 50.']);
        $this->assertDatabaseMissing('products', ['name' => 'Overstocked']);

        $this->actingAs($superAdmin)->post('/products', [...$payload, 'stock' => 50])
            ->assertRedirect('/products');
        $product = Product::query()->where('name', 'Overstocked')->firstOrFail();
        $this->assertSame(50, $product->stock);

        $this->actingAs($superAdmin)->put(route('products.update', $product), [...$payload, 'stock' => 51])
            ->assertSessionHasErrors(['stock' => 'Stock cannot be more than 50.']);
        $this->assertSame(50, $product->fresh()->stock);
    }

    public function test_product_categories_are_saved_in_pascal_case(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);

        $this->actingAs($superAdmin)->post('/products', [
            'name' => 'Steak Plate',
            'category' => '  beef   ENTRÉES ',
            'price' => 350,
            'stock' => 10,
            'active' => 1,
        ])->assertRedirect('/products');

        $product = Product::query()->where('name', 'Steak Plate')->firstOrFail();
        $this->assertSame('Beef Entrées', $product->category);

        $this->actingAs($superAdmin)->put(route('products.update', $product), [
            'name' => 'Steak Plate',
            'category' => 'house specials',
            'price' => 350,
            'stock' => 10,
            'active' => 1,
        ])->assertRedirect('/products');

        $this->assertSame('House Specials', $product->fresh()->category);
    }

    public function test_product_forms_offer_every_existing_category(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $latte = Product::query()->create(['name' => 'Latte', 'category' => 'Drinks', 'price' => 120, 'stock' => 10]);
        Product::query()->create(['name' => 'Nachos', 'category' => 'Starters', 'price' => 180, 'stock' => 10]);

        $this->actingAs($superAdmin)
            ->get('/products?search=Latte')
            ->assertOk()
            ->assertSee('id="category-options-template"', false)
            ->assertSee('aria-controls="category-options"', false)
            ->assertSee('aria-controls="category-edit-options"', false)
            ->assertSee('data-update-url="'.route('products.update', $latte).'"', false)
            ->assertSee('data-category="Drinks"', false)
            ->assertSee('data-category="Starters"', false)
            ->assertSee('max="50"', false);
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

    public function test_super_admin_can_toggle_a_single_product_visibility(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $product = Product::query()->create(['name' => 'Low Latte', 'category' => 'Drinks', 'price' => 120, 'stock' => 3, 'active' => false]);

        $this->actingAs($superAdmin)
            ->patchJson(route('products.visibility', $product), ['active' => 1])
            ->assertOk()
            ->assertJson([
                'ids' => [$product->id],
                'active' => true,
                'low_stock_count' => 1,
            ]);

        $this->assertTrue($product->fresh()->active);
        $this->assertDatabaseHas('activity_logs', ['route_name' => 'products.visibility']);

        $this->actingAs($superAdmin)
            ->patch(route('products.visibility', $product), ['active' => 0])
            ->assertRedirect()
            ->assertSessionHas('status', 'Low Latte is now hidden from the menu.');

        $this->assertFalse($product->fresh()->active);
    }

    public function test_super_admin_can_show_or_hide_many_products_at_once(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $first = Product::query()->create(['name' => 'First', 'category' => 'Drinks', 'price' => 90, 'stock' => 20, 'active' => false]);
        $second = Product::query()->create(['name' => 'Second', 'category' => 'Drinks', 'price' => 90, 'stock' => 20, 'active' => false]);
        $untouched = Product::query()->create(['name' => 'Untouched', 'category' => 'Drinks', 'price' => 90, 'stock' => 20, 'active' => false]);

        $this->actingAs($superAdmin)
            ->patchJson(route('products.visibility.bulk'), ['ids' => [$first->id, $second->id], 'active' => 1])
            ->assertOk()
            ->assertJson(['message' => '2 products shown on the menu.', 'active' => true]);

        $this->assertTrue($first->fresh()->active);
        $this->assertTrue($second->fresh()->active);
        $this->assertFalse($untouched->fresh()->active);

        $this->actingAs($superAdmin)
            ->patchJson(route('products.visibility.bulk'), ['ids' => [$first->id, 999999], 'active' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids.1');
        $this->assertTrue($first->fresh()->active);

        $this->actingAs($superAdmin)
            ->patchJson(route('products.visibility.bulk'), ['ids' => [], 'active' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ids');
    }

    public function test_admin_cannot_change_product_visibility(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $product = Product::query()->create(['name' => 'Protected', 'category' => 'Drinks', 'price' => 90, 'stock' => 20, 'active' => true]);

        $this->actingAs($admin)->patchJson(route('products.visibility', $product), ['active' => 0])->assertForbidden();
        $this->actingAs($admin)->patchJson(route('products.visibility.bulk'), ['ids' => [$product->id], 'active' => 0])->assertForbidden();

        $this->assertTrue($product->fresh()->active);
    }

    public function test_product_page_uses_one_shared_editor_and_reopens_it_after_validation_errors(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        Product::query()->create(['name' => 'Latte', 'category' => 'Drinks', 'price' => 120, 'stock' => 10]);
        $nachos = Product::query()->create(['name' => 'Nachos', 'category' => 'Starters', 'price' => 180, 'stock' => 10]);

        $page = $this->actingAs($superAdmin)->get('/products')->assertOk()->getContent();
        $this->assertSame(1, substr_count($page, 'id="product-editor"'));
        $this->assertSame(1, substr_count($page, 'id="product-edit-form"'));
        $this->assertMatchesRegularExpression('/<div id="product-editor"[^>]*\shidden\s*>/', $page);

        $invalidEdit = $this->actingAs($superAdmin)
            ->followingRedirects()
            ->put(route('products.update', $nachos), [
                'form_context' => 'edit-'.$nachos->id,
                'name' => 'Loaded Nachos',
                'category' => 'Starters',
                'price' => 180,
                'stock' => 51,
            ])
            ->assertOk();

        $this->assertDoesNotMatchRegularExpression('/<div id="product-editor"[^>]*\shidden\s*>/', $invalidEdit->getContent());
        $invalidEdit->assertSee('value="Loaded Nachos"', false)
            ->assertSee('action="'.route('products.update', $nachos).'"', false);
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

        $response = $this->get('/menu-images/'.$product->id)->assertOk();
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=86400', (string) $response->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-store', (string) $response->headers->get('Cache-Control'));

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

    public function test_uploaded_menu_pictures_are_trimmed_squared_and_resized(): void
    {
        // A small dark dish in the middle of a wide 1600x1000 white studio shot.
        $image = imagecreatetruecolor(1600, 1000);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 700, 400, 899, 599, imagecolorallocate($image, 120, 40, 20));
        ob_start();
        imagepng($image);
        $processed = app(ProductImageProcessor::class)->processContents(ob_get_clean());

        [$width, $height, $type] = getimagesizefromstring($processed);
        $this->assertSame(IMAGETYPE_WEBP, $type);
        $this->assertSame([ProductImageProcessor::SIZE, ProductImageProcessor::SIZE], [$width, $height]);

        // The dish now fills the standard share of the frame instead of about an eighth of its width.
        $result = imagecreatefromstring($processed);
        $center = imagecolorsforindex($result, imagecolorat($result, intdiv($width, 2), intdiv($height, 2)));
        $insideEdge = imagecolorsforindex($result, imagecolorat($result, (int) ($width * 0.15), intdiv($height, 2)));
        $margin = imagecolorsforindex($result, imagecolorat($result, (int) ($width * 0.06), intdiv($height, 2)));
        $corner = imagecolorsforindex($result, imagecolorat($result, 2, 2));
        $this->assertLessThan(160, $center['red']);
        $this->assertLessThan(160, $insideEdge['red']);
        $this->assertGreaterThan(240, $margin['green']);
        $this->assertGreaterThan(240, $corner['green']);
    }

    public function test_small_pictures_on_off_white_backgrounds_come_out_as_the_same_standard_square(): void
    {
        // A tiny, tall cup on a light grey backdrop.
        $image = imagecreatetruecolor(300, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 225, 225, 220));
        imagefilledrectangle($image, 140, 40, 159, 159, imagecolorallocate($image, 40, 110, 60));
        ob_start();
        imagepng($image);
        $processed = app(ProductImageProcessor::class)->processContents(ob_get_clean());

        [$width, $height] = getimagesizefromstring($processed);
        $this->assertSame([ProductImageProcessor::SIZE, ProductImageProcessor::SIZE], [$width, $height]);

        $result = imagecreatefromstring($processed);
        $top = imagecolorsforindex($result, imagecolorat($result, 400, (int) (800 * 0.12)));
        $besideCup = imagecolorsforindex($result, imagecolorat($result, 300, 400));
        $this->assertLessThan(140, $top['red'], 'The cup is scaled up to fill the standard height.');
        $this->assertGreaterThanOrEqual(250, min($besideCup['red'], $besideCup['green'], $besideCup['blue']), 'The grey backdrop becomes pure white.');
    }

    public function test_pictures_framed_in_the_cropper_keep_the_admins_framing(): void
    {
        // The admin deliberately placed the dish in the top-left corner of the square.
        $image = imagecreatetruecolor(1200, 1200);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 50, 50, 549, 549, imagecolorallocate($image, 120, 40, 20));
        ob_start();
        imagejpeg($image, null, 92);
        $contents = (string) ob_get_clean();
        $images = app(ProductImageProcessor::class);

        $framed = imagecreatefromstring($images->processContents($contents, framed: true));
        $this->assertSame([ProductImageProcessor::SIZE, ProductImageProcessor::SIZE], [imagesx($framed), imagesy($framed)]);
        $this->assertLessThan(160, imagecolorsforindex($framed, imagecolorat($framed, 100, 100))['red']);
        $this->assertGreaterThan(240, imagecolorsforindex($framed, imagecolorat($framed, 600, 600))['green']);

        // Without the flag the automatic standard would re-center it.
        $automatic = imagecreatefromstring($images->processContents($contents));
        $this->assertLessThan(160, imagecolorsforindex($automatic, imagecolorat($automatic, 400, 400))['red']);
    }

    public function test_sideways_phone_photos_are_turned_upright(): void
    {
        // Stored as 400x200 (red left, blue right) with an EXIF note to turn it 90 degrees clockwise.
        $image = imagecreatetruecolor(400, 200);
        imagefilledrectangle($image, 0, 0, 199, 199, imagecolorallocate($image, 220, 20, 20));
        imagefilledrectangle($image, 200, 0, 399, 199, imagecolorallocate($image, 20, 20, 220));
        ob_start();
        imagejpeg($image, null, 95);
        $tiff = "MM\x00\x2A\x00\x00\x00\x08"."\x00\x01"."\x01\x12\x00\x03\x00\x00\x00\x01\x00\x06\x00\x00"."\x00\x00\x00\x00";
        $exif = "Exif\x00\x00".$tiff;
        $jpeg = substr_replace((string) ob_get_clean(), "\xFF\xE1".pack('n', strlen($exif) + 2).$exif, 2, 0);

        $result = imagecreatefromstring(app(ProductImageProcessor::class)->processContents($jpeg, framed: true));
        $top = imagecolorsforindex($result, imagecolorat($result, 600, 150));
        $bottom = imagecolorsforindex($result, imagecolorat($result, 200, 650));

        // Upright, the picture is 200x400 with red on top and blue below.
        $this->assertGreaterThan($top['blue'], $top['red']);
        $this->assertGreaterThan($bottom['red'], $bottom['blue']);
    }

    public function test_framed_uploads_are_saved_as_the_admin_framed_them(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $image = imagecreatetruecolor(1200, 1200);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledrectangle($image, 50, 50, 549, 549, imagecolorallocate($image, 120, 40, 20));
        ob_start();
        imagejpeg($image, null, 92);

        $this->actingAs($superAdmin)->post('/products', [
            'name' => 'Corner Cake',
            'category' => 'Cakes',
            'price' => 100,
            'stock' => 5,
            'active' => 1,
            'image' => UploadedFile::fake()->createWithContent('corner-framed.jpg', (string) ob_get_clean()),
            'image_framed' => 1,
        ])->assertRedirect('/products');

        $stored = imagecreatefromstring(Storage::disk('public')->get(Product::query()->where('name', 'Corner Cake')->value('image_path')));
        $this->assertLessThan(160, imagecolorsforindex($stored, imagecolorat($stored, 100, 100))['red']);
        $this->assertGreaterThan(240, imagecolorsforindex($stored, imagecolorat($stored, 600, 600))['green']);
    }

    public function test_the_brand_logo_is_recognised_but_dish_photos_are_not(): void
    {
        $images = app(ProductImageProcessor::class);
        $logo = (string) file_get_contents(public_path('kermits-logo.jpg'));

        $this->assertTrue($images->isBrandLogo($logo));
        $this->assertTrue($images->isBrandLogo((string) $images->processContents($logo)));
        $this->assertFalse($images->isBrandLogo($this->dishPng()));
    }

    public function test_the_brand_logo_cannot_be_uploaded_as_a_product_picture(): void
    {
        Storage::fake('public');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $logo = UploadedFile::fake()->createWithContent('logo.jpg', (string) file_get_contents(public_path('kermits-logo.jpg')));

        $this->actingAs($superAdmin)->post('/products', [
            'name' => 'Logo Cake',
            'category' => 'Cakes',
            'price' => 100,
            'stock' => 5,
            'image' => $logo,
        ])->assertSessionHasErrors(['image' => "That's the Kermit's logo. Please upload a photo of the dish instead."]);

        $this->assertDatabaseMissing('products', ['name' => 'Logo Cake']);
    }

    public function test_picture_urls_change_when_a_new_picture_is_stored(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/first.webp', 'first');
        Storage::disk('public')->put('products/second.webp', 'second');
        $product = Product::query()->create(['name' => 'Versioned', 'price' => 50, 'stock' => 5, 'image_path' => 'products/first.webp']);

        $firstUrl = $product->imageUrl();
        $product->update(['image_path' => 'products/second.webp']);

        $this->assertStringStartsWith('/menu-images/'.$product->id.'?v=', $firstUrl);
        $this->assertNotSame($firstUrl, $product->imageUrl());
    }

    public function test_optimize_images_command_standardizes_pictures_and_clears_logo_and_missing_links(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $disk->put('products/dish.png', $this->dishPng());
        $disk->put('products/logo.webp', (string) file_get_contents(public_path('kermits-logo.jpg')));
        $first = Product::query()->create(['name' => 'Dish One', 'price' => 50, 'stock' => 5, 'image_path' => 'products/dish.png']);
        $second = Product::query()->create(['name' => 'Dish Two', 'price' => 50, 'stock' => 5, 'image_path' => 'products/dish.png']);
        $logo = Product::query()->create(['name' => 'Logo Item', 'price' => 50, 'stock' => 5, 'image_path' => 'products/logo.webp']);
        $missing = Product::query()->create(['name' => 'Missing Item', 'price' => 50, 'stock' => 5, 'image_path' => 'products/menu/gone.png']);

        $this->artisan('products:optimize-images', ['--all' => true, '--clear-missing' => true, '--clear-logos' => true, '--dry-run' => true])
            ->expectsOutputToContain('[dry run] 1 standardized, 1 logo stand-ins, 1 missing files')
            ->assertSuccessful();
        $this->assertSame('products/dish.png', $first->fresh()->image_path);

        $this->artisan('products:optimize-images', ['--all' => true, '--clear-missing' => true, '--clear-logos' => true])->assertSuccessful();

        $newPath = $first->fresh()->image_path;
        $this->assertStringEndsWith('.webp', $newPath);
        $this->assertSame($newPath, $second->fresh()->image_path);
        $this->assertSame([ProductImageProcessor::SIZE, ProductImageProcessor::SIZE], array_slice(getimagesizefromstring($disk->get($newPath)), 0, 2));
        $this->assertNull($logo->fresh()->image_path);
        $this->assertNull($missing->fresh()->image_path);

        // Originals are kept with a manifest so the run can be undone.
        $this->assertFalse($disk->exists('products/dish.png'));
        $backup = collect($disk->allFiles('products/originals'));
        $this->assertTrue($backup->contains(fn (string $path) => str_ends_with($path, '/products/dish.png')));
        $this->assertTrue($backup->contains(fn (string $path) => str_ends_with($path, '/products/logo.webp')));
        $manifest = json_decode($disk->get($backup->first(fn (string $path) => str_ends_with($path, 'manifest.json'))), true);
        $this->assertEqualsCanonicalizing(['converted', 'logo', 'missing'], array_column($manifest, 'reason'));
    }

    private function dishPng(): string
    {
        $image = imagecreatetruecolor(600, 400);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagefilledellipse($image, 300, 200, 320, 220, imagecolorallocate($image, 230, 150, 40));
        imagefilledellipse($image, 300, 200, 120, 80, imagecolorallocate($image, 120, 40, 20));
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    private function fakePng(string $name): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent($name, $png);
    }
}
