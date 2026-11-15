<?php

use App\Enums\ProductStatus;
use App\Enums\RoleName;
use App\Enums\SellerOrderStatus;
use App\Enums\SellerStatus;
use App\Enums\ShopStatus;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

test('approving a seller grants the role and opens a shop with a balance', function () {
    $profile = SellerProfile::factory()->create(['business_name' => 'Aling Nena Store']);

    $this->actingAs($this->admin)->post("/admin/sellers/{$profile->id}/approve")->assertRedirect();

    $profile->refresh();
    expect($profile->status)->toBe(SellerStatus::Approved)
        ->and($profile->approved_by)->toBe($this->admin->id)
        ->and($profile->user->fresh()->hasRole(RoleName::Seller))->toBeTrue()
        ->and($profile->shop->slug)->toBe('aling-nena-store')
        ->and($profile->shop->status)->toBe(ShopStatus::Active)
        ->and($profile->shop->balance)->not->toBeNull();
});

test('rejecting a seller requires a reason and approval cannot repeat', function () {
    $profile = SellerProfile::factory()->create();

    $this->actingAs($this->admin)->post("/admin/sellers/{$profile->id}/reject")->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->post("/admin/sellers/{$profile->id}/reject", ['reason' => 'Missing permit'])->assertRedirect();
    expect($profile->fresh()->status)->toBe(SellerStatus::Rejected)->and($profile->fresh()->rejection_reason)->toBe('Missing permit');

    $approved = SellerProfile::factory()->approved()->create();
    $this->actingAs($this->admin)->post("/admin/sellers/{$approved->id}/approve")->assertSessionHasErrors('status');
});

test('suspending a seller hides the shop and live products, reinstating restores the shop', function () {
    $shop = Shop::factory()->create();
    $live = Product::factory()->create(['shop_id' => $shop->id]);
    $draft = Product::factory()->status(ProductStatus::Draft)->create(['shop_id' => $shop->id]);
    $profile = $shop->sellerProfile;

    $this->actingAs($this->admin)->post("/admin/sellers/{$profile->id}/suspend", ['reason' => 'Repeated violations'])->assertRedirect();

    expect($shop->fresh()->status)->toBe(ShopStatus::Suspended)
        ->and($live->fresh()->status)->toBe(ProductStatus::Unpublished)
        ->and($draft->fresh()->status)->toBe(ProductStatus::Draft)
        ->and($profile->user->fresh()->hasRole(RoleName::Seller))->toBeFalse();

    $this->actingAs($this->admin)->post("/admin/sellers/{$profile->id}/reinstate")->assertRedirect();
    expect($shop->fresh()->status)->toBe(ShopStatus::Active)->and($profile->user->fresh()->hasRole(RoleName::Seller))->toBeTrue();
});

test('the seller page reports performance', function () {
    $shop = Shop::factory()->create();
    SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['shop_id' => $shop->id]);
    SellerOrder::factory()->status(SellerOrderStatus::Cancelled)->create(['shop_id' => $shop->id]);

    $this->actingAs($this->admin)->get("/admin/sellers/{$shop->seller_profile_id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('performance.orders', 2)
            ->where('performance.completedOrders', 1)
            ->where('performance.cancellationRate', 50)
            ->where('performance.gmv', '359.00'));
});

test('products under review can be approved and published', function () {
    $product = Product::factory()->status(ProductStatus::UnderReview)->create(['published_at' => null]);

    $this->actingAs($this->admin)->post("/admin/products/{$product->id}/approve")->assertRedirect();

    expect($product->fresh()->status)->toBe(ProductStatus::Live)->and($product->fresh()->published_at)->not->toBeNull();
});

test('a product without active variations cannot be approved', function () {
    $product = Product::factory()->status(ProductStatus::UnderReview)->create();
    $product->variations()->update(['is_active' => false]);

    $this->actingAs($this->admin)->post("/admin/products/{$product->id}/approve")->assertSessionHasErrors('status');
    expect($product->fresh()->status)->toBe(ProductStatus::UnderReview);
});

test('rejecting, unpublishing, flagging and resolving follow the allowed statuses', function () {
    $product = Product::factory()->status(ProductStatus::UnderReview)->create();

    $this->actingAs($this->admin)->post("/admin/products/{$product->id}/reject", ['reason' => 'Blurry photos'])->assertRedirect();
    expect($product->fresh()->status)->toBe(ProductStatus::Rejected)->and($product->fresh()->rejection_reason)->toBe('Blurry photos');

    $this->actingAs($this->admin)->post("/admin/products/{$product->id}/unpublish")->assertSessionHasErrors('status');

    $live = Product::factory()->create();
    $this->actingAs($this->admin)->post("/admin/products/{$live->id}/flag", ['reason' => 'Counterfeit'])->assertRedirect();
    expect($live->fresh()->status)->toBe(ProductStatus::Violation)->and($live->violations()->where('status', 'open')->count())->toBe(1);

    $this->actingAs($this->admin)->post("/admin/products/{$live->id}/resolve")->assertRedirect();
    expect($live->fresh()->status)->toBe(ProductStatus::UnderReview)->and($live->violations()->where('status', 'resolved')->count())->toBe(1);
});

test('products can be filtered by status, category tree and search', function () {
    $parent = ProductCategory::factory()->create();
    $child = ProductCategory::factory()->create(['parent_id' => $parent->id]);
    Product::factory()->create(['product_category_id' => $child->id, 'name' => 'Ube Halaya Jar']);
    Product::factory()->status(ProductStatus::UnderReview)->create();

    $this->actingAs($this->admin)->get("/admin/products?category={$parent->id}")
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('products.data.0.name', 'Ube Halaya Jar'));

    $this->actingAs($this->admin)->get('/admin/products?status=under_review')
        ->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->where('counts.under_review', 1)->where('counts.all', 2));

    $this->actingAs($this->admin)->get('/admin/products?search=halaya')->assertInertia(fn (Assert $page) => $page->has('products.data', 1));
});

test('categories can be created, nested, updated and only deleted when empty', function () {
    $this->actingAs($this->admin)->post('/admin/categories', ['name' => 'Snacks', 'is_active' => true])->assertRedirect();
    $parent = ProductCategory::where('name', 'Snacks')->first();

    $this->actingAs($this->admin)->post('/admin/categories', ['name' => 'Chips', 'parent_id' => $parent->id])->assertRedirect();
    $child = ProductCategory::where('name', 'Chips')->first();
    expect($child->slug)->toBe('snacks-chips')->and($child->sort_order)->toBe(0);

    $this->actingAs($this->admin)->post('/admin/categories', ['name' => 'Too deep', 'parent_id' => $child->id])->assertSessionHasErrors('parent_id');

    $this->actingAs($this->admin)->put("/admin/categories/{$parent->id}", ['name' => 'Snacks & Chips', 'is_active' => false])->assertRedirect();
    expect($parent->fresh()->name)->toBe('Snacks & Chips')->and($parent->fresh()->is_active)->toBeFalse();

    Product::factory()->create(['product_category_id' => $child->id]);
    $this->actingAs($this->admin)->delete("/admin/categories/{$child->id}")->assertSessionHas('error');
    $this->actingAs($this->admin)->delete("/admin/categories/{$parent->id}")->assertSessionHas('error');
    expect(ProductCategory::count())->toBe(2);

    $empty = ProductCategory::factory()->create();
    $this->actingAs($this->admin)->delete("/admin/categories/{$empty->id}")->assertSessionHas('success');
    expect(ProductCategory::find($empty->id))->toBeNull();
});
