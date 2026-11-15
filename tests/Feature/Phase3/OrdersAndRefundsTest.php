<?php

use App\Enums\InventoryMovementType;
use App\Enums\RefundStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Models\ProductInventory;
use App\Models\Refund;
use App\Models\SellerOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Ordering\InventoryService;
use App\Services\Ordering\OrderStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

test('order tabs group statuses and search finds by reference', function () {
    $pending = SellerOrder::factory()->create();
    SellerOrder::factory()->status(SellerOrderStatus::Processing)->create();
    SellerOrder::factory()->status(SellerOrderStatus::Shipping)->create();
    SellerOrder::factory()->status(SellerOrderStatus::ReturnRequested)->create();

    $this->actingAs($this->admin)->get('/admin/orders?tab=to_ship')
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 2)
            ->where('counts.all', 4)
            ->where('counts.to_ship', 2)
            ->where('counts.shipping', 1)
            ->where('counts.return_refund', 1));

    $this->actingAs($this->admin)->get('/admin/orders?search='.$pending->reference)
        ->assertInertia(fn (Assert $page) => $page->has('orders.data', 1)->where('orders.data.0.id', $pending->id));
});

test('cancelling an order releases reserved stock and records history', function () {
    $order = SellerOrder::factory()->create();
    $variation = $order->items()->first()->variation;
    expect($variation->fresh()->reserved)->toBe(2);

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/cancel", ['reason' => 'Customer request'])->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(SellerOrderStatus::Cancelled)
        ->and($order->cancelled_reason)->toBe('Customer request')
        ->and($order->cancelled_by)->toBe($this->admin->id)
        ->and($order->cancelled_at)->not->toBeNull()
        ->and($variation->fresh()->reserved)->toBe(0)
        ->and($variation->fresh()->stock)->toBe(20)
        ->and($order->statusHistories()->where('to_status', 'cancelled')->exists())->toBeTrue()
        ->and(ProductInventory::where('product_variation_id', $variation->id)->where('type', InventoryMovementType::Release)->exists())->toBeTrue();
});

test('orders that already shipped cannot be cancelled', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Shipping)->create();

    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/cancel", ['reason' => 'Too late'])->assertSessionHasErrors('status');
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Shipping);
});

test('shipping an order turns the reservation into a sale', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::ReadyForPickup)->create();
    $item = $order->items()->first();

    app(OrderStatusService::class)->transition($order, SellerOrderStatus::Shipping, $this->admin);

    expect($item->variation->fresh()->stock)->toBe(18)
        ->and($item->variation->fresh()->reserved)->toBe(0)
        ->and($item->product->fresh()->sold_count)->toBe(2)
        ->and($order->fresh()->shipped_at)->not->toBeNull();
});

test('stock cannot be reserved beyond what is available', function () {
    $order = SellerOrder::factory()->create();
    $variation = $order->items()->first()->variation;

    expect(fn () => app(InventoryService::class)->reserve($variation, 19, $order))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(InventoryService::class)->adjust($variation, 1))->toThrow(InvalidArgumentException::class);
});

test('approving a refund with restock returns stock and records a refund transaction', function () {
    $refund = Refund::factory()->create();
    $order = $refund->sellerOrder;
    $variation = $order->items()->first()->variation;

    $this->actingAs($this->admin)->post("/admin/refunds/{$refund->id}/approve", ['restock' => true])->assertRedirect();

    expect($refund->fresh()->status)->toBe(RefundStatus::Refunded)
        ->and($order->fresh()->status)->toBe(SellerOrderStatus::Refunded)
        ->and($variation->fresh()->stock)->toBe(22);

    $transaction = Transaction::where('type', TransactionType::Refund)->first();
    expect($transaction->amount)->toBe('300.00')
        ->and($transaction->direction)->toBe(TransactionDirection::Debit)
        ->and($transaction->shop_id)->toBe($order->shop_id);
});

test('a refund claws back seller earnings that were already credited', function () {
    $refund = Refund::factory()->create();
    $order = $refund->sellerOrder;
    $ledger = app(LedgerService::class);

    DB::transaction(function () use ($ledger, $order) {
        $ledger->creditPending($order->shop, '285.00');
        $ledger->record(TransactionType::SaleEarning, TransactionDirection::Credit, '285.00', $order, ['shop' => $order->shop]);
    });

    $this->actingAs($this->admin)->post("/admin/refunds/{$refund->id}/approve")->assertRedirect();

    $balance = $order->shop->balance()->first();
    expect($balance->pending_balance)->toBe('0.00')->and($balance->total_earned)->toBe('0.00');
});

test('rejecting a refund restores the previous order status', function () {
    $refund = Refund::factory()->create();

    $this->actingAs($this->admin)->post("/admin/refunds/{$refund->id}/reject")->assertSessionHasErrors('reason');
    $this->actingAs($this->admin)->post("/admin/refunds/{$refund->id}/reject", ['reason' => 'No proof of damage'])->assertRedirect();

    expect($refund->fresh()->status)->toBe(RefundStatus::Rejected)
        ->and($refund->sellerOrder->fresh()->status)->toBe(SellerOrderStatus::Delivered);

    $this->actingAs($this->admin)->post("/admin/refunds/{$refund->id}/approve")->assertSessionHasErrors('status');
});

test('invalid status transitions are rejected by the service', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Completed)->create();

    expect(fn () => app(OrderStatusService::class)->transition($order, SellerOrderStatus::Cancelled))->toThrow(ValidationException::class);
});
