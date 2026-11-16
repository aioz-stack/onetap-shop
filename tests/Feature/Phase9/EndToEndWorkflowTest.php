<?php

use App\Enums\CollectionStatus;
use App\Enums\PaymentStatus;
use App\Enums\RemittanceStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\LogisticsPartner;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RiderProfile;
use App\Models\RiderRemittance;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Withdrawal;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/*
| Phase 9: the acceptance workflow from agents.md §16, driven through the real web routes of every
| panel, then the money afterwards (earnings release, payout, refund clawback).
*/

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('local');
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);

    $this->admin = User::factory()->admin()->create();

    $this->partner = LogisticsPartner::factory()->create(['base_delivery_fee' => '59.00', 'rider_fee_per_delivery' => '35.00']);
    $this->partner->serviceAreas()->create(['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Quezon City', 'delivery_fee' => 59]);
    $this->operator = $this->partner->owner;
    $this->rider = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);

    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'base_price' => '250.00']);
    $this->variation = $this->product->variations()->first();
    $this->variation->update(['price' => '250.00', 'stock' => 10, 'reserved' => 0]);

    $this->customer = User::factory()->customer()->create();
    $this->address = $this->customer->addresses()->create(['recipient_name' => 'Juan', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Quezon City', 'barangay' => 'Bagong Pag-asa', 'street' => '12 Mabini St.', 'is_default' => true]);
});

/**
 * Customer orders 2 items; seller packs; logistics assigns; rider delivers with proof and COD.
 */
