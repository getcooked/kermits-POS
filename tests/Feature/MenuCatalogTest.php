<?php

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\MenuCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_photographed_menu_is_seeded_by_category_and_is_idempotent(): void
    {
        $this->seed(MenuCatalogSeeder::class);
        $this->seed(MenuCatalogSeeder::class);

        $this->assertSame(138, Product::query()->count());
        $this->assertDatabaseHas('products', ['name' => 'Scallops Con Queso', 'category' => 'Starters', 'price' => 245]);
        $this->assertDatabaseHas('products', ['name' => "Kermit's Classic Burger", 'category' => 'Buns & Beyond', 'price' => 290]);
        $this->assertDatabaseHas('products', ['name' => 'Beef with Onions', 'category' => 'Beef Entrées', 'price' => 230]);
        $this->assertDatabaseHas('products', ['name' => 'Biscoff Pudding Latte', 'category' => 'Crafted Drinks', 'price' => 210]);
        $this->assertDatabaseHas('products', ['name' => 'Chocolate Pistachio · Whole Cake', 'category' => 'Cakes', 'price' => 1400]);
        $this->assertDatabaseHas('products', ['name' => 'Chocolate Pistachio · Slice', 'category' => 'Cakes', 'price' => 140]);
    }
}
