<?php

use App\Enums\ProductStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\BankAccount;
use App\Models\LogisticsPartner;
use App\Models\Product;
use App\Models\Refund;
use App\Models\RiderProfile;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Alert;
use App\Services\Administration\SettingsService;
use App\Services\Catalog\ProductModerationService;
use App\Services\Catalog\SellerApprovalService;
use App\Services\Finance\LedgerService;
use App\Services\Finance\RemittanceService;
use App\Services\Finance\WithdrawalService;
use App\Services\Identity\AccountService;
use App\Services\Logistics\DispatchService;
use App\Services\Logistics\ShipmentService;
use App\Services\Logistics\ShipmentStatusService;
use App\Services\Ordering\CustomerOrderService;
use App\Services\Ordering\RefundService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->admin = User::factory()->admin()->create();
});

/**
 * Alerts of one type sent to a user (with Notification::fake()).
 */
function alertsFor(User $user, string $type)
{
    return Notification::sent($user, Alert::class)->filter(fn (Alert $alert) => $alert->type === $type);
}

test('delivery moments reach the customer and failures reach the partner', function () {
    Notification::fake();
    $partner = LogisticsPartner::factory()->create();
    $shipment = Shipment::factory()->create(['logistics_partner_id' => $partner->id, 'status' => ShipmentStatus::InTransit]);
    $customer = $shipment->sellerOrder->order->customer;
    $statuses = app(ShipmentStatusService::class);

    $statuses->transition($shipment, ShipmentStatus::OutForDelivery);
    expect(alertsFor($customer, 'delivery.out')->first()->body)->toContain('Please prepare ₱359.00');

    $statuses->transition($shipment->fresh(), ShipmentStatus::CustomerUnavailable, null, 'Nobody home');
    expect(alertsFor($customer, 'delivery.failed')->first()->title)->toBe('We missed you')
        ->and(alertsFor($partner->owner, 'delivery.failed')->first()->url)->toBe("/logistics/shipments/{$shipment->id}");
});

test('new parcels reach the covering partner, and uncovered ones reach admins', function () {
    Notification::fake();
    $partner = LogisticsPartner::factory()->create();
    $partner->serviceAreas()->create(['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Quezon City', 'delivery_fee' => 59]);
    $covered = SellerOrder::factory()->create();
    $covered->order->address()->update(['city' => 'Quezon City']);
    app(ShipmentService::class)->createFor($covered->fresh());
    expect(alertsFor($partner->owner, 'shipment.new'))->toHaveCount(1);

    $uncovered = SellerOrder::factory()->create();
    $uncovered->order->address()->update(['city' => 'Davao City']);
    app(ShipmentService::class)->createFor($uncovered->fresh());
    expect(alertsFor($this->admin, 'shipment.unrouted'))->toHaveCount(1);

    // The admin toggle switches that alert off.
    app(SettingsService::class)->set('notifications', 'notify_unrouted_shipments', '0');
    $another = SellerOrder::factory()->create();
    $another->order->address()->update(['city' => 'Davao City']);
    app(ShipmentService::class)->createFor($another->fresh());
    expect(alertsFor($this->admin, 'shipment.unrouted'))->toHaveCount(1);
});

test('refund requests reach admins and the seller; the decision reaches the customer', function () {
    Notification::fake();
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $customer = $order->order->customer;
    $seller = $order->shop->sellerProfile->user;

    $refund = app(CustomerOrderService::class)->requestRefund($order, $customer, ['reason' => 'Damaged', 'amount' => '100']);
    expect(alertsFor($this->admin, 'refund.requested'))->toHaveCount(1)
        ->and(alertsFor($seller, 'refund.requested')->first()->url)->toBe("/seller/orders/{$order->id}");

    app(RefundService::class)->reject($refund, $this->admin, 'Photos do not show damage');
    expect(alertsFor($customer, 'refund.rejected')->first()->body)->toContain('Photos do not show damage');

    $second = Refund::factory()->create();
    app(RefundService::class)->approve($second, $this->admin);
    expect(alertsFor($second->sellerOrder->order->customer, 'refund.approved'))->toHaveCount(1);
});

