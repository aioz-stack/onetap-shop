<?php

namespace Database\Seeders;

use App\Models\ProductCategory;
use App\Models\ShopCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const PRODUCT_CATEGORIES = [
        'Groceries' => ['Fresh Produce', 'Pantry', 'Beverages', 'Snacks'],
        'Food & Bakery' => ['Baked Goods', 'Ready-to-Eat', 'Local Delicacies'],
        'Home & Living' => ['Kitchenware', 'Cleaning', 'Decor'],
        'Fashion' => ['Men', 'Women', 'Kids', 'Accessories'],
        'Health & Beauty' => ['Personal Care', 'Skincare', 'Wellness'],
        'Electronics' => ['Mobile Accessories', 'Gadgets', 'Appliances'],
        'Hardware' => ['Tools', 'Electrical', 'Plumbing'],
        'Pets' => ['Pet Food', 'Pet Supplies'],
    ];

    /**
     * @var list<string>
     */
    private const SHOP_CATEGORIES = ['Sari-sari Store', 'Bakery', 'Grocery', 'Boutique', 'Hardware', 'Pharmacy', 'Electronics', 'Home Business'];

    public function run(): void
    {
        $sort = 0;

        foreach (self::PRODUCT_CATEGORIES as $parentName => $children) {
            $parent = ProductCategory::query()->firstOrCreate(
                ['slug' => Str::slug($parentName)],
                ['name' => $parentName, 'sort_order' => $sort++],
            );

            foreach ($children as $index => $childName) {
                ProductCategory::query()->firstOrCreate(
                    ['slug' => Str::slug($parentName.' '.$childName)],
                    ['name' => $childName, 'parent_id' => $parent->id, 'sort_order' => $index],
                );
            }
        }

        foreach (self::SHOP_CATEGORIES as $name) {
            ShopCategory::query()->firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]);
        }
    }
}
