<?php

use App\Enums\AssignmentStatus;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Notifications\DeliveryAssigned;
use App\Services\Logistics\ShipmentStatusService;
use App\Services\Ordering\OrderStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->partner = LogisticsPartner::factory()->create();
    $this->operator = $this->partner->owner;
    $this->rider = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id, 'availability' => 'available']);
    $this->shipment = Shipment::factory()->create(['logistics_partner_id' => $this->partner->id]);
});

function walk(Shipment $shipment, array $statuses, ?User $actor = null): Shipment
{
    foreach ($statuses as $status) {
        $shipment = app(ShipmentStatusService::class)->transition($shipment, $status, $actor);
    }

    return $shipment;
}

test('every logistics page renders for the partner operator', function (string $path, string $component) {
    $this->actingAs($this->operator)->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/logistics', 'Logistics/Dashboard'],
    ['/logistics/shipments', 'Logistics/Shipments/Index'],
    ['/logistics/shipments?tab=delivered', 'Logistics/Shipments/Index'],
    ['/logistics/riders', 'Logistics/Riders/Index'],
    ['/logistics/riders/create', 'Logistics/Riders/Form'],
    ['/logistics/coverage', 'Logistics/Coverage'],
    ['/logistics/cod', 'Logistics/Finance/Cod'],
    ['/logistics/remittances', 'Logistics/Finance/Remittances'],
    ['/logistics/earnings', 'Logistics/Finance/Earnings'],
]);

test('operators assign their riders, who are notified', function () {
    Notification::fake();

    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/assign", ['rider_id' => $this->rider->id])->assertSessionHasNoErrors();

    $this->shipment->refresh();
    expect($this->shipment->status)->toBe(ShipmentStatus::Assigned)
        ->and($this->shipment->assignments()->first()->rider_profile_id)->toBe($this->rider->id)
        ->and($this->shipment->assignments()->first()->status)->toBe(AssignmentStatus::Assigned);

    Notification::assertSentTo($this->rider->user, DeliveryAssigned::class);
    $this->actingAs($this->operator)->get('/logistics/shipments?tab=assigned')->assertInertia(fn (Assert $page) => $page->has('shipments.data', 1)->where('shipments.data.0.rider', $this->rider->user->name));
});

test('reassigning cancels the previous job', function () {
    $other = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);

    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/assign", ['rider_id' => $this->rider->id]);
    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/assign", ['rider_id' => $other->id])->assertSessionHasNoErrors();

    $jobs = $this->shipment->assignments()->orderBy('id')->get();
    expect($jobs->pluck('status')->all())->toBe([AssignmentStatus::Cancelled, AssignmentStatus::Assigned])
        ->and($jobs->last()->rider_profile_id)->toBe($other->id)
        ->and($this->shipment->fresh()->status)->toBe(ShipmentStatus::Assigned);
});

test('only active riders of the same partner can be assigned', function () {
    $foreign = RiderProfile::factory()->create();
    $suspended = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id, 'status' => 'suspended']);

    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/assign", ['rider_id' => $foreign->id])->assertSessionHasErrors('rider_id');
    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/assign", ['rider_id' => $suspended->id])->assertSessionHasErrors('rider_id');
    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::PendingPickup);
});

test('partners only see and act on their own shipments', function () {
    $theirs = Shipment::factory()->create();

    $this->actingAs($this->operator)->get("/logistics/shipments/{$theirs->id}")->assertNotFound();
    $this->actingAs($this->operator)->post("/logistics/shipments/{$theirs->id}/assign", ['rider_id' => $this->rider->id])->assertNotFound();
    $this->actingAs($this->operator)->get('/logistics/shipments')->assertInertia(fn (Assert $page) => $page->has('shipments.data', 1)->where('counts.incoming', 1));
});