test('seller applications, approvals and product reviews notify the right people', function () {
    Notification::fake();
    $user = User::factory()->customer()->create();
    $profile = app(AccountService::class)->applyToSell($user, ['business_name' => 'Lola Bakery', 'business_type' => 'sole_proprietor', 'shop_name' => 'Lola Bakery', 'pickup_street' => '1', 'pickup_barangay' => 'X', 'pickup_city' => 'Quezon City', 'pickup_province' => 'MM', 'pickup_region' => 'NCR', 'pickup_phone' => '09171234567']);
    expect(alertsFor($this->admin, 'seller.applied')->first()->url)->toBe("/admin/sellers/{$profile->id}");

    app(SellerApprovalService::class)->approve($profile, $this->admin);
    expect(alertsFor($user, 'seller.approved'))->toHaveCount(1);

    $rejected = SellerProfile::factory()->create();
    app(SellerApprovalService::class)->reject($rejected, $this->admin, 'Blurry permit');
    expect(alertsFor($rejected->user, 'seller.rejected')->first()->body)->toContain('Blurry permit');

    $product = Product::factory()->status(ProductStatus::UnderReview)->create();
    app(ProductModerationService::class)->reject($product, $this->admin, 'Missing photos');
    expect(alertsFor($product->shop->sellerProfile->user, 'product.rejected')->first()->body)->toContain('Missing photos');
});

test('withdrawals notify admins on request and the seller on each decision', function () {
    Notification::fake();
    $shop = Shop::factory()->create();
    app(LedgerService::class)->creditAvailable($shop, '1000.00');
    $account = BankAccount::factory()->create(['shop_id' => $shop->id]);
    $seller = $shop->sellerProfile->user;

    $withdrawal = app(WithdrawalService::class)->request($shop, $account, '500.00', $seller);
    expect(alertsFor($this->admin, 'withdrawal.requested'))->toHaveCount(1);

    app(WithdrawalService::class)->approve($withdrawal, $this->admin);
    app(WithdrawalService::class)->markPaid($withdrawal, $this->admin, 'BDO-123');
    expect(alertsFor($seller, 'withdrawal.approved'))->toHaveCount(1)
        ->and(alertsFor($seller, 'withdrawal.paid')->first()->body)->toContain('BDO-123');
});

test('dispatch and cash events notify partners and riders', function () {
    Notification::fake();
    $partner = LogisticsPartner::factory()->create();
    $rider = RiderProfile::factory()->create(['logistics_partner_id' => $partner->id]);
    $shipment = Shipment::factory()->create(['logistics_partner_id' => $partner->id]);
    $dispatch = app(DispatchService::class);

    $dispatch->assign($shipment, $rider, $partner->owner);
    $dispatch->unassign($shipment->fresh(), $partner->owner, 'Rider sick');
    expect(alertsFor($rider->user, 'job.removed'))->toHaveCount(1);

    $other = RiderProfile::factory()->create(['logistics_partner_id' => $partner->id]);
    $dispatch->assign($shipment->fresh(), $rider, $partner->owner);
    $dispatch->assign($shipment->fresh(), $other, $partner->owner);
    expect(alertsFor($rider->user, 'job.removed'))->toHaveCount(2);

    $rider->collections()->create(['shipment_id' => $shipment->id, 'amount_due' => '359.00', 'amount_collected' => '359.00', 'collected_at' => now(), 'status' => 'collected']);
    $remittance = app(RemittanceService::class)->submit($rider, '300.00');
    expect(alertsFor($partner->owner, 'remittance.submitted'))->toHaveCount(1);

    app(RemittanceService::class)->confirm($remittance, $partner->owner, '300.00');
    expect(alertsFor($rider->user, 'remittance.disputed')->first()->body)->toContain('₱59.00');
});

