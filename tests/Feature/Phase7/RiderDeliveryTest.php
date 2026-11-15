<?php

use App\Enums\AssignmentStatus;
use App\Enums\CollectionStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RemittanceStatus;
use App\Enums\RiderAvailability;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\RiderRemittance;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Logistics\DispatchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->partner = LogisticsPartner::factory()->create(['rider_fee_per_delivery' => '35.00']);
    $this->operator = $this->partner->owner;
    $this->rider = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id, 'availability' => 'offline']);
    $this->shipment = Shipment::factory()->create(['logistics_partner_id' => $this->partner->id]);
    $this->shipment->sellerOrder->order->payments()->create(['method' => PaymentMethod::Cod, 'amount' => '359.00', 'status' => PaymentStatus::Pending]);
    app(DispatchService::class)->assign($this->shipment, $this->rider, $this->operator);
});

function onShift(RiderProfile $rider): RiderProfile
{
    test()->actingAs($rider->user)->post('/rider/shift/sign-in')->assertSessionHasNoErrors();

    return $rider->refresh();
}

function step(RiderProfile $rider, Shipment $shipment, string $status, array $extra = [])
{
    return test()->actingAs($rider->user)->post("/rider/deliveries/{$shipment->id}/status", ['status' => $status] + $extra);
}

function toDoor(RiderProfile $rider, Shipment $shipment): void
{
    $flow = ['assigned', 'accepted', 'arrived_at_pickup', 'picked_up', 'in_transit', 'out_for_delivery'];

    foreach (array_slice($flow, array_search($shipment->fresh()->status->value, $flow, true) + 1) as $status) {
        step($rider, $shipment, $status, ['lat' => '14.6760000', 'lng' => '121.0437000'])->assertSessionHasNoErrors();
    }
}

function deliver(RiderProfile $rider, Shipment $shipment, array $data = [])
{
    return test()->actingAs($rider->user)->post("/rider/deliveries/{$shipment->id}/deliver", $data + [
        'photo' => UploadedFile::fake()->image('proof.jpg', 800, 600),
        'recipient_name' => 'Juan Dela Cruz',
        'recipient_relation' => 'Self',
        'amount_collected' => '359.00',
        'lat' => '14.6760000',
        'lng' => '121.0437000',
    ]);
}

test('every rider page renders for the rider', function (string $path, string $component) {
    $path = str_replace('{id}', (string) $this->shipment->id, $path);

    $this->actingAs($this->rider->user)->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/rider', 'Rider/Dashboard'],
    ['/rider/deliveries', 'Rider/Deliveries/Index'],
    ['/rider/deliveries?tab=pickup', 'Rider/Deliveries/Index'],
    ['/rider/deliveries?tab=delivery', 'Rider/Deliveries/Index'],
    ['/rider/deliveries?tab=issues', 'Rider/Deliveries/Index'],
    ['/rider/deliveries?tab=history', 'Rider/Deliveries/Index'],
    ['/rider/deliveries/{id}', 'Rider/Deliveries/Show'],
    ['/rider/cod', 'Rider/Cod'],
    ['/rider/profile', 'Rider/Profile'],
]);

test('other roles cannot open the rider app', function () {
    $this->actingAs($this->operator)->get('/rider')->assertForbidden();
    $this->actingAs(User::factory()->customer()->create())->get('/rider/deliveries')->assertForbidden();
});

test('signing in and out of a shift sets availability and logs the shift', function () {
    onShift($this->rider);
    expect($this->rider->availability)->toBe(RiderAvailability::Available)
        ->and($this->rider->shifts()->whereNull('ended_at')->count())->toBe(1);

    // Signing in twice keeps one open shift.
    onShift($this->rider);
    expect($this->rider->shifts()->count())->toBe(1);

    $this->actingAs($this->rider->user)->post('/rider/shift/availability', ['available' => false])->assertSessionHasNoErrors();
    expect($this->rider->refresh()->availability)->toBe(RiderAvailability::Busy);

    $this->actingAs($this->rider->user)->post('/rider/shift/sign-out')->assertSessionHasNoErrors();
    expect($this->rider->refresh()->availability)->toBe(RiderAvailability::Offline)
        ->and($this->rider->shifts()->first()->ended_at)->not->toBeNull();

    $this->actingAs($this->rider->user)->post('/rider/shift/availability', ['available' => true])->assertSessionHasErrors('shift');
});