function orderAndDeliver($test, int $quantity = 2): SellerOrder
{
    $test->actingAs($test->customer)->post('/cart/items', ['variation_id' => $test->variation->id, 'quantity' => $quantity])->assertSessionHasNoErrors();
    $test->actingAs($test->customer)->post('/checkout', ['address_id' => $test->address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
    $order = SellerOrder::query()->latest('id')->first();

    $test->actingAs($test->seller)->post("/seller/orders/{$order->id}/accept")->assertSessionHasNoErrors();
    $test->actingAs($test->seller)->post("/seller/orders/{$order->id}/ready")->assertSessionHasNoErrors();
    $shipment = $order->fresh()->shipment;
    expect($shipment->logistics_partner_id)->toBe($test->partner->id);

    $test->actingAs($test->operator)->post("/logistics/shipments/{$shipment->id}/assign", ['rider_id' => $test->rider->id])->assertSessionHasNoErrors();

    $test->actingAs($test->rider->user)->post('/rider/shift/sign-in');
    foreach (['accepted', 'arrived_at_pickup', 'picked_up', 'in_transit', 'out_for_delivery'] as $status) {
        $test->actingAs($test->rider->user)->post("/rider/deliveries/{$shipment->id}/status", ['status' => $status, 'lat' => '14.676', 'lng' => '121.043'])->assertSessionHasNoErrors();
    }
    $test->actingAs($test->rider->user)->post("/rider/deliveries/{$shipment->id}/deliver", [
        'photo' => UploadedFile::fake()->image('proof.jpg', 640, 480),
        'recipient_name' => 'Juan',
        'recipient_relation' => 'Self',
        'amount_collected' => $order->fresh()->total,
    ])->assertSessionHasNoErrors();

    return $order->fresh();
}

test('the full marketplace workflow keeps orders, stock, cash and money in step', function () {
    // 1–7. Customer places order → seller → logistics → rider delivers with proof and COD.
    $order = orderAndDeliver($this, 4);
    $shipment = $order->shipment;

    expect($order->items_subtotal)->toBe('1000.00')
        ->and($order->shipping_fee)->toBe('59.00')
        ->and($order->total)->toBe('1059.00')
        ->and($order->commission_amount)->toBe('50.00')
        ->and($order->seller_earning)->toBe('950.00')
        ->and($order->status)->toBe(SellerOrderStatus::Delivered)
        ->and($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->proof)->not->toBeNull()
        ->and($shipment->collection->status)->toBe(CollectionStatus::Collected)
        ->and($order->order->payment_status)->toBe(PaymentStatus::Paid);

    // Inventory: 10 on hand, 4 sold, nothing left reserved.
    $this->variation->refresh();
    expect($this->variation->stock)->toBe(6)->and($this->variation->reserved)->toBe(0);

    // Every panel sees the same order state.
    $this->actingAs($this->customer)->get("/account/orders/{$order->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.status', 'delivered'));
    $this->actingAs($this->seller)->get("/seller/orders/{$order->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.status', 'delivered'));
    $this->actingAs($this->admin)->get("/admin/orders/{$order->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('order.status', 'delivered')->where('order.shipment.status', 'delivered'));
    $this->actingAs($this->operator)->get("/logistics/shipments/{$shipment->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('shipment.status', 'delivered'));

    // 8. Customer confirms: order completes, commission and the seller's pending earning are booked.
    $this->actingAs($this->customer)->post("/account/orders/{$order->id}/confirm")->assertSessionHasNoErrors();
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Completed)
        ->and($this->shop->balance->fresh()->pending_balance)->toBe('950.00');

    // 9. Rider remits the cash; the partner counts and confirms it.
    $this->actingAs($this->rider->user)->post('/rider/cod/remit', ['amount' => '1059.00'])->assertSessionHasNoErrors();
    $remittance = RiderRemittance::query()->sole();
    $this->actingAs($this->operator)->post("/logistics/remittances/{$remittance->id}/confirm", ['received_amount' => '1059.00'])->assertSessionHasNoErrors();
    expect($remittance->fresh()->status)->toBe(RemittanceStatus::Confirmed)
        ->and($shipment->collection->fresh()->status)->toBe(CollectionStatus::Remitted);

    // 10. After the hold period the earning becomes available.
    $this->travel(4)->days();
    $this->artisan('onetap:release-earnings')->assertSuccessful();
    $balance = $this->shop->balance->fresh();
    expect($balance->pending_balance)->toBe('0.00')->and($balance->available_balance)->toBe('950.00');

    // 11. Seller withdraws (password-confirmed finance pages); admin approves and pays.
    $confirmed = fn () => $this->actingAs($this->seller)->withSession(['auth.password_confirmed_at' => now()->unix()]);
    $confirmed()->post('/seller/finance/bank-accounts', ['bank_name' => 'BDO', 'account_name' => 'Maria Santos', 'account_number' => '0012 3456 7890'])->assertSessionHasNoErrors();
    $account = BankAccount::query()->sole();
    expect($account->account_last4)->toBe('7890');

    // Below the ₱500 minimum is refused; ₱500 goes through and ₱450 stays available.
    $confirmed()->post('/seller/finance/withdrawals', ['amount' => '400.00', 'bank_account_id' => $account->id])->assertSessionHasErrors();
    $confirmed()->post('/seller/finance/withdrawals', ['amount' => '500.00', 'bank_account_id' => $account->id])->assertSessionHasNoErrors();
    $withdrawal = Withdrawal::query()->sole();
    $this->actingAs($this->admin)->post("/admin/finance/withdrawals/{$withdrawal->id}/approve")->assertSessionHasNoErrors();
    $this->actingAs($this->admin)->post("/admin/finance/withdrawals/{$withdrawal->id}/pay", ['reference' => 'BDO-20261116-01'])->assertSessionHasNoErrors();
    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Paid)
        ->and($this->shop->balance->fresh()->available_balance)->toBe('450.00');

    // Ledger: every peso is accounted for.
    $sum = fn (TransactionType $type) => number_format((float) Transaction::query()->where('type', $type)->sum('amount'), 2, '.', '');
    expect($sum(TransactionType::SaleEarning))->toBe('950.00')
        ->and($sum(TransactionType::Commission))->toBe('50.00')
        ->and($sum(TransactionType::Remittance))->toBe('1059.00')
        ->and($sum(TransactionType::Withdrawal))->toBe('500.00');

    // Dashboards read the same records.
    $this->actingAs($this->admin)->get('/admin/finance')->assertOk()->assertInertia(fn (Assert $page) => $page->where('allTime.commission', '50.00')->where('allTime.paidOut', '500.00'));
    $this->actingAs($this->rider->user)->get('/rider/cod')->assertOk()->assertInertia(fn (Assert $page) => $page->where('summary.inHand', '0.00'));
});

test('a refund after completion claws back the earning and restocks', function () {
    $order = orderAndDeliver($this, 1);
    $this->actingAs($this->customer)->post("/account/orders/{$order->id}/confirm")->assertSessionHasNoErrors();
    expect($this->shop->balance->fresh()->pending_balance)->toBe('237.50');

    $this->actingAs($this->customer)->post("/account/orders/{$order->id}/refund", ['reason' => 'Crushed in transit', 'details' => 'Box was flattened'])->assertSessionHasNoErrors();
    $refund = Refund::query()->sole();
    expect($order->fresh()->status)->toBe(SellerOrderStatus::ReturnRequested);

    // The seller and admin both see it; the admin approves with restock.
    $this->actingAs($this->seller)->get('/seller/orders?tab=return_refund')->assertOk()->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    $this->actingAs($this->admin)->post("/admin/refunds/{$refund->id}/approve", ['restock' => true, 'note' => 'Photos confirm damage'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(SellerOrderStatus::Refunded)
        ->and($this->variation->fresh()->stock)->toBe(10)
        ->and($this->shop->balance->fresh()->pending_balance)->toBe('0.00')
        ->and(Transaction::query()->where('type', TransactionType::Refund)->sole()->amount)->toBe('250.00');

    $this->actingAs($this->customer)->get('/account/refunds')->assertOk()->assertInertia(fn (Assert $page) => $page->where('refunds.data.0.status', 'refunded'));
});

test('cancelling before pickup releases stock and closes the parcel everywhere', function () {
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $this->variation->id, 'quantity' => 3]);
    $this->actingAs($this->customer)->post('/checkout', ['address_id' => $this->address->id, 'payment_method' => 'cod'])->assertSessionHasNoErrors();
    $order = SellerOrder::query()->sole();
    expect($this->variation->fresh()->reserved)->toBe(3);

    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/accept");
    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/ready");
    $shipment = $order->fresh()->shipment;
    $this->actingAs($this->operator)->post("/logistics/shipments/{$shipment->id}/assign", ['rider_id' => $this->rider->id]);

    // A packed order can no longer be cancelled by the shop; an administrator can.
    $this->actingAs($this->seller)->post("/seller/orders/{$order->id}/cancel", ['reason' => 'Oven broke down'])->assertSessionHasErrors();
    $this->actingAs($this->admin)->post("/admin/orders/{$order->id}/cancel", ['reason' => 'Shop oven broke down'])->assertSessionHasNoErrors();

    expect($order->fresh()->status)->toBe(SellerOrderStatus::Cancelled)
        ->and($this->variation->fresh()->reserved)->toBe(0)
        ->and($this->variation->fresh()->stock)->toBe(10)
        ->and($shipment->fresh()->status->isFinal() || $shipment->fresh()->status === ShipmentStatus::PendingPickup)->toBeTrue()
        ->and($shipment->assignments()->whereIn('status', ['assigned', 'accepted'])->count())->toBe(0);

    $this->actingAs($this->rider->user)->get('/rider/deliveries')->assertOk()->assertInertia(fn (Assert $page) => $page->where('counts.new', 0));
    expect(Order::query()->sole()->payment_status)->not->toBe(PaymentStatus::Paid);
});
