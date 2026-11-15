<?php

use App\Enums\InventoryMovementType;
use App\Enums\ProductStatus;
use App\Enums\SellerOrderStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductInventory;
use App\Models\SellerOrder;
use App\Models\Shop;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
    $this->category = ProductCategory::factory()->create();
});

function productPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ube Pandesal',
        'description' => 'Soft rolls',
        'product_category_id' => test()->category->id,
        'weight_grams' => 300,
        'variations' => [
            ['label' => 'Pack of 6', 'price' => '60', 'compare_at_price' => '', 'stock' => 10, 'is_active' => true],
            ['label' => 'Pack of 12', 'price' => '110', 'stock' => 5, 'is_active' => true],
        ],
        'images' => [UploadedFile::fake()->image('a.jpg', 400, 400)],
    ], $overrides);
}

test('every seller page renders', function (string $path, string $component) {
    $this->actingAs($this->seller)->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/seller', 'Seller/Dashboard'],
    ['/seller/products', 'Seller/Products/Index'],
    ['/seller/products/create', 'Seller/Products/Form'],
    ['/seller/inventory', 'Seller/Products/Inventory'],
    ['/seller/orders', 'Seller/Orders/Index'],
    ['/seller/shop', 'Seller/Shop/Edit'],
    ['/seller/chat', 'Seller/CustomerService/Inbox'],
    ['/seller/auto-replies', 'Seller/CustomerService/AutoReplies'],
    ['/seller/reviews', 'Seller/CustomerService/Reviews'],
    ['/seller/vouchers', 'Seller/Marketing/Vouchers'],
    ['/seller/promotions', 'Seller/Marketing/Promotions'],
    ['/seller/referrals', 'Seller/Marketing/Referrals'],
]);

test('creating a product saves variations, stock ledger and photos', function () {
    $this->actingAs($this->seller)->post('/seller/products', productPayload())->assertRedirect('/seller/products');

    $product = Product::with('variations', 'images')->first();
    expect($product->status)->toBe(ProductStatus::Draft)
        ->and($product->base_price)->toBe('60.00')
        ->and($product->variations)->toHaveCount(2)
        ->and($product->variations->firstWhere('label', 'Pack of 6')->stock)->toBe(10)
        ->and($product->variations->firstWhere('label', 'Pack of 6')->is_default)->toBeTrue()
        ->and($product->images->first()->is_primary)->toBeTrue()
        ->and(ProductInventory::where('type', InventoryMovementType::Restock)->count())->toBe(2);

    Storage::disk('public')->assertExists($product->images->first()->path);
});

test('submitting on create sends the product to review', function () {
    $this->actingAs($this->seller)->post('/seller/products', productPayload(['submit' => true]));

    expect(Product::first()->status)->toBe(ProductStatus::UnderReview);
});

test('product validation catches missing photos, bad prices and duplicate variations', function () {
    $this->actingAs($this->seller)->post('/seller/products', productPayload([
        'images' => [],
        'variations' => [
            ['label' => 'Same', 'price' => '100', 'compare_at_price' => '50', 'stock' => 1],
            ['label' => 'Same', 'price' => '0', 'stock' => -1],
        ],
    ]))->assertSessionHasErrors(['images', 'variations.0.compare_at_price', 'variations.1.label', 'variations.1.price', 'variations.1.stock']);

    $this->actingAs($this->seller)->post('/seller/products', productPayload(['images' => [UploadedFile::fake()->create('doc.pdf', 10)]]))->assertSessionHasErrors('images.0');
    expect(Product::count())->toBe(0);
});

test('price and stock edits keep a live product live, content edits send it to review', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id, 'product_category_id' => $this->category->id]);
    $product->images()->create(['path' => 'x.jpg', 'is_primary' => true]);
    $variation = $product->variations()->first();
    $base = ['name' => $product->name, 'description' => $product->description, 'product_category_id' => $this->category->id, 'weight_grams' => 500];

    $this->actingAs($this->seller)->post("/seller/products/{$product->id}", $base + ['variations' => [['id' => $variation->id, 'label' => 'Default', 'price' => '175', 'stock' => 30, 'is_active' => true]]])->assertSessionHasNoErrors();
    expect($product->fresh()->status)->toBe(ProductStatus::Live)->and($variation->fresh()->price)->toBe('175.00')->and($variation->fresh()->stock)->toBe(30);
    expect(ProductInventory::where('product_variation_id', $variation->id)->where('type', InventoryMovementType::Adjustment)->value('quantity'))->toBe(10);

    $this->actingAs($this->seller)->post("/seller/products/{$product->id}", ['name' => 'A Brand New Name'] + $base + ['variations' => [['id' => $variation->id, 'label' => 'Default', 'price' => '175', 'stock' => 30]]]);
    expect($product->fresh()->status)->toBe(ProductStatus::UnderReview);
});

