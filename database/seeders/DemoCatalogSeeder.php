<?php

namespace Database\Seeders;

use App\Enums\ProductStatus;
use App\Enums\RoleName;
use App\Enums\SellerStatus;
use App\Enums\ShopStatus;
use App\Models\Banner;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Local demo catalog: three neighborhood shops with products, generated images and banners.
 * Idempotent (keyed by slugs/SKUs). Runs only in local/testing via DatabaseSeeder.
 */
class DemoCatalogSeeder extends Seeder
{
    /**
     * shop slug => [owner username, owner name, shop name, shop category slug, description, products]
     * product: [name, category slug, price, stock, description, variations?: [[label, price, stock]]]
     *
     * @var array<string, array<int, mixed>>
     */
    private const SHOPS = [
        'santos-home-bakery' => ['seller', 'Maria Santos', 'Santos Home Bakery', 'bakery', 'Freshly baked pandesal, ensaymada and cakes every morning.', [
            ['Classic Pandesal (10 pcs)', 'food-bakery-baked-goods', '45.00', 80, 'Soft, slightly sweet bread rolls baked at 5 AM daily.'],
            ['Ube Ensaymada', 'food-bakery-baked-goods', '65.00', 40, 'Buttery brioche swirl with ube halaya, topped with cheese and sugar.', [['Single', '65.00', 40], ['Box of 6', '360.00', 12]]],
            ['Chocolate Moist Cake', 'food-bakery-baked-goods', '480.00', 8, 'Rich chocolate cake with fudge frosting. Order a day ahead for large sizes.', [['6 inch', '480.00', 8], ['8 inch', '750.00', 4]]],
            ['Spanish Bread (6 pcs)', 'food-bakery-baked-goods', '60.00', 50, 'Rolled bread filled with sweet buttery crumbs.'],
            ['Homemade Ube Halaya Jar', 'food-bakery-local-delicacies', '220.00', 25, 'Slow-cooked purple yam jam, 350 g jar.'],
            ['Buko Pie', 'food-bakery-local-delicacies', '350.00', 10, 'Young coconut custard pie with flaky crust.'],
        ]],
        'aling-nena-sari-sari' => ['nena', 'Nena Villanueva', 'Aling Nena Sari-sari', 'sari-sari-store', 'Your everyday neighborhood store: canned goods, snacks, drinks and household needs.', [
            ['Jasmine Rice 5 kg', 'groceries-pantry', '320.00', 30, 'Fragrant long-grain rice, sealed 5 kg bag.'],
            ['Instant Pancit Canton Pack (6)', 'groceries-pantry', '96.00', 60, 'Six packs of the classic stir-fry noodles.', [['Original', '96.00', 60], ['Chilimansi', '96.00', 45]]],
            ['Fresh Eggs (1 dozen)', 'groceries-fresh-produce', '108.00', 40, 'Medium-size farm eggs.'],
            ['Calamansi 500 g', 'groceries-fresh-produce', '70.00', 35, 'Freshly picked calamansi for juice and sawsawan.'],
            ['Bottled Water 1 L (6 pack)', 'groceries-beverages', '120.00', 50, 'Purified drinking water.'],
            ['Dishwashing Liquid 500 ml', 'home-living-cleaning', '75.00', 40, 'Lemon-scented, cuts grease fast.'],
        ]],
        'bayani-hardware' => ['bayani', 'Jose Bayani', 'Bayani Hardware', 'hardware', 'Tools, electrical and plumbing supplies for quick home fixes.', [
            ['LED Bulb 9W (Daylight)', 'hardware-electrical', '85.00', 70, 'Energy-saving E27 bulb, 1-year warranty.', [['1 pc', '85.00', 70], ['Pack of 4', '300.00', 20]]],
            ['Extension Cord 3-Gang, 5 m', 'hardware-electrical', '420.00', 15, 'Heavy-duty cord with surge protection.'],
            ['Claw Hammer 16 oz', 'hardware-tools', '280.00', 20, 'Fiberglass handle with anti-slip grip.'],
            ['Screwdriver Set (6 pcs)', 'hardware-tools', '250.00', 18, 'Flat and Phillips heads, magnetic tips.'],
            ['PVC Pipe Sealant Tape (3 rolls)', 'hardware-plumbing', '45.00', 0, 'Teflon tape for leak-free joints. Restocking soon.'],
            ['Faucet Aerator', 'hardware-plumbing', '120.00', 25, 'Saves water and reduces splashing.'],
        ]],
    ];

    private const PALETTE = ['#0f9d76', '#0a654e', '#fb6514', '#f79009', '#2bb38c', '#0b5040'];

