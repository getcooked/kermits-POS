<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class MenuImageSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->whereNull('image_path')->each(function (Product $product): void {
            $product->update([
                'image_path' => $this->imageFor($product),
            ]);
        });
    }

    private function imageFor(Product $product): string
    {
        $text = strtolower($product->name.' '.$product->description);

        return match (true) {
            str_contains($text, 'cake'),
            str_contains($text, 'pudding'),
            str_contains($text, 'tiramisu'),
            str_contains($text, 'brazo') => 'products/menu/cakes-desserts.png',

            str_contains($text, 'pasta'),
            str_contains($text, 'spaghetti'),
            str_contains($text, 'palabok'),
            str_contains($text, 'pancit'),
            str_contains($text, 'salicab') => 'products/menu/pasta-noodles.png',

            str_contains($text, 'rice'),
            str_contains($text, 'beef'),
            str_contains($text, 'pork belly'),
            str_contains($text, 'chorizo') => 'products/menu/mains-rice.png',

            str_contains($text, 'shake'),
            str_contains($text, 'lemonade'),
            str_contains($text, 'iced tea'),
            str_contains($text, 'cooler') => 'products/menu/cold-drinks.png',

            str_contains($text, 'latte'),
            str_contains($text, 'americano'),
            str_contains($text, 'cappuccino'),
            str_contains($text, 'macchiato'),
            str_contains($text, 'coffee'),
            str_contains($text, 'matcha') => 'products/menu/coffee.png',

            default => 'products/menu/snacks.png',
        };
    }
}
