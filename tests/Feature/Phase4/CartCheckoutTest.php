<?php

use App\Enums\ProductStatus;
use App\Enums\SellerOrderStatus;
use App\Models\LogisticsPartner;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Notifications\OrderPlaced;
use App\Services\Administration\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->customer = User::factory()->customer()->create();
    $this->address = $this->customer->addresses()->create([
        'recipient_name' => 'Juan', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'Metro Manila',
        'city' => 'Quezon City', 'barangay' => 'Bagong Pag-asa', 'street' => '12 Mabini', 'is_default' => true,
    ]);
});

function variationOf(Product $product)
{
    return $product->variations()->first();
}

test('guests are sent to login and staff cannot use the cart', function () {
    $this->get('/cart')->assertRedirect('/login');
    $this->actingAs(User::factory()->admin()->create())->get('/cart')->assertForbidden();
});

test('adding to the cart merges quantities and respects stock', function () {
    $variation = variationOf(Product::factory()->create());

    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 5])->assertSessionHasNoErrors();
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 5])->assertSessionHasNoErrors();
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 11])->assertSessionHasErrors('quantity');

    expect($this->customer->cart->items()->first()->quantity)->toBe(10);
});

test('unavailable, vacation and own-shop items cannot be added', function () {
    $draft = variationOf(Product::factory()->status(ProductStatus::Draft)->create());
    $vacation = variationOf(Product::factory()->create(['shop_id' => Shop::factory()->create(['vacation_mode' => true])->id]));
    $sellerShop = Shop::factory()->create();
    $seller = $sellerShop->sellerProfile->user;
    $own = variationOf(Product::factory()->create(['shop_id' => $sellerShop->id]));

    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $draft->id, 'quantity' => 1])->assertSessionHasErrors('variation_id');
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $vacation->id, 'quantity' => 1])->assertSessionHasErrors('variation_id');
    $this->actingAs($seller)->post('/cart/items', ['variation_id' => $own->id, 'quantity' => 1])->assertSessionHasErrors(['variation_id' => 'You cannot buy from your own shop.']);
});

test('the cart groups items by shop and quotes delivery from partner coverage', function () {
    $partner = LogisticsPartner::factory()->create(['base_delivery_fee' => '70.00']);
    $partner->serviceAreas()->create(['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Quezon City', 'barangay' => 'Bagong Pag-asa', 'delivery_fee' => '45.00']);
    $first = variationOf(Product::factory()->create());
    $second = variationOf(Product::factory()->create());

    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $first->id, 'quantity' => 2]);
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $second->id, 'quantity' => 1]);

    $this->actingAs($this->customer)->get('/cart')->assertInertia(fn (Assert $page) => $page
        ->component('Customer/Cart')
        ->has('groups', 2)
        ->where('groups.0.shipping.fee', '45.00')
        ->where('groups.0.shipping.covered', true)
        ->where('summary.items_subtotal', '450.00')
        ->where('summary.shipping_total', '90.00')
        ->where('summary.grand_total', '540.00')
        ->where('counts.cart', 3));
});

test('uncovered areas use the default fee and big baskets ship free', function () {
    app(SettingsService::class)->set('shipping', 'free_shipping_threshold', '500.00');
    $variation = variationOf(Product::factory()->create());

    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 2]);
    $this->actingAs($this->customer)->get('/cart')->assertInertia(fn (Assert $page) => $page->where('groups.0.shipping.fee', '59.00')->where('groups.0.shipping.covered', false));

    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 2]);
    $this->actingAs($this->customer)->get('/cart')->assertInertia(fn (Assert $page) => $page->where('groups.0.shipping.fee', '0.00')->where('groups.0.shipping.free', true));
});

