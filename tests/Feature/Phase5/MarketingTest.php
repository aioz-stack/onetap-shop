<?php

use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Shop;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'base_price' => '200.00']);
    $this->product->variations()->update(['price' => '200.00']);
    $this->customer = User::factory()->customer()->create();
    $this->address = $this->customer->addresses()->create(['recipient_name' => 'J', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'MM', 'city' => 'Quezon City', 'barangay' => 'X', 'street' => '1', 'is_default' => true]);
});

function voucherFor(Shop $shop, array $overrides = []): Voucher
{
    return $shop->vouchers()->create(array_merge(['code' => 'SAVE50', 'name' => 'Save', 'type' => VoucherType::Fixed, 'value' => '50.00', 'min_spend' => '0', 'per_user_limit' => 1, 'is_active' => true], $overrides));
}

function addToCart($test, int $quantity = 2): void
{
    $test->actingAs($test->customer)->post('/cart/items', ['variation_id' => $test->product->variations()->first()->id, 'quantity' => $quantity]);
}

test('sellers create vouchers with validated, unique codes', function () {
    $this->actingAs($this->seller)->post('/seller/vouchers', ['code' => 'bakery 10', 'name' => 'X', 'type' => 'percent', 'value' => 95, 'per_user_limit' => 1])->assertSessionHasErrors(['code', 'value']);
    $this->actingAs($this->seller)->post('/seller/vouchers', ['code' => 'bakery10', 'name' => 'Ten off', 'type' => 'percent', 'value' => 10, 'max_discount' => 30, 'per_user_limit' => 1])->assertSessionHasNoErrors();
    $this->actingAs(Shop::factory()->create()->sellerProfile->user)->post('/seller/vouchers', ['code' => 'BAKERY10', 'name' => 'Dup', 'type' => 'fixed', 'value' => 5, 'per_user_limit' => 1])->assertSessionHasErrors('code');

    expect(Voucher::where('code', 'BAKERY10')->first()->shop_id)->toBe($this->shop->id);
    $this->actingAs($this->seller)->get('/seller/vouchers')->assertInertia(fn (Assert $page) => $page->where('vouchers.0.label', '10% off (up to ₱30.00)')->where('vouchers.0.state', 'active'));
});

test('promotions lower the price on the storefront, in the cart and at checkout', function () {
    $this->actingAs($this->seller)->post('/seller/promotions', ['name' => 'Payday', 'discount_percent' => 25, 'product_ids' => [$this->product->id], 'is_active' => true])->assertSessionHasNoErrors();

    $this->get("/products/{$this->product->slug}")->assertInertia(fn (Assert $page) => $page
        ->where('product.variations.0.price', '150.00')
        ->where('product.variations.0.compare_at_price', '200.00')
        ->where('product.discount_percent', 25));
    $this->get('/search')->assertInertia(fn (Assert $page) => $page->where('products.data.0.price', '150.00')->where('products.data.0.discount_percent', 25));

    addToCart($this);
    $this->actingAs($this->customer)->get('/cart')->assertInertia(fn (Assert $page) => $page->where('groups.0.items.0.unit_price', '150.00')->where('summary.items_subtotal', '300.00'));

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod']);
    $item = Order::first()->sellerOrders()->first()->items()->first();
    expect($item->unit_price)->toBe('150.00')->and($item->line_total)->toBe('300.00');
});

test('promotions from another shop are rejected and expired ones do not apply', function () {
    $foreign = Product::factory()->create();
    $this->actingAs($this->seller)->post('/seller/promotions', ['name' => 'Steal', 'discount_percent' => 50, 'product_ids' => [$foreign->id]])->assertSessionHasErrors('product_ids.0');

    $promotion = Promotion::create(['shop_id' => $this->shop->id, 'name' => 'Old', 'discount_percent' => 50, 'starts_at' => now()->subDays(5), 'ends_at' => now()->subDay(), 'is_active' => true]);
    $promotion->products()->attach($this->product->id);

    $this->get("/products/{$this->product->slug}")->assertInertia(fn (Assert $page) => $page->where('product.variations.0.price', '200.00')->where('product.discount_percent', null));
});

test('a fixed voucher discounts the seller order, reduces commission and is redeemed once', function () {
    voucherFor($this->shop);
    addToCart($this);

    $this->actingAs($this->customer)->get('/checkout?vouchers['.$this->shop->id.']=save50')->assertInertia(fn (Assert $page) => $page
        ->where('groups.0.voucher.discount', '50.00')
        ->where('summary.discount_total', '50.00')
        ->where('summary.grand_total', '409.00'));

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'vouchers' => [$this->shop->id => 'SAVE50']])->assertSessionHasNoErrors();

    $sellerOrder = Order::first()->sellerOrders()->first();
    expect($sellerOrder->discount)->toBe('50.00')
        ->and($sellerOrder->total)->toBe('409.00')
        ->and($sellerOrder->commission_amount)->toBe('17.50')
        ->and($sellerOrder->seller_earning)->toBe('332.50')
        ->and(Order::first()->discount_total)->toBe('50.00')
        ->and(Voucher::first()->used_count)->toBe(1)
        ->and(VoucherRedemption::count())->toBe(1);

    addToCart($this);
    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'vouchers' => [$this->shop->id => 'SAVE50']])->assertSessionHasErrors("vouchers.{$this->shop->id}");
});

test('percent vouchers respect the cap and free-shipping vouchers cover delivery', function () {
    voucherFor($this->shop, ['code' => 'PCT', 'type' => VoucherType::Percent, 'value' => '20', 'max_discount' => '60.00']);
    voucherFor($this->shop, ['code' => 'SHIP', 'type' => VoucherType::FreeShipping, 'value' => '0']);
    addToCart($this, 3);

    $this->actingAs($this->customer)->get('/checkout?vouchers['.$this->shop->id.']=PCT')->assertInertia(fn (Assert $page) => $page->where('groups.0.voucher.discount', '60.00'));

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'vouchers' => [$this->shop->id => 'SHIP']]);
    $sellerOrder = Order::first()->sellerOrders()->first();

    expect($sellerOrder->discount)->toBe('59.00')
        ->and($sellerOrder->total)->toBe('600.00')
        ->and($sellerOrder->commission_amount)->toBe('30.00')
        ->and($sellerOrder->seller_earning)->toBe('511.00');
});

test('voucher rules: minimum spend, other shops, schedule and usage limits', function () {
    voucherFor($this->shop, ['code' => 'MIN', 'min_spend' => '1000']);
    voucherFor(Shop::factory()->create(), ['code' => 'OTHER']);
    voucherFor($this->shop, ['code' => 'LATER', 'starts_at' => now()->addDay()]);
    voucherFor($this->shop, ['code' => 'GONE', 'usage_limit' => 1, 'used_count' => 1]);
    addToCart($this);

    foreach (['MIN' => 'minimum spend', 'OTHER' => 'not a valid voucher', 'LATER' => 'not active', 'GONE' => 'fully claimed', 'NOPE' => 'not a valid voucher'] as $code => $message) {
        $this->actingAs($this->customer)->get('/checkout?vouchers['.$this->shop->id.']='.$code)
            ->assertInertia(fn (Assert $page) => $page->where('groups.0.voucher', null)->where('groups.0.voucher_error', fn ($error) => str_contains($error, $message)));
    }
});

test('the api applies vouchers at checkout', function () {
    voucherFor($this->shop);
    addToCart($this);
    $token = $this->customer->createToken('phone')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/orders', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'vouchers' => [$this->shop->id => 'save50']])
        ->assertCreated()
        ->assertJsonPath('data.grand_total', '409.00');
});
