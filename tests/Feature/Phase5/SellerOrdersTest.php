<?php

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Notifications\OrderStatusUpdated;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
});

test('sellers accept and pack orders, which creates a routed COD shipment', function () {
    Notification::fake();
    $partner = LogisticsPartner::factory()->create();
    $partner->serviceAreas()->create(['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Quezon City']);
    $order = SellerOrder::factory()->create(['shop_id' => $this->shop->id]);

    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/ready")->assertSessionHasErrors('status');
    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/accept")->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Processing);

    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/ready")->assertSessionHasNoErrors();
    $shipment = $order->fresh()->shipment;

    expect($order->fresh()->status)->toBe(SellerOrderStatus::ReadyForPickup)
        ->and($shipment->status)->toBe(ShipmentStatus::PendingPickup)
        ->and($shipment->logistics_partner_id)->toBe($partner->id)
        ->and($shipment->cod_amount)->toBe('359.00')
        ->and($shipment->delivery_fee)->toBe('59.00')
        ->and($shipment->items)->toHaveCount(1)
        ->and($shipment->pickup_address['name'])->toBe($this->shop->name)
        ->and($shipment->dropoff_address['city'])->toBe('Quezon City');

    Notification::assertSentTo($order->order->customer, OrderStatusUpdated::class);
});

test('shipments without partner coverage wait for assignment', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Processing)->create(['shop_id' => $this->shop->id]);

    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/ready");

    expect($order->fresh()->shipment->logistics_partner_id)->toBeNull()
        ->and($order->fresh()->shipment->statusHistories()->first()->note)->toContain('waiting for assignment');
});

test('sellers can cancel before packing, which releases stock', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Processing)->create(['shop_id' => $this->shop->id]);
    $packed = SellerOrder::factory()->status(SellerOrderStatus::ReadyForPickup)->create(['shop_id' => $this->shop->id]);

    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/cancel")->assertSessionHasErrors('reason');
    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/cancel", ['reason' => 'Out of ube'])->assertSessionHasNoErrors();
    $this->actingAs($this->seller)->post("/seller/orders/{$packed->id}/cancel", ['reason' => 'Too late'])->assertSessionHasErrors('status');

    expect($order->fresh()->status)->toBe(SellerOrderStatus::Cancelled)
        ->and($order->fresh()->cancelled_reason)->toBe('Cancelled by shop: Out of ube')
        ->and($order->items()->first()->variation->reserved)->toBe(0);
});

test('sellers only see and act on their own orders', function () {
    $mine = SellerOrder::factory()->create(['shop_id' => $this->shop->id]);
    $theirs = SellerOrder::factory()->create();

    $this->actingAs($this->seller)->get('/seller/orders')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $mine->id)->where('counts.to_ship', 1));
    $this->actingAs($this->seller)->get("/seller/orders/{$mine->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.can.accept', true)->where('order.seller_earning', '285.00'));
    $this->actingAs($this->seller)->get("/seller/orders/{$theirs->id}")->assertNotFound();
    $this->actingAs($this->seller)->post("/seller/orders/{$theirs->id}/accept")->assertNotFound();
});

test('the dashboard reflects the shop numbers', function () {
    SellerOrder::factory()->create(['shop_id' => $this->shop->id]);
    SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['shop_id' => $this->shop->id]);

    $this->actingAs($this->seller)->get('/seller')->assertInertia(fn (Assert $page) => $page
        ->where('stats.orders', 2)
        ->where('stats.pendingOrders', 1)
        ->where('stats.totalSales', '300.00')
        ->has('toShip', 1)
        ->has('chart.labels', 30)
        ->where('sellerCounts.toShip', 1));
});