test('pending or suspended riders cannot start a shift', function () {
    $this->rider->update(['status' => 'pending']);

    $this->actingAs($this->rider->user)->post('/rider/shift/sign-in')->assertSessionHasErrors('shift');
    expect($this->rider->shifts()->count())->toBe(0);
});

test('riders must be on shift to update a delivery', function () {
    step($this->rider, $this->shipment, 'accepted')->assertSessionHasErrors('status');
    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::Assigned);
});

test('the full delivery flow records proof, COD and payment', function () {
    onShift($this->rider);
    toDoor($this->rider, $this->shipment);

    $shipment = $this->shipment->fresh();
    expect($shipment->status)->toBe(ShipmentStatus::OutForDelivery)
        ->and($shipment->sellerOrder->status)->toBe(SellerOrderStatus::Shipping)
        ->and($shipment->assignments()->first()->status)->toBe(AssignmentStatus::Accepted)
        ->and($shipment->statusHistories()->whereNotNull('lat')->count())->toBe(5);

    deliver($this->rider, $this->shipment)->assertSessionHasNoErrors();

    $shipment = $this->shipment->fresh(['proof', 'collection', 'sellerOrder.order.payments']);
    expect($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->delivered_at)->not->toBeNull()
        ->and($shipment->proof->recipient_name)->toBe('Juan Dela Cruz')
        ->and((float) $shipment->proof->lat)->toBe(14.676)
        ->and($shipment->collection->amount_collected)->toBe('359.00')
        ->and($shipment->collection->status)->toBe(CollectionStatus::Collected)
        ->and($shipment->sellerOrder->status)->toBe(SellerOrderStatus::Delivered)
        ->and($shipment->sellerOrder->paid_at)->not->toBeNull()
        ->and($shipment->sellerOrder->order->payment_status)->toBe(PaymentStatus::Paid)
        ->and($shipment->sellerOrder->order->payments->first()->status)->toBe(PaymentStatus::Paid)
        ->and($shipment->assignments()->first()->status)->toBe(AssignmentStatus::Completed);

    Storage::disk('local')->assertExists($shipment->proof->photo_path);

    $this->actingAs($this->rider->user)->get('/rider')->assertInertia(fn (Assert $page) => $page
        ->where('stats.completedToday', 1)
        ->where('stats.codToday', '359.00')
        ->where('stats.earningsToday', '35.00')
        ->where('stats.assigned', 0));
});

test('delivery needs a photo, a recipient and the out-for-delivery status', function () {
    onShift($this->rider);
    step($this->rider, $this->shipment, 'accepted');

    deliver($this->rider, $this->shipment)->assertSessionHasErrors('status');

    toDoor($this->rider, $this->shipment->fresh());
    $this->actingAs($this->rider->user)->post("/rider/deliveries/{$this->shipment->id}/deliver", ['recipient_name' => ''])->assertSessionHasErrors(['photo', 'recipient_name']);
    deliver($this->rider, $this->shipment, ['photo' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::OutForDelivery);
});

test('short cash is flagged and the order stays unpaid', function () {
    onShift($this->rider);
    toDoor($this->rider, $this->shipment);

    deliver($this->rider, $this->shipment, ['amount_collected' => '300.00'])->assertSessionHasNoErrors();

    $shipment = $this->shipment->fresh(['collection', 'sellerOrder.order']);
    expect($shipment->status)->toBe(ShipmentStatus::Delivered)
        ->and($shipment->collection->status)->toBe(CollectionStatus::Discrepancy)
        ->and($shipment->sellerOrder->paid_at)->toBeNull()
        ->and($shipment->sellerOrder->order->payment_status)->toBe(PaymentStatus::Pending);
});

test('riders cannot skip steps', function () {
    onShift($this->rider);

    step($this->rider, $this->shipment, 'picked_up')->assertSessionHasErrors('status');
    step($this->rider, $this->shipment, 'returned_to_seller', ['note' => 'x'])->assertSessionHasErrors('status');
    step($this->rider, $this->shipment, 'delivered')->assertSessionHasErrors('status');

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::Assigned);
});

