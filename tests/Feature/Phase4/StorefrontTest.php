<?php

use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Shop;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('the home page shows live products in its sections', function () {
    $visible = Product::factory()->create(['sold_count' => 5, 'rating_avg' => 4.5, 'rating_count' => 2]);
    Product::factory()->status(ProductStatus::UnderReview)->create();
    Product::factory()->create(['shop_id' => Shop::factory()->create(['status' => ShopStatus::Suspended])->id]);

    $this->get('/')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Customer/Home')
        ->has('newArrivals', 1)
        ->where('newArrivals.0.id', $visible->id)
        ->where('bestSellers.0.id', $visible->id)
        ->where('featured.0.id', $visible->id)
        ->has('popularShops'));
});

test('search filters by text, category tree, price and stock, and sorts', function () {
    $parent = ProductCategory::factory()->create(['slug' => 'groceries']);
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    $cheap = Product::factory()->create(['name' => 'Ube Jam Small', 'product_category_id' => $child->id, 'base_price' => '50.00']);
    $cheap->variations()->update(['price' => '50.00']);
    $pricey = Product::factory()->create(['name' => 'Ube Jam Large', 'base_price' => '300.00']);
    $pricey->variations()->update(['price' => '300.00', 'stock' => 0]);
    Product::factory()->create(['name' => 'Hammer']);

    $this->get('/search?q=ube&sort=price_desc')->assertInertia(fn (Assert $page) => $page
        ->where('products.meta.total', 2)
        ->where('products.data.0.id', $pricey->id)
        ->where('products.data.0.in_stock', false));

    $this->get('/search?category=groceries')->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 1)->where('products.data.0.id', $cheap->id));
    $this->get('/search?q=ube&max=100')->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 1));
    $this->get('/search?q=ube&in_stock=1')->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 1)->where('products.data.0.price', '50.00'));
});

test('product pages show only visible products with variations and stock', function () {
    $product = Product::factory()->create();
    $product->variations()->update(['stock' => 10, 'reserved' => 3]);
    $hidden = Product::factory()->status(ProductStatus::Draft)->create();

    $this->get("/products/{$product->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Customer/Product')
        ->where('product.id', $product->id)
        ->where('product.variations.0.available', 7)
        ->where('product.shop.id', $product->shop_id));

    $this->get("/products/{$hidden->slug}")->assertNotFound();
});

test('shop pages list the shop products and hide inactive shops', function () {
    $shop = Shop::factory()->create();
    Product::factory()->count(2)->create(['shop_id' => $shop->id]);
    $closed = Shop::factory()->create(['status' => ShopStatus::Suspended]);

    $this->get("/shops/{$shop->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('shop.id', $shop->id)
        ->where('shop.products_count', 2)
        ->where('products.meta.total', 2));

    $this->get("/shops/{$closed->slug}")->assertNotFound();
});

test('the public api exposes products and shops', function () {
    $product = Product::factory()->create(['name' => 'Api Pandesal']);

    $this->getJson('/api/v1/products?q=pandesal')
        ->assertOk()
        ->assertJsonPath('data.0.slug', $product->slug)
        ->assertJsonPath('meta.total', 1);

    $this->getJson("/api/v1/products/{$product->slug}")
        ->assertOk()
        ->assertJsonPath('data.variations.0.price', '150.00')
        ->assertJsonPath('data.shop.slug', $product->shop->slug);

    $this->getJson('/api/v1/shops')->assertOk()->assertJsonPath('data.0.slug', $product->shop->slug);
    $this->getJson('/api/v1/products/does-not-exist')->assertNotFound();
});
