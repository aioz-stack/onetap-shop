<?php

use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\TransactionType;
use App\Models\Commission;
use App\Models\SellerOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\OrderStatusUpdated;
use App\Services\Ordering\OrderStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
});

function ownerOf(SellerOrder $order): User
{
    return $order->order->customer;
}

test('customers see only their own orders, grouped by tab', function () {
    $order = SellerOrder::factory()->create();
    SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['order_id' => $order->order_id]);
    SellerOrder::factory()->create();

    $this->actingAs(ownerOf($order))->get('/account/orders')->assertInertia(fn (Assert $page) => $page
        ->component('Customer/Account/Orders')
        ->where('counts.all', 2)
        ->where('counts.to_ship', 1)
        ->where('counts.completed', 1));

    $this->actingAs(ownerOf($order))->get('/account/orders?tab=completed')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    $this->actingAs(ownerOf($order))->get("/account/orders/{$order->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.can.cancel', true));
    $this->actingAs(User::factory()->customer()->create())->get("/account/orders/{$order->id}")->assertNotFound();
});

test('customers can cancel before the shop starts preparing, and stock is released', function () {
    $order = SellerOrder::factory()->create();
    $preparing = SellerOrder::factory()->status(SellerOrderStatus::Processing)->create();

    $this->actingAs(ownerOf($order))->post("/account/orders/{$order->id}/cancel", ['reason' => 'Wrong size'])->assertRedirect();
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Cancelled)
        ->and($order->items()->first()->variation->reserved)->toBe(0);

    $this->actingAs(ownerOf($preparing))->post("/account/orders/{$preparing->id}/cancel", ['reason' => 'Too slow'])->assertSessionHasErrors('status');
    $this->actingAs(User::factory()->customer()->create())->post("/account/orders/{$preparing->id}/cancel", ['reason' => 'x'])->assertNotFound();
});

test('confirming receipt completes the order and posts commission and pending earnings', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();

    $this->actingAs(ownerOf($order))->post("/account/orders/{$order->id}/confirm")->assertRedirect();

    $balance = $order->shop->balance()->first();
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Completed)
        ->and(Commission::where('seller_order_id', $order->id)->value('amount'))->toBe('15.00')
        ->and(Transaction::where('type', TransactionType::Commission)->value('amount'))->toBe('15.00')
        ->and(Transaction::where('type', TransactionType::SaleEarning)->value('amount'))->toBe('285.00')
        ->and($balance->pending_balance)->toBe('285.00')
        ->and($balance->total_earned)->toBe('285.00');

    $this->actingAs(ownerOf($order))->post("/account/orders/{$order->id}/confirm")->assertSessionHasErrors('status');
    expect(Commission::count())->toBe(1);
});

test('earnings move to available after the hold period', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $this->actingAs(ownerOf($order))->post("/account/orders/{$order->id}/confirm");

    $this->artisan('onetap:release-earnings')->expectsOutput('Released earnings for 0 order(s).');

    $this->travel(4)->days();
    $this->artisan('onetap:release-earnings')->expectsOutput('Released earnings for 1 order(s).');
    $this->artisan('onetap:release-earnings')->expectsOutput('Released earnings for 0 order(s).');

    $balance = $order->shop->balance()->first();
    expect($balance->pending_balance)->toBe('0.00')->and($balance->available_balance)->toBe('285.00')->and($order->fresh()->earnings_released_at)->not->toBeNull();
});

test('customers can request a return once, and it reaches the admin queue', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $customer = ownerOf($order);

    $this->actingAs($customer)->post("/account/orders/{$order->id}/refund", ['reason' => 'Damaged', 'amount' => '999'])->assertSessionHasErrors('amount');
    $this->actingAs($customer)->post("/account/orders/{$order->id}/refund", ['reason' => 'Damaged', 'details' => 'Crushed box'])->assertRedirect();
    $this->actingAs($customer)->post("/account/orders/{$order->id}/refund", ['reason' => 'Again'])->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(SellerOrderStatus::ReturnRequested)
        ->and($order->refunds()->first()->amount)->toBe('300.00')
        ->and($order->refunds()->first()->status)->toBe(RefundStatus::Requested);

    $this->actingAs($customer)->get('/account/refunds')->assertInertia(fn (Assert $page) => $page->has('refunds.data', 1));
    $this->actingAs(User::factory()->admin()->create())->get('/admin/refunds')->assertInertia(fn (Assert $page) => $page->has('refunds.data', 1));
});

test('reviews require a completed order and update product and shop ratings', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $customer = ownerOf($order);
    $item = $order->items()->first();

    $this->actingAs($customer)->post("/account/items/{$item->id}/review", ['rating' => 5])->assertSessionHasErrors('rating');

    $this->actingAs($customer)->post("/account/orders/{$order->id}/confirm");
    $this->actingAs($customer)->post("/account/items/{$item->id}/review", ['rating' => 4, 'comment' => 'Very fresh'])->assertRedirect();
    $this->actingAs($customer)->post("/account/items/{$item->id}/review", ['rating' => 1])->assertSessionHasErrors('rating');
    $this->actingAs(User::factory()->customer()->create())->post("/account/items/{$item->id}/review", ['rating' => 1])->assertNotFound();

    $product = $item->product->fresh();
    expect($product->rating_count)->toBe(1)->and((float) $product->rating_avg)->toBe(4.0)->and((float) $order->shop->fresh()->rating_avg)->toBe(4.0);

    $this->get("/products/{$product->slug}")->assertInertia(fn (Assert $page) => $page->where('reviews.meta.total', 1)->where('reviews.data.0.comment', 'Very fresh'));
    $this->actingAs($customer)->get('/account/reviews')->assertInertia(fn (Assert $page) => $page->has('pending', 0)->has('written', 1));
});

test('customers are notified when someone else changes their order status', function () {
    Notification::fake();
    $order = SellerOrder::factory()->create();

    app(OrderStatusService::class)->transition($order, SellerOrderStatus::Processing, User::factory()->admin()->create());

    Notification::assertSentTo(ownerOf($order), OrderStatusUpdated::class);
});

test('notifications can be listed, opened and marked read', function () {
    $order = SellerOrder::factory()->create();
    $customer = ownerOf($order);
    app(OrderStatusService::class)->transition($order, SellerOrderStatus::Processing, User::factory()->admin()->create());

    $notification = $customer->notifications()->first();
    $this->actingAs($customer)->get('/account/notifications')->assertInertia(fn (Assert $page) => $page->has('notifications.data', 1)->where('counts.notifications', 1));
    $this->actingAs($customer)->get("/account/notifications/{$notification->id}")->assertRedirect("/account/orders/{$order->id}");
    expect($customer->unreadNotifications()->count())->toBe(0);
});