test('declining sends the job back to the dispatcher', function () {
    onShift($this->rider);

    step($this->rider, $this->shipment, 'declined')->assertSessionHasErrors('note');
    step($this->rider, $this->shipment, 'declined', ['note' => 'Flat tire'])->assertRedirect('/rider/deliveries');

    $shipment = $this->shipment->fresh();
    expect($shipment->status)->toBe(ShipmentStatus::PendingPickup)
        ->and($shipment->assignments()->first()->status)->toBe(AssignmentStatus::Declined);

    // Once accepted, a job can no longer be declined.
    app(DispatchService::class)->assign($shipment, $this->rider, $this->operator);
    step($this->rider, $shipment, 'accepted');
    step($this->rider, $shipment, 'declined', ['note' => 'Too far'])->assertSessionHasErrors('status');
});

test('failed attempts can be retried and then delivered', function () {
    onShift($this->rider);
    toDoor($this->rider, $this->shipment);

    step($this->rider, $this->shipment, 'customer_unavailable')->assertSessionHasErrors('note');
    step($this->rider, $this->shipment, 'customer_unavailable', ['note' => 'Nobody home'])->assertSessionHasNoErrors();
    expect($this->shipment->fresh()->attempts)->toBe(1);

    $this->actingAs($this->rider->user)->get('/rider/deliveries?tab=issues')->assertInertia(fn (Assert $page) => $page->has('jobs.data', 1));

    step($this->rider, $this->shipment, 'rescheduled', ['note' => 'Customer asked for 5pm'])->assertSessionHasNoErrors();
    step($this->rider, $this->shipment, 'out_for_delivery')->assertSessionHasNoErrors();
    deliver($this->rider, $this->shipment)->assertSessionHasNoErrors();

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::Delivered);
});

test('a failed parcel can be returned to the shop', function () {
    onShift($this->rider);
    toDoor($this->rider, $this->shipment);
    step($this->rider, $this->shipment, 'failed_delivery', ['note' => 'Refused parcel']);

    step($this->rider, $this->shipment, 'returned_to_seller')->assertSessionHasErrors('note');
    step($this->rider, $this->shipment, 'returned_to_seller', ['note' => 'Customer refused'])->assertSessionHasNoErrors();

    $shipment = $this->shipment->fresh();
    expect($shipment->status)->toBe(ShipmentStatus::ReturnedToSeller)
        ->and($shipment->sellerOrder->status)->toBe(SellerOrderStatus::Returned)
        ->and($shipment->assignments()->first()->status)->toBe(AssignmentStatus::Completed);

    $this->actingAs($this->rider->user)->get('/rider')->assertInertia(fn (Assert $page) => $page->where('stats.earningsToday', '0.00'));
});

test('riders only see and touch their own jobs', function () {
    $other = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);
    onShift($other);

    $this->actingAs($other->user)->get("/rider/deliveries/{$this->shipment->id}")->assertNotFound();
    step($other, $this->shipment, 'accepted')->assertNotFound();
    deliver($other, $this->shipment)->assertNotFound();
    $this->actingAs($other->user)->get("/rider/deliveries/{$this->shipment->id}/proof-photo")->assertNotFound();
    $this->actingAs($other->user)->get('/rider/deliveries')->assertInertia(fn (Assert $page) => $page->has('jobs.data', 0));

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::Assigned);
});

test('reassigned riders lose access to update the job', function () {
    $other = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);
    onShift($this->rider);
    app(DispatchService::class)->assign($this->shipment, $other, $this->operator);

    step($this->rider, $this->shipment->fresh(), 'accepted')->assertNotFound();
    $this->actingAs($this->rider->user)->get("/rider/deliveries/{$this->shipment->id}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('job.open', false)->where('job.next_steps', []));
});

