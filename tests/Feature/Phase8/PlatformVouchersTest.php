<?php

use App\Enums\SellerOrderStatus;
use App\Enums\TransactionType;
use App\Enums\VoucherType;
use App\Models\Order;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Voucher;
use App\Services\Finance\EarningsService;
use App\Services\Finance\FinanceReportService;
use App\Services\Ordering\OrderStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->admin = User::factory()->admin()->create();
    $this->shop = Shop::factory()->create();
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'base_price' => '200.00']);
    $this->product->variations()->update(['price' => '200.00']);
    $this->customer = User::factory()->customer()->create();
    $this->address = $this->customer->addresses()->create(['recipient_name' => 'J', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'MM', 'city' => 'Quezon City', 'barangay' => 'X', 'street' => '1', 'is_default' => true]);
});

function platformVoucher(array $overrides = []): Voucher
{
    return Voucher::query()->create(array_merge(['shop_id' => null, 'code' => 'ONETAP50', 'name' => 'Launch', 'type' => VoucherType::Fixed, 'value' => '50.00', 'min_spend' => '0', 'per_user_limit' => 1, 'is_active' => true], $overrides));
}

function fillCart($test, Product $product, int $quantity = 2): void
{
    $test->actingAs($test->customer)->post('/cart/items', ['variation_id' => $product->variations()->first()->id, 'quantity' => $quantity])->assertSessionHasNoErrors();
}

test('admins manage platform vouchers', function () {
    $this->actingAs($this->admin)->get('/admin/vouchers')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Admin/Marketing/Vouchers')->has('vouchers', 0));

    $this->actingAs($this->admin)->post('/admin/vouchers', ['code' => 'freeship', 'name' => 'Free delivery', 'type' => 'free_shipping', 'max_discount' => 80, 'per_user_limit' => 1, 'is_active' => true])->assertSessionHasNoErrors();
    $voucher = Voucher::query()->sole();
    expect($voucher->shop_id)->toBeNull()->and($voucher->code)->toBe('FREESHIP');

    $this->actingAs($this->admin)->put("/admin/vouchers/{$voucher->id}", ['code' => 'FREESHIP', 'name' => 'Free delivery week', 'type' => 'free_shipping', 'max_discount' => 60, 'per_user_limit' => 2, 'is_active' => true])->assertSessionHasNoErrors();
    expect($voucher->fresh()->per_user_limit)->toBe(2);

    // Shop vouchers are not editable from the admin screen, and sellers cannot touch platform vouchers.
    $shopVoucher = $this->shop->vouchers()->create(['code' => 'SHOP10', 'name' => 'x', 'type' => VoucherType::Fixed, 'value' => 10, 'per_user_limit' => 1]);
    $this->actingAs($this->admin)->delete("/admin/vouchers/{$shopVoucher->id}")->assertNotFound();
    $this->actingAs($this->shop->sellerProfile->user)->delete("/seller/vouchers/{$voucher->id}")->assertNotFound();
    $this->actingAs($this->customer)->get('/admin/vouchers')->assertForbidden();

    $this->actingAs($this->admin)->delete("/admin/vouchers/{$voucher->id}")->assertSessionHasNoErrors();
    expect(Voucher::query()->whereNull('shop_id')->count())->toBe(0);
});

test('Onetap funds platform vouchers: the seller is paid in full', function () {
    platformVoucher();
    fillCart($this, $this->product);

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'vouchers' => [$this->shop->id => 'onetap50']])->assertSessionHasNoErrors();

    $sellerOrder = Order::query()->sole()->sellerOrders()->sole();
    expect($sellerOrder->discount)->toBe('50.00')
        ->and($sellerOrder->total)->toBe('409.00')
        ->and($sellerOrder->commission_amount)->toBe('20.00')   // 5% of the full ₱400
        ->and($sellerOrder->seller_earning)->toBe('380.00');    // not reduced by the voucher

    // Completion books the voucher cost against marketplace revenue.
    $status = app(OrderStatusService::class);
    foreach ([SellerOrderStatus::Processing, SellerOrderStatus::ReadyForPickup, SellerOrderStatus::Shipping, SellerOrderStatus::Delivered, SellerOrderStatus::Completed] as $next) {
        $sellerOrder = $status->transition($sellerOrder->fresh(), $next, $this->admin);
    }
    app(EarningsService::class)->postCompletion($sellerOrder);
    app(EarningsService::class)->postCompletion($sellerOrder); // idempotent

    expect(Transaction::query()->where('type', TransactionType::PlatformVoucher)->sole()->amount)->toBe('50.00');

    $summary = app(FinanceReportService::class)->summary();
    expect($summary['commission'])->toBe('20.00')
        ->and($summary['platformVouchers'])->toBe('50.00')
        ->and($summary['netRevenue'])->toBe('-30.00');

    $this->actingAs($this->admin)->get('/admin/finance')->assertInertia(fn (Assert $page) => $page->where('allTime.netRevenue', '-30.00'));
});

test('shop vouchers still come out of the seller earning', function () {
    $this->shop->vouchers()->create(['code' => 'SAVE50', 'name' => 'Save', 'type' => VoucherType::Fixed, 'value' => '50.00', 'min_spend' => '0', 'per_user_limit' => 1, 'is_active' => true]);
    fillCart($this, $this->product);

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'vouchers' => [$this->shop->id => 'SAVE50']])->assertSessionHasNoErrors();

    $sellerOrder = Order::query()->sole()->sellerOrders()->sole();
    expect($sellerOrder->commission_amount)->toBe('17.50')->and($sellerOrder->seller_earning)->toBe('332.50');
});

test('a platform voucher works once per order', function () {
    platformVoucher(['per_user_limit' => 5]);
    $otherShop = Shop::factory()->create();
    $other = Product::factory()->create(['shop_id' => $otherShop->id]);
    fillCart($this, $this->product);
    fillCart($this, $other, 1);

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'vouchers' => [$this->shop->id => 'ONETAP50', $otherShop->id => 'ONETAP50']])
        ->assertSessionHasErrors("vouchers.{$otherShop->id}");

    expect(Order::query()->count())->toBe(0);
});

test('price sorting and filters use promotion prices', function () {
    $cheap = Product::factory()->create(['name' => 'Ube Halaya Jar', 'base_price' => '150.00']);
    $cheap->variations()->update(['price' => '150.00']);
    $discounted = Product::factory()->create(['name' => 'Ube Halaya Tub', 'base_price' => '200.00']);
    $discounted->variations()->update(['price' => '200.00']);
    $promotion = Promotion::query()->create(['shop_id' => $discounted->shop_id, 'name' => 'Half off', 'discount_percent' => 50, 'is_active' => true]);
    $promotion->products()->attach($discounted->id);

    // ₱200 at 50% off (₱100) is cheaper than ₱150.
    $this->get('/search?q=halaya&sort=price_asc')->assertInertia(fn (Assert $page) => $page->where('products.data.0.id', $discounted->id)->where('products.data.1.id', $cheap->id));
    $this->get('/search?q=halaya&sort=price_desc')->assertInertia(fn (Assert $page) => $page->where('products.data.0.id', $cheap->id));
    $this->get('/search?q=halaya&max=120')->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 1)->where('products.data.0.id', $discounted->id));
    $this->get('/search?q=halaya&min=120')->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 1)->where('products.data.0.id', $cheap->id));

    // An expired promotion no longer counts.
    $promotion->update(['ends_at' => now()->subMinute()]);
    $this->get('/search?q=halaya&sort=price_asc')->assertInertia(fn (Assert $page) => $page->where('products.data.0.id', $cheap->id));
});