    public function run(): void
    {
        $index = 0;

        foreach (self::SHOPS as $slug => [$username, $ownerName, $shopName, $categorySlug, $description, $products]) {
            $shop = $this->shop($slug, $username, $ownerName, $shopName, $categorySlug, $description);

            foreach ($products as $definition) {
                $this->product($shop, $definition, self::PALETTE[$index++ % count(self::PALETTE)]);
            }
        }

        Banner::query()->firstOrCreate(['title' => 'Fresh from your barangay bakery'], [
            'subtitle' => 'Pandesal, ensaymada and cakes from Santos Home Bakery, delivered warm.',
            'link_url' => '/shops/santos-home-bakery',
            'sort_order' => 1,
        ]);
        Banner::query()->firstOrCreate(['title' => 'Fix it today'], [
            'subtitle' => 'Bulbs, tools and plumbing supplies from Bayani Hardware.',
            'link_url' => '/shops/bayani-hardware',
            'sort_order' => 2,
        ]);
    }

    private function shop(string $slug, string $username, string $ownerName, string $shopName, string $categorySlug, string $description): Shop
    {
        $owner = User::query()->firstOrCreate(['username' => $username], [
            'name' => $ownerName,
            'email' => "{$username}@onetap.test",
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $owner->assignRole(RoleName::Customer);
        $owner->assignRole(RoleName::Seller);
        $owner->customerProfile()->firstOrCreate([], ['referral_code' => strtoupper(Str::random(8))]);
        $owner->cart()->firstOrCreate([]);

        $profile = $owner->sellerProfile()->firstOrCreate([], [
            'business_name' => $shopName,
            'business_type' => 'sole_proprietor',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);

        $shop = Shop::query()->firstOrCreate(['slug' => $slug], [
            'seller_profile_id' => $profile->id,
            'name' => $shopName,
            'description' => $description,
            'shop_category_id' => ShopCategory::query()->where('slug', $categorySlug)->value('id'),
            'pickup_street' => fake()->streetAddress(),
            'pickup_barangay' => 'Bagong Pag-asa',
            'pickup_city' => 'Quezon City',
            'pickup_province' => 'Metro Manila',
            'pickup_region' => 'NCR',
            'pickup_phone' => '0918'.random_int(1000000, 9999999),
            'status' => ShopStatus::Active,
        ]);
        $shop->balance()->firstOrCreate([]);
        $shop->autoReplies()->firstOrCreate(['type' => 'greeting'], ['message' => "Hi! Thanks for messaging {$shopName}. We usually reply within the hour."]);

        return $shop;
    }

    /**
     * @param  array<int, mixed>  $definition
     */
    private function product(Shop $shop, array $definition, string $color): void
    {
        [$name, $categorySlug, $price, $stock, $description] = $definition;
        $variations = $definition[5] ?? [['Default', $price, $stock]];
        $slug = Str::slug($shop->slug.' '.$name);

        $product = Product::withTrashed()->firstOrCreate(['slug' => $slug], [
            'shop_id' => $shop->id,
            'product_category_id' => ProductCategory::query()->where('slug', $categorySlug)->value('id'),
            'name' => $name,
            'description' => $description,
            'status' => ProductStatus::Live,
            'base_price' => $price,
            'weight_grams' => 500,
            'published_at' => now()->subDays(random_int(0, 20)),
        ]);

        foreach ($variations as $position => [$label, $variationPrice, $variationStock]) {
            $product->variations()->firstOrCreate(['sku' => strtoupper(Str::limit(Str::slug($slug, ''), 20, '')).'-'.($position + 1)], [
                'label' => $label,
                'price' => $variationPrice,
                'stock' => $variationStock,
                'is_default' => $position === 0,
            ]);
        }

        if (! $product->images()->exists()) {
            $path = "products/demo/{$slug}.svg";
            Storage::disk('public')->put($path, $this->placeholderSvg($name, $color));
            $product->images()->create(['path' => $path, 'is_primary' => true]);
        }
    }

    private function placeholderSvg(string $name, string $color): string
    {
        $words = explode("\n", wordwrap(htmlspecialchars($name, ENT_XML1), 16, "\n", true));
        $lines = '';

        foreach (array_slice($words, 0, 3) as $line => $text) {
            $y = 300 + ($line - (min(count($words), 3) - 1) / 2) * 52;
            $lines .= "<text x=\"300\" y=\"{$y}\" text-anchor=\"middle\" font-family=\"Outfit, Arial, sans-serif\" font-size=\"44\" font-weight=\"700\" fill=\"#ffffff\">{$text}</text>";
        }

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" width="600" height="600" viewBox="0 0 600 600">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="{$color}"/><stop offset="1" stop-color="#04261f"/></linearGradient></defs>
  <rect width="600" height="600" fill="url(#g)"/>
  <circle cx="500" cy="110" r="140" fill="none" stroke="#ffffff" stroke-opacity="0.15" stroke-width="40"/>
  <circle cx="90" cy="520" r="18" fill="#fdb022"/>
  {$lines}
</svg>
SVG;
    }
}
