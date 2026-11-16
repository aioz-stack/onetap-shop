<?php

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Services\Logistics\DispatchService;
use App\Services\Logistics\ShipmentStatusService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->partner = LogisticsPartner::factory()->create();
    $this->rider = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);
    $this->shipment = Shipment::factory()->create(['logistics_partner_id' => $this->partner->id]);
    $this->order = $this->shipment->sellerOrder;
    $this->customer = $this->order->order->customer;
    app(DispatchService::class)->assign($this->shipment, $this->rider, $this->partner->owner);
});

function moveTo(Shipment $shipment, array $statuses, ?string $note = null): void
{
    foreach ($statuses as $status) {
        app(ShipmentStatusService::class)->transition($shipment->fresh(), $status, null, $note);
    }
}

function timelineLabels($test): array
{
    $labels = [];
    $test->actingAs($test->customer)->get("/account/orders/{$test->order->id}")->assertOk()
        ->assertInertia(function (Assert $page) use (&$labels) {
            $labels = collect($page->toArray()['props']['order']['timeline'])->pluck('label')->all();
        });

    return $labels;
}

test('the customer history follows the parcel to out for delivery, matching the tracking status', function () {
    moveTo($this->shipment, [ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery]);

    $this->actingAs($this->customer)->get("/account/orders/{$this->order->id}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->where('order.status', 'shipping')
        ->where('order.shipment.status', 'out_for_delivery'));

    $labels = timelineLabels($this);

    // Newest step matches the tracking line; internal steps (queue, accepted) are hidden; nothing repeats.
    expect(end($labels))->toBe('Out for delivery')
        ->and($labels)->toContain('Rider assigned', 'Rider at the shop', 'Shipping', 'In transit')
        ->and($labels)->not->toContain('Pending Pickup', 'Accepted', 'Picked Up')
        ->and($labels)->toBe(array_values(array_unique($labels)))
        ->and(array_search('Shipping', $labels))->toBeLessThan(array_search('In transit', $labels));
});

test('failed attempts and redeliveries show with the rider note, and delivery is not listed twice', function () {
    moveTo($this->shipment, [ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery]);
    moveTo($this->shipment, [ShipmentStatus::CustomerUnavailable], 'Nobody home, called twice');
    moveTo($this->shipment, [ShipmentStatus::Rescheduled, ShipmentStatus::OutForDelivery, ShipmentStatus::Delivered]);

    $timeline = [];
    $this->actingAs($this->customer)->get("/account/orders/{$this->order->id}")->assertInertia(function (Assert $page) use (&$timeline) {
        $timeline = $page->toArray()['props']['order']['timeline'];
    });
    $labels = collect($timeline)->pluck('label');

    expect($this->order->fresh()->status)->toBe(SellerOrderStatus::Delivered)
        ->and($labels->last())->toBe('Delivered')
        ->and($labels->filter(fn ($label) => $label === 'Delivered'))->toHaveCount(1)
        ->and($labels->filter(fn ($label) => $label === 'Out for delivery'))->toHaveCount(2)
        ->and(collect($timeline)->firstWhere('label', 'Delivery attempt failed: recipient unavailable')['note'])->toBe('Nobody home, called twice')
        ->and($labels)->toContain('Redelivery scheduled');
});

test('the mobile API returns the same merged history', function () {
    moveTo($this->shipment, [ShipmentStatus::Accepted, ShipmentStatus::ArrivedAtPickup, ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery]);
    $web = timelineLabels($this);

    Sanctum::actingAs($this->customer);
    $api = collect($this->getJson("/api/v1/orders/{$this->order->id}")->assertOk()->json('data.timeline'))->pluck('label')->all();

    expect($api)->toBe($web);
});