test('the admin team email and user emails follow the settings', function () {
    Notification::fake();
    $settings = app(SettingsService::class);
    $settings->set('notifications', 'admin_email', 'ops@onetap.test');
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $customer = $order->order->customer;

    $refund = app(CustomerOrderService::class)->requestRefund($order, $customer, ['reason' => 'Damaged']);
    Notification::assertSentOnDemand(Alert::class, fn (Alert $alert, array $channels, AnonymousNotifiable $notifiable) => $notifiable->routes['mail'] === 'ops@onetap.test' && $alert->type === 'refund.requested');

    // Users get in-app only, until "email users" is switched on.
    app(RefundService::class)->reject($refund, $this->admin, 'No');
    expect(alertsFor($customer, 'refund.rejected')->first()->via($customer))->toBe(['database']);

    $settings->set('notifications', 'email_users', '1');
    $second = Refund::factory()->create();
    app(RefundService::class)->approve($second, $this->admin);
    $owner = $second->sellerOrder->order->customer;
    expect(alertsFor($owner, 'refund.approved')->first()->via($owner))->toBe(['database', 'mail']);
});

test('suspended users get no alerts', function () {
    Notification::fake();
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $order->shop->sellerProfile->user->update(['status' => 'suspended']);

    app(CustomerOrderService::class)->requestRefund($order, $order->order->customer, ['reason' => 'Damaged']);
    expect(alertsFor($order->shop->sellerProfile->user, 'refund.requested'))->toHaveCount(0);
});

test('every role reads its inbox from the header bell', function () {
    $partner = LogisticsPartner::factory()->create();
    $owner = $partner->owner;
    $owner->notify(new Alert('shipment.new', 'New pickup request', 'OTX1 is ready.', '/logistics/shipments'));
    $owner->notify(new Alert('x', 'Bad link', 'Should not leave the app.', 'https://evil.example'));

    $this->actingAs($owner)->getJson('/notifications/feed')->assertOk()->assertJsonPath('unread', 2)->assertJsonCount(2, 'data');
    $first = $owner->notifications()->where('data->title', 'New pickup request')->first();
    $evil = $owner->notifications()->where('data->title', 'Bad link')->first();

    $this->actingAs($owner)->get("/notifications/{$first->id}/open")->assertRedirect('/logistics/shipments');
    $this->actingAs($owner)->get("/notifications/{$evil->id}/open")->assertRedirect(route('logistics.dashboard'));
    expect($owner->unreadNotifications()->count())->toBe(0);

    // Someone else's notification is not found.
    $this->actingAs($this->admin)->get("/notifications/{$first->id}/open")->assertNotFound();

    $owner->notify(new Alert('x', 'Another', 'Body'));
    $this->actingAs($owner)->postJson('/notifications/read-all')->assertOk()->assertJsonPath('unread', 0);
    $this->actingAs($owner)->get('/logistics')->assertInertia(fn ($page) => $page->where('counts.notifications', 0));
});

test('the API exposes the inbox to every role', function () {
    $rider = RiderProfile::factory()->create();
    $rider->user->notify(new Alert('job.removed', 'Job reassigned', 'OTX1', '/rider/deliveries'));
    $rider->user->notify(new Alert('remittance.confirmed', 'Remittance confirmed', 'Thanks'));
    Sanctum::actingAs($rider->user);

    $response = $this->getJson('/api/v1/notifications')->assertOk()->assertJsonPath('meta.unread', 2)->assertJsonCount(2, 'data');
    $id = $response->json('data.0.id');

    $this->postJson("/api/v1/notifications/{$id}/read")->assertOk()->assertJsonPath('data.read', true);
    $this->getJson('/api/v1/notifications?unread=1')->assertJsonCount(1, 'data');
    $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('unread', 0);
    $this->postJson('/api/v1/notifications/not-a-real-id/read')->assertNotFound();
});