test('checkout splits the order per shop, reserves stock, fixes commission and clears the cart', function () {
    Notification::fake();
    $a = Product::factory()->create();
    $b = Product::factory()->create();
    $unselected = variationOf(Product::factory()->create());

    foreach ([[variationOf($a), 2], [variationOf($b), 3], [$unselected, 1]] as [$variation, $quantity]) {
        $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => $quantity]);
    }
    $this->customer->cart->items()->where('product_variation_id', $unselected->id)->update(['is_selected' => false]);

    $response = $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod', 'notes' => 'Gate 2']);

    $response->assertSessionHasNoErrors();
    $order = Order::with('sellerOrders.items', 'address', 'payments')->first();
    $response->assertRedirect("/checkout/success/{$order->order_number}");

    expect($order->sellerOrders)->toHaveCount(2)
        ->and($order->items_subtotal)->toBe('750.00')
        ->and($order->shipping_total)->toBe('118.00')
        ->and($order->grand_total)->toBe('868.00')
        ->and($order->notes)->toBe('Gate 2')
        ->and($order->address->street)->toBe('12 Mabini')
        ->and($order->payments->first()->amount)->toBe('868.00');

    $sellerOrder = $order->sellerOrders->firstWhere('shop_id', $b->shop_id);
    expect($sellerOrder->status)->toBe(SellerOrderStatus::Pending)
        ->and($sellerOrder->items_subtotal)->toBe('450.00')
        ->and($sellerOrder->commission_rate)->toBe('5.00')
        ->and($sellerOrder->commission_amount)->toBe('22.50')
        ->and($sellerOrder->seller_earning)->toBe('427.50')
        ->and(variationOf($b)->reserved)->toBe(3)
        ->and($sellerOrder->statusHistories()->count())->toBe(1);

    expect($this->customer->cart->items()->pluck('product_variation_id')->all())->toBe([$unselected->id]);

    Notification::assertSentTo($this->customer, OrderPlaced::class);
    Notification::assertSentTo($b->shop->sellerProfile->user, NewSellerOrder::class);

    $this->actingAs($this->customer)->get("/checkout/success/{$order->order_number}")->assertOk();
    $this->actingAs(User::factory()->customer()->create())->get("/checkout/success/{$order->order_number}")->assertNotFound();
});

test('checkout fails cleanly when stock ran out', function () {
    $variation = variationOf(Product::factory()->create());
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 5]);
    $variation->update(['reserved' => 18]);

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('cart');

    expect(Order::count())->toBe(0)->and($variation->fresh()->reserved)->toBe(18);
});

test('checkout enforces cod rules and address ownership', function () {
    $variation = variationOf(Product::factory()->create());
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 2]);
    $strangerAddress = User::factory()->customer()->create()->addresses()->create($this->address->only(['recipient_name', 'phone', 'region', 'province', 'city', 'barangay', 'street']));

    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $strangerAddress->id, 'payment_method' => 'cod'])->assertSessionHasErrors('address_id');
    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'gcash'])->assertSessionHasErrors('payment_method');

    app(SettingsService::class)->set('payment', 'cod_max_amount', '100.00');
    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('payment_method');

    expect(Order::count())->toBe(0)->and($variation->fresh()->reserved)->toBe(0);
});

test('the empty cart cannot be checked out', function () {
    $this->actingAs($this->customer)->get('/checkout')->assertRedirect('/cart');
    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod'])->assertSessionHasErrors('cart');
});

test('buy now checks out only that item', function () {
    $other = variationOf(Product::factory()->create());
    $wanted = variationOf(Product::factory()->create());
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $other->id, 'quantity' => 1]);

    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $wanted->id, 'quantity' => 1, 'buy_now' => true])->assertRedirect('/checkout');

    expect($this->customer->cart->items()->where('is_selected', true)->pluck('product_variation_id')->all())->toBe([$wanted->id]);
});

test('a mobile client can shop and check out through the api', function () {
    $variation = variationOf(Product::factory()->create());
    $token = $this->customer->createToken('phone')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/cart/items', ['variation_id' => $variation->id, 'quantity' => 2])
        ->assertCreated()
        ->assertJsonPath('data.summary.items_subtotal', '300.00');

    $this->withToken($token)->postJson('/api/v1/orders', ['address_id' => $this->address->id, 'payment_method' => 'cod'])
        ->assertCreated()
        ->assertJsonPath('data.grand_total', '359.00')
        ->assertJsonPath('data.seller_orders.0.status', 'pending');

    $orderId = SellerOrder::first()->id;
    $this->withToken($token)->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.id', $orderId);
    $this->withToken($token)->getJson("/api/v1/orders/{$orderId}")->assertOk()->assertJsonPath('data.can.cancel', true);
    $this->withToken($token)->postJson("/api/v1/orders/{$orderId}/cancel", ['reason' => 'Changed my mind'])->assertOk()->assertJsonPath('data.status', 'cancelled');
    expect($variation->fresh()->reserved)->toBe(0);

    app('auth')->forgetGuards(); // Sanctum caches the user between requests inside one test.
    $this->withToken(User::factory()->customer()->create()->createToken('x')->plainTextToken)->getJson("/api/v1/orders/{$orderId}")->assertNotFound();
});
