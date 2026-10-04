<?php

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCatalogSeeder extends Seeder
{
    public function run()
    {
        $catalog = [
            ['Fresh Produce', 'Fruit', 'Honeycrisp Apples', 'Crisp, sweet apples picked for everyday snacking.', 4.99, 48, 'percentage', 10],
            ['Fresh Produce', 'Fruit', 'Organic Bananas', 'Naturally sweet organic bananas, sold by the bunch.', 3.49, 62, null, 0],
            ['Fresh Produce', 'Vegetables', 'Baby Spinach', 'Tender washed spinach leaves for salads and smoothies.', 3.99, 35, 'flat', 0.50],
            ['Fresh Produce', 'Vegetables', 'Rainbow Carrots', 'Colourful crunchy carrots with a naturally sweet flavour.', 4.49, 29, null, 0],
            ['Fresh Produce', 'Vegetables', 'Vine Tomatoes', 'Juicy ripe tomatoes for sandwiches, sauces, and salads.', 3.79, 41, 'percentage', 8],
            ['Fresh Produce', 'Herbs', 'Fresh Basil', 'Aromatic basil leaves, perfect for pasta and pesto.', 2.99, 24, null, 0],
            ['Dairy & Eggs', 'Milk', 'Whole Milk 1L', 'Rich and creamy pasteurised whole milk.', 2.89, 52, null, 0],
            ['Dairy & Eggs', 'Cheese', 'Aged Cheddar', 'Sharp, smooth cheddar cheese for slicing and cooking.', 6.99, 31, 'percentage', 12],
            ['Dairy & Eggs', 'Yogurt', 'Greek Yogurt', 'Thick, high-protein plain Greek yogurt.', 5.49, 27, null, 0],
            ['Dairy & Eggs', 'Eggs', 'Free-Range Eggs', 'One dozen free-range eggs from responsibly raised hens.', 5.99, 44, 'flat', 1.00],
            ['Bakery', 'Bread', 'Sourdough Loaf', 'Slow-fermented artisan sourdough with a crisp crust.', 5.99, 19, null, 0],
            ['Bakery', 'Bread', 'Whole Wheat Bread', 'Soft sliced whole wheat bread for everyday meals.', 3.99, 38, null, 0],
            ['Bakery', 'Pastries', 'Butter Croissants', 'Flaky all-butter croissants, baked fresh.', 6.49, 22, 'percentage', 15],
            ['Bakery', 'Pastries', 'Blueberry Muffins', 'Moist muffins bursting with sweet blueberries.', 5.29, 26, null, 0],
            ['Pantry', 'Pasta & Rice', 'Italian Spaghetti', 'Durum wheat spaghetti that holds sauce beautifully.', 2.49, 57, null, 0],
            ['Pantry', 'Pasta & Rice', 'Jasmine Rice', 'Fragrant long-grain jasmine rice.', 8.99, 46, 'percentage', 10],
            ['Pantry', 'Sauces', 'Extra Virgin Olive Oil', 'Cold-pressed olive oil with a balanced finish.', 12.99, 28, null, 0],
            ['Pantry', 'Sauces', 'Tomato Basil Sauce', 'Slow-simmered tomato sauce with basil.', 4.79, 34, 'flat', 0.75],
            ['Pantry', 'Snacks', 'Roasted Almonds', 'Lightly salted whole almonds, dry roasted.', 7.49, 30, null, 0],
            ['Pantry', 'Snacks', 'Dark Chocolate Bar', '72% cocoa dark chocolate with a smooth finish.', 3.29, 39, 'percentage', 5],
            ['Beverages', 'Coffee', 'Colombian Ground Coffee', 'Medium-roast Arabica coffee with caramel notes.', 11.99, 25, null, 0],
            ['Beverages', 'Tea', 'Earl Grey Tea', 'Classic black tea scented with bergamot.', 6.99, 32, null, 0],
            ['Beverages', 'Juice', 'Fresh Orange Juice', 'Chilled orange juice with no added sugar.', 4.99, 36, 'flat', 0.50],
            ['Beverages', 'Sparkling Water', 'Sparkling Mineral Water', 'Naturally carbonated mineral water, six-pack.', 5.49, 50, null, 0],
            ['Home Care', 'Cleaning', 'Plant-Based Dish Soap', 'Tough on grease and gentle on hands.', 4.99, 21, null, 0],
            ['Home Care', 'Laundry', 'Lavender Laundry Detergent', 'Concentrated detergent with a soft lavender scent.', 13.99, 23, 'percentage', 10],
            ['Personal Care', 'Skin Care', 'Gentle Face Cleanser', 'Fragrance-free daily cleanser for sensitive skin.', 9.99, 20, null, 0],
            ['Personal Care', 'Oral Care', 'Bamboo Toothbrush Set', 'Four soft-bristle toothbrushes with bamboo handles.', 7.99, 33, 'flat', 1.00],
            ['Frozen', 'Frozen Meals', 'Vegetable Lasagna', 'Hearty oven-ready lasagna made with garden vegetables.', 8.49, 18, null, 0],
            ['Frozen', 'Frozen Fruit', 'Mixed Berry Blend', 'Frozen strawberries, blueberries, and raspberries.', 7.99, 37, 'percentage', 12],
        ];

        $images = [
            'products/NlYkijrpSw68GjTvuIz4HPSUMqLbgVRT7gf2csjd.webp',
            'products/6k9fbHN5dXuCVkU8LUHaAtVM0wdutCUZgBshorPK.webp',
            'products/yJILXdaR3oHHaJhpGaAuPOFbQyoaMGPGZ4qcrATz.webp',
            'products/VKSJHI4pnHtnG6CXRx8uWJ1EgKE6SJLZZh9avyLy.webp',
            'products/hKGAwjxGnjdgmU99GquruoKmVKTx97fIo5v1RRsw.webp',
            'products/sfjbqoCNu7rgaUxGoCuiCBPCJkb2C6YMW9Doj93T.jpg',
        ];

        DB::transaction(function () use ($catalog, $images) {
            foreach ($catalog as $index => [$categoryName, $subcategoryName, $name, $description, $price, $stock, $discountType, $discountValue]) {
                DB::table('categories')->updateOrInsert(['name' => $categoryName], ['description' => $categoryName . ' essentials']);
                $categoryId = DB::table('categories')->where('name', $categoryName)->value('id');
                DB::table('subcategories')->updateOrInsert(['category_id' => $categoryId, 'name' => $subcategoryName], []);
                $subcategoryId = DB::table('subcategories')->where(['category_id' => $categoryId, 'name' => $subcategoryName])->value('id');

                $sectionName = $index < 10 ? 'Popular Products' : ($index < 20 ? 'Everyday Essentials' : 'New Arrivals');
                DB::table('sections')->updateOrInsert(['name' => $sectionName], ['display_order' => $index < 10 ? 1 : ($index < 20 ? 2 : 3), 'is_active' => 1]);
                $sectionId = DB::table('sections')->where('name', $sectionName)->value('id');

                $sku = 'ECO-DEMO-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT);
                DB::table('products')->updateOrInsert(['sku' => $sku], [
                    'subcategory_id' => $subcategoryId,
                    'name' => $name,
                    'description' => $description,
                    'price' => $price,
                    'is_active' => 1,
                ]);
                $productId = DB::table('products')->where('sku', $sku)->value('id');

                DB::table('inventory')->updateOrInsert(['product_id' => $productId, 'warehouse_location' => 'Main Warehouse'], ['stock_quantity' => $stock]);
                DB::table('product_images')->updateOrInsert(['product_id' => $productId, 'is_primary' => 1], ['image_url' => $images[$index % count($images)]]);
                DB::table('section_products')->updateOrInsert(['section_id' => $sectionId, 'product_id' => $productId], ['display_order' => $index + 1]);

                DB::table('product_discounts')->where('product_id', $productId)->delete();
                if ($discountType) {
                    DB::table('product_discounts')->insert(['product_id' => $productId, 'discount_type' => $discountType, 'discount_value' => $discountValue]);
                }
            }
        });
    }
}