test('the proof photo is private to the rider, the partner and admins', function () {
    onShift($this->rider);
    toDoor($this->rider, $this->shipment);
    deliver($this->rider, $this->shipment);

    $this->actingAs($this->rider->user)->get("/rider/deliveries/{$this->shipment->id}/proof-photo")->assertOk();
    $this->actingAs($this->operator)->get("/logistics/shipments/{$this->shipment->id}/proof-photo")->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get("/admin/logistics/shipments/{$this->shipment->id}/proof-photo")->assertOk();

    $otherPartner = LogisticsPartner::factory()->create();
    $this->actingAs($otherPartner->owner)->get("/logistics/shipments/{$this->shipment->id}/proof-photo")->assertNotFound();
    $this->actingAs($this->shipment->sellerOrder->order->customer)->get("/admin/logistics/shipments/{$this->shipment->id}/proof-photo")->assertForbidden();

    $this->actingAs($this->operator)->get("/logistics/shipments/{$this->shipment->id}")->assertInertia(fn (Assert $page) => $page->where('shipment.proof.photo_url', route('logistics.shipments.proof-photo', $this->shipment)));
});

test('riders remit collected cash and the partner confirms it', function () {
    onShift($this->rider);
    toDoor($this->rider, $this->shipment);
    deliver($this->rider, $this->shipment);

    $this->actingAs($this->rider->user)->get('/rider/cod')->assertInertia(fn (Assert $page) => $page->where('summary.inHand', '359.00')->has('collections', 1));

    $this->actingAs($this->rider->user)->post('/rider/cod/remit', ['amount' => '359.00'])->assertSessionHasNoErrors();
    $remittance = RiderRemittance::query()->sole();
    expect($remittance->expected_amount)->toBe('359.00')
        ->and($remittance->status)->toBe(RemittanceStatus::Submitted);

    // Nothing left to remit.
    $this->actingAs($this->rider->user)->post('/rider/cod/remit', ['amount' => '10'])->assertSessionHasErrors('amount');

    $this->actingAs($this->operator)->post("/logistics/remittances/{$remittance->id}/confirm", ['received_amount' => '359.00'])->assertSessionHasNoErrors();
    expect($remittance->fresh()->status)->toBe(RemittanceStatus::Confirmed);
    $this->actingAs($this->rider->user)->get('/rider/cod')->assertInertia(fn (Assert $page) => $page->where('summary.inHand', '0.00')->where('remittances.data.0.status', 'confirmed'));
});

test('partners and admins set the rider pay per delivery', function () {
    $this->actingAs($this->operator)->put('/logistics/rider-pay', ['rider_fee_per_delivery' => '42.50'])->assertSessionHasNoErrors();
    expect($this->partner->fresh()->rider_fee_per_delivery)->toBe('42.50');

    $this->actingAs($this->operator)->put('/logistics/rider-pay', ['rider_fee_per_delivery' => '-1'])->assertSessionHasErrors('rider_fee_per_delivery');

    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->put("/admin/logistics/partners/{$this->partner->id}", [
        'name' => $this->partner->name,
        'code' => $this->partner->code,
        'base_delivery_fee' => '59.00',
        'rider_fee_per_delivery' => '30.00',
        'status' => 'active',
    ])->assertSessionHasNoErrors();
    expect($this->partner->fresh()->rider_fee_per_delivery)->toBe('30.00');
});

test('riders update their contact number and password', function () {
    $this->actingAs($this->rider->user)->put('/rider/profile', ['phone' => '12345'])->assertSessionHasErrors('phone');
    $this->actingAs($this->rider->user)->put('/rider/profile', ['phone' => '09179998888'])->assertSessionHasNoErrors();
    expect($this->rider->user->fresh()->phone)->toBe('09179998888');

    $this->actingAs($this->rider->user)->put('/rider/profile/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertSessionHasErrors('current_password');
    $this->actingAs($this->rider->user)->put('/rider/profile/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertSessionHasNoErrors();
});
