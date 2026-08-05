<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class MenuCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $menu = [
            'Starters' => [
                ['Scallops Con Queso', 'Tender scallops baked in a creamy, cheesy sauce.', 245],
                ['Truffled Shoestring', 'Crispy golden shoestring potatoes with truffle.', 260],
                ["Sour n' Cream Shoestring", 'Crispy shoestring potatoes with sour cream seasoning.', 260],
                ['Cheese Shoestring', 'Crispy shoestring potatoes with cheese seasoning.', 260],
                ['BBQ Shoestring', 'Crispy shoestring potatoes with barbecue seasoning.', 260],
            ],
            'Salads' => [
                ["Kermit's Salad", 'Fresh house salad prepared with signature dressing.', 220],
                ['Caesar Salad', 'Classic Caesar salad with crisp greens.', 210],
            ],
            'Pasta & Noodles' => [
                ['Baked Lasagna', 'Wide pasta sheets with tomato sauce, beef, and cheese.', 265],
                ['Aligue Pasta', 'Spaghetti in a rich, buttery crab roe sauce.', 290],
                ['Aglio Olio', 'Italian pasta with olive oil, garlic, and chili flakes.', 250],
                ['Baked Spaghetti', 'Classic spaghetti baked with meat sauce and cheese.', 300],
                ['Regular Spaghetti', 'Classic spaghetti with savory meat and tomato sauce.', 160],
                ['Truffled Carbonara', 'Creamy pasta with bacon, truffle, and grated cheese.', 270],
                ['Palabok', 'Rice noodles with savory garlic sauce, shrimp, pork, and chicharon.', 250],
                ['Pancit Guisado', 'Filipino noodles sautéed with meat and vegetables.', 240],
                ['Bihon Guisado', 'Stir-fried rice noodles with meat and vegetables.', 240],
                ['Seafood Puttanesca', 'Puttanesca sauce with fresh seafood and linguine.', 290],
            ],
            'Buns & Beyond' => [
                ["Kermit's Classic Burger", 'House-made patty, fresh-baked buns, and special dressing.', 290],
                ['Cheeseburger', 'Grilled patty with melted cheese, vegetables, and signature sauce.', 200],
                ['Cheeseburger with Bacon', 'Cheeseburger topped with crispy bacon.', 240],
                ['Cheeseburger with Bacon & Egg', 'Beef patty, bacon, fried egg, and melted cheese.', 250],
                ['Three Cheese Burger', 'Char-grilled beef with three rich cheeses.', 275],
                ['Quarter Pounder', 'Beef patty with melted cheese and fresh toppings.', 275],
                ["Kermit's Special Burger", 'Grilled beef with rich béchamel sauce.', 300],
                ['Bacon Mushroom Melt', 'Smoky bacon, sautéed mushrooms, and melted cheese.', 280],
                ["Kermit's Hawaiian Burger", 'Beef patty with caramelized pineapple and bacon.', 290],
                ['Crispy Chicken Sandwich', 'Golden fried marinated chicken with salad.', 290],
            ],
            'Sides & Others' => [
                ['Chopsuey', 'Mixed vegetables cooked in a savory sauce.', 200],
                ['Mashed Potato', 'Smooth and creamy mashed potato.', 120],
            ],
            'Beef Entrées' => [
                ['Beef Stroganoff', 'Tender beef strips in creamy mustard sauce with rice.', 310],
                ['Burger Steak with Bacon', 'Beef patty with bacon and mushroom gravy with rice.', 300],
                ['Regular Burger Steak', 'Beef patty with savory gravy and rice.', 275],
                ['Beef with Onions', 'Beef slices with caramelized onions and rice.', 230],
                ['Osso Buco', 'Slow-braised beef shank with herbs and vegetables; good for two.', 460],
                ['Salpicado', 'Garlic-sautéed tenderloin beef strips with rice.', 370],
            ],
            'Pork Entrées' => [
                ['BBQ Ribs', 'Pork ribs glazed with smoky barbecue sauce and served with rice.', 450],
                ['Grilled Hawaiian Belly', 'Pork belly glazed with sweet Hawaiian-style sauce.', 250],
                ['Lechon Kawali in Bagoong Rice', 'Crispy pork belly served with bagoong rice.', 250],
                ['Sisig', 'Sizzling chopped pork with spices, onions, and calamansi.', 295],
                ['Pork Schnitzel', 'Golden breaded pork cutlet served with rice.', 225],
            ],
            'Chicken Entrées' => [
                ['Chicken Potato Casserole', 'Chicken and potatoes baked in a creamy savory sauce.', 330],
                ['Chicken Stroganoff', 'Chicken strips in creamy mushroom sauce.', 290],
                ['Chicken Fun Bites', 'Crispy bite-sized chicken with dipping sauce.', 220],
                ['Chicken Fillet', 'Tender chicken fillet in creamy sauce with rice.', 230],
            ],
            'Burrito & Wraps' => [
                ['Gyro Chicken Wrap', 'Gyro chicken, fresh vegetables, and creamy sauce.', 230],
                ['Gyro Taco Wrap', 'Gyro beef, vegetables, and zesty sauce in a taco shell.', 300],
                ['Chunky Chicken Wrap', 'Chicken chunks, vegetables, and savory sauce.', 210],
                ['Ham & Beef Combo Wrap', 'Ham, beef, and vegetables in a soft tortilla.', 175],
                ['Beef Burrito', 'Savory beef and fresh vegetables in a soft tortilla.', 170],
                ['Chicken Burrito', 'Savory chicken and fresh vegetables in a soft tortilla.', 170],
                ['Veggie Burrito', 'Sautéed vegetables and savory sauce in a soft tortilla.', 150],
            ],
            'Coffee Classics' => [
                ['Espresso Macchiato', 'Classic espresso coffee.', 110], ['Espresso Doppio', 'Double espresso.', 100],
                ['Americano', 'Espresso with hot water.', 120], ['Cappuccino', 'Espresso with steamed milk foam.', 130],
                ['Café Latte', 'Espresso with steamed milk.', 145], ['Flat White', 'Smooth espresso and steamed milk.', 140],
                ['Matcha', 'Classic matcha drink.', 130], ['Mocha', 'Chocolate espresso drink.', 140], ['Hot Tea', 'Freshly brewed hot tea.', 95],
                ["Kermit's Signature", 'A barista signature short espresso extraction.', 170],
            ],
            'Crafted Drinks' => [
                ["Kermit's Golden Cloud", 'Iced crafted drink with cloud cream.', 200], ['Croak Chatta', 'Iced crafted drink with cloud cream.', 200],
                ['Biscoff Latte', 'Iced Biscoff latte with cloud cream.', 200], ['Dirty Matcha', 'Matcha with espresso and cloud cream.', 185],
                ['Spanish Latte', 'Iced Spanish-style latte.', 165], ['Caramel Macchiato', 'Iced caramel macchiato.', 160],
                ['Matcha Cloud', 'Iced matcha with cloud cream.', 160], ['Caffe Latte Dream', 'Iced latte with cloud cream.', 150],
                ['Banana Pudding Matcha', 'Matcha inspired by banana pudding.', 180], ['Biscoff Pudding Latte', 'Biscoff pudding-inspired latte.', 210],
            ],
            'House Specials' => [
                ['Fresh Lemonade', 'Fresh house lemonade.', 125], ['Home Brewed Iced Tea', 'House-brewed iced tea.', 120],
                ['Halo-Halo Special', 'Special Filipino shaved-ice dessert drink.', 200], ['Halo-Halo Regular', 'Classic Filipino shaved-ice dessert drink.', 180],
                ['Peach Mango', 'Refreshing peach and mango drink.', 200], ['Mais Con Yelo', 'Sweet corn with shaved ice and milk.', 200],
                ['Special Mango Con Yelo', 'Mango with shaved ice and milk.', 200], ["Cookies n' Cream Shake", 'Creamy cookies and cream shake.', 200],
            ],
            'Blended Drinks' => [
                ['Biscoff Blended Coffee', 'Coffee-based Biscoff blended drink.', 190], ['Mocha Blended Coffee', 'Coffee-based mocha blended drink.', 180],
                ['Salted Caramel Blended Coffee', 'Coffee-based salted caramel blend.', 190], ['Matcha Blended', 'Coffee-free matcha blend.', 180],
                ['Mango Graham Blended', 'Coffee-free mango graham blend.', 190], ['Vanilla Blended', 'Coffee-free vanilla blend.', 190],
                ['Mango Blended', 'Coffee-free mango blend.', 190],
            ],
            'Other Drinks' => [
                ['Bottled Water', 'Chilled bottled water.', 30], ['Coke Regular', 'Chilled soft drink.', 25],
                ['Coke Zero', 'Zero-sugar chilled soft drink.', 80], ['Sprite', 'Chilled lemon-lime soft drink.', 25],
            ],
            'Cakes' => $this->cakes(),
        ];

        Product::query()->update(['active' => false]);
        $categoryOrder = 0;
        foreach ($menu as $category => $items) {
            $categoryOrder++;
            foreach ($items as [$name, $description, $price]) {
                Product::query()->updateOrCreate(['name' => $name], [
                    'category' => $category, 'category_order' => $categoryOrder,
                    'description' => $description, 'price' => $price, 'stock' => 50, 'active' => true,
                ]);
            }
        }
    }

    private function cakes(): array
    {
        $cakes = [
            ['Chocolate Pistachio',1400,140], ['Strawberry Pistachio',1400,140], ['Classic Tiramisu',1200,130],
            ['Pistachio Tiramisu',1200,130], ['Mango Tiramisu',1200,130], ['Rocher Chocolate',1100,110],
            ['Blackout Chocolate',1100,110], ['Black Forest',1100,110], ['Salted Chocolate',1100,110],
            ['Cookies & Cream',1100,110], ['Midnight Chocolate',1100,110], ['Chocolate Overload',1100,110],
            ['Red Velvet',1100,110], ['Carrot Cake',1100,110], ['Ube Custard',1100,110], ['Ube Classic',1000,100],
            ['Mango Overload',1100,110], ['Mango Graham',1100,110], ['Mango Tres Leches',1100,110],
            ['Mango Buttercreme',1000,100], ['Pistachio Sans Rival',1200,130], ['Classic Sans Rival',1100,120],
            ['White Forest',1000,100], ['Salted Caramel',1000,100],
        ];

        return collect($cakes)->flatMap(fn ($cake) => [
            [$cake[0].' · Whole Cake', $cake[0].' whole cake.', $cake[1]],
            [$cake[0].' · Slice', $cake[0].' single slice.', $cake[2]],
        ])->all();
    }
}