test('pickup moves the seller order to shipping and delivery to delivered', function () {
    $order = $this->shipment->sellerOrder;
    $variation = $order->items()->first()->variation;

    $shipment = walk($this->shipment, [ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup, ShipmentStatus::PickedUp]);
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Shipping)
        ->and($variation->fresh()->reserved)->toBe(0)
        ->and($variation->fresh()->stock)->toBe(18)
        ->and($shipment->picked_up_at)->not->toBeNull();

    $shipment = walk($shipment, [ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery, ShipmentStatus::Delivered]);
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Delivered)
        ->and($shipment->delivered_at)->not->toBeNull()
        ->and($shipment->statusHistories()->count())->toBe(7);
});

test('the delivery flow rejects skipped steps', function () {
    expect(fn () => walk($this->shipment, [ShipmentStatus::Delivered]))->toThrow(ValidationException::class);
    expect(fn () => walk($this->shipment, [ShipmentStatus::Assigned, ShipmentStatus::PickedUp]))->toThrow(ValidationException::class);
});

test('failed deliveries can be rescheduled and redelivered, or returned to the seller', function () {
    $shipment = walk($this->shipment, [ShipmentStatus::Assigned, ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery, ShipmentStatus::CustomerUnavailable]);
    expect($shipment->attempts)->toBe(1);

    $this->actingAs($this->operator)->post("/logistics/shipments/{$shipment->id}/reschedule", ['note' => 'Customer asked for tomorrow', 'scheduled_at' => now()->addDay()->toDateTimeString()])->assertSessionHasNoErrors();
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Rescheduled)->and($shipment->fresh()->scheduled_at)->not->toBeNull();

    $this->actingAs($this->operator)->post("/logistics/shipments/{$shipment->id}/assign", ['rider_id' => $this->rider->id])->assertSessionHasNoErrors();
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::Rescheduled)
        ->and($shipment->assignments()->latest('id')->first()->type->value)->toBe('delivery');

    $shipment = walk($shipment->fresh(), [ShipmentStatus::OutForDelivery, ShipmentStatus::FailedDelivery]);
    $variation = $shipment->sellerOrder->items()->first()->variation;

    $this->actingAs($this->operator)->post("/logistics/shipments/{$shipment->id}/return", ['note' => 'Two failed attempts'])->assertSessionHasNoErrors();
    expect($shipment->fresh()->status)->toBe(ShipmentStatus::ReturnedToSeller)
        ->and($shipment->sellerOrder->fresh()->status)->toBe(SellerOrderStatus::Returned)
        ->and($variation->fresh()->stock)->toBe(20)
        ->and($shipment->assignments()->whereIn('status', ['assigned', 'accepted'])->count())->toBe(0);
});

test('unassigning before pickup puts the shipment back in the queue', function () {
    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/assign", ['rider_id' => $this->rider->id]);
    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/unassign", ['reason' => 'Rider is sick'])->assertSessionHasNoErrors();

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::PendingPickup)
        ->and($this->shipment->assignments()->first()->status)->toBe(AssignmentStatus::Cancelled);
});

test('cancelling a packed order closes its waiting shipment and rider job', function () {
    $this->actingAs($this->operator)->post("/logistics/shipments/{$this->shipment->id}/assign", ['rider_id' => $this->rider->id]);
    $order = $this->shipment->sellerOrder;

    app(OrderStatusService::class)->transition($order, SellerOrderStatus::Cancelled, User::factory()->admin()->create(), 'Customer changed mind');

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::ReturnedToSeller)
        ->and($this->shipment->assignments()->first()->status)->toBe(AssignmentStatus::Cancelled);
});

test('admins route shipments that no partner covers', function () {
    $orphan = Shipment::factory()->create(['logistics_partner_id' => null]);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/admin/logistics/shipments?unassigned=1')->assertInertia(fn (Assert $page) => $page->where('unassignedCount', 1)->has('shipments.data', 1)->where('shipments.data.0.can_route', true));
    $this->actingAs($admin)->post("/admin/logistics/shipments/{$orphan->id}/route", ['partner_id' => $this->partner->id])->assertSessionHasNoErrors();

    expect($orphan->fresh()->logistics_partner_id)->toBe($this->partner->id);
    $this->actingAs($this->operator)->get("/logistics/shipments/{$orphan->id}")->assertOk();
});