test('stock cannot drop below what open orders reserved', function () {
    $order = SellerOrder::factory()->create(['shop_id' => $this->shop->id]);
    $variation = $order->items()->first()->variation;

    $this->actingAs($this->seller)->post("/seller/inventory/{$variation->id}", ['stock' => 1])->assertSessionHasErrors('stock');
    $this->actingAs($this->seller)->post("/seller/inventory/{$variation->id}", ['stock' => 8, 'note' => 'Recount'])->assertSessionHasNoErrors();

    expect($variation->fresh()->stock)->toBe(8)->and(ProductInventory::where('note', 'Recount')->exists())->toBeTrue();
});

test('removing a variation that was sold deactivates it instead of deleting', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['shop_id' => $this->shop->id]);
    $product = $order->items()->first()->product;
    $product->images()->create(['path' => 'x.jpg', 'is_primary' => true]);
    $sold = $product->variations()->first();

    $this->actingAs($this->seller)->post("/seller/products/{$product->id}", [
        'name' => $product->name, 'description' => $product->description, 'product_category_id' => $product->product_category_id,
        'variations' => [['label' => 'New size', 'price' => '99', 'stock' => 3]],
    ])->assertSessionHasNoErrors();

    expect($sold->fresh())->not->toBeNull()->and($sold->fresh()->is_active)->toBeFalse()->and($product->variations()->where('is_active', true)->count())->toBe(1);
});

test('status actions follow the seller workflow', function () {
    $draft = Product::factory()->status(ProductStatus::Draft)->create(['shop_id' => $this->shop->id]);
    $draft->images()->create(['path' => 'x.jpg', 'is_primary' => true]);
    $live = Product::factory()->create(['shop_id' => $this->shop->id]);
    $live->images()->create(['path' => 'y.jpg', 'is_primary' => true]);

    $this->actingAs($this->seller)->post("/seller/products/{$draft->id}/submit")->assertSessionHasNoErrors();
    expect($draft->fresh()->status)->toBe(ProductStatus::UnderReview);

    $this->actingAs($this->seller)->post("/seller/products/{$live->id}/unpublish");
    expect($live->fresh()->status)->toBe(ProductStatus::Unpublished);
    $this->actingAs($this->seller)->post("/seller/products/{$live->id}/republish");
    expect($live->fresh()->status)->toBe(ProductStatus::Live);

    $this->actingAs($this->seller)->post("/seller/products/{$live->id}/submit")->assertSessionHasErrors('status');
    $this->actingAs($this->seller)->delete("/seller/products/{$live->id}")->assertRedirect('/seller/products');
    expect(Product::find($live->id))->toBeNull()->and(Product::withTrashed()->find($live->id))->not->toBeNull();
});

test('products with an open violation are locked and other shops cannot touch products', function () {
    $flagged = Product::factory()->status(ProductStatus::Violation)->create(['shop_id' => $this->shop->id]);
    $theirs = Product::factory()->create();

    $this->actingAs($this->seller)->post("/seller/products/{$flagged->id}", productPayload())->assertSessionHasErrors('status');
    $this->actingAs($this->seller)->get("/seller/products/{$theirs->id}/edit")->assertNotFound();
    $this->actingAs($this->seller)->delete("/seller/products/{$theirs->id}")->assertNotFound();
});

test('the product list filters by status for this shop only', function () {
    Product::factory()->count(2)->create(['shop_id' => $this->shop->id]);
    Product::factory()->status(ProductStatus::Draft)->create(['shop_id' => $this->shop->id]);
    Product::factory()->create();

    $this->actingAs($this->seller)->get('/seller/products?status=live')->assertInertia(fn (Assert $page) => $page
        ->has('products.data', 2)
        ->where('counts.all', 3)
        ->where('counts.draft', 1));
});
