<?php

use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\User;
use App\Services\Logistics\DispatchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Storage::fake('local');
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->partner = LogisticsPartner::factory()->create(['rider_fee_per_delivery' => '35.00']);
    $this->rider = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);
    $this->shipment = Shipment::factory()->create(['logistics_partner_id' => $this->partner->id]);
    app(DispatchService::class)->assign($this->shipment, $this->rider, $this->partner->owner);
});

test('a rider app completes a delivery over the API', function () {
    Sanctum::actingAs($this->rider->user);

    $this->postJson('/api/v1/rider/availability', ['action' => 'sign_in'])->assertOk()->assertJsonPath('data.on_shift', true)->assertJsonPath('data.availability', 'available');

    $this->getJson('/api/v1/rider/assignments')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.tracking_number', $this->shipment->tracking_number)
        ->assertJsonPath('data.0.status', 'assigned');

    $this->getJson("/api/v1/rider/deliveries/{$this->shipment->id}")->assertOk()->assertJsonPath('data.next_steps', ['accepted'])->assertJsonPath('data.can_decline', true);

    foreach (['accepted', 'arrived_at_pickup', 'picked_up', 'in_transit', 'out_for_delivery'] as $status) {
        $this->postJson("/api/v1/rider/deliveries/{$this->shipment->id}/status", ['status' => $status, 'lat' => 14.676, 'lng' => 121.0437])->assertOk()->assertJsonPath('data.status', $status);
    }

    $this->postJson("/api/v1/rider/deliveries/{$this->shipment->id}/status", ['status' => 'delivered'])->assertUnprocessable()->assertJsonValidationErrors('status');

    $this->post("/api/v1/rider/deliveries/{$this->shipment->id}/proof", [
        'photo' => UploadedFile::fake()->image('proof.jpg'),
        'recipient_name' => 'Juan Dela Cruz',
        'amount_collected' => '359.00',
    ], ['Accept' => 'application/json'])->assertCreated()
        ->assertJsonPath('data.status', 'delivered')
        ->assertJsonPath('data.collection.amount_collected', '359.00')
        ->assertJsonPath('data.proof.recipient_name', 'Juan Dela Cruz');

    $this->get("/api/v1/rider/deliveries/{$this->shipment->id}/proof-photo")->assertOk();
    $this->getJson('/api/v1/rider/dashboard')->assertOk()->assertJsonPath('data.stats.completedToday', 1)->assertJsonPath('data.stats.earningsToday', '35.00');
    $this->getJson('/api/v1/rider/cod')->assertOk()->assertJsonPath('data.summary.inHand', '359.00');
    $this->postJson('/api/v1/rider/cod/remittances', ['amount' => '359.00'])->assertCreated()->assertJsonPath('data.status', 'submitted');
    $this->getJson('/api/v1/rider/assignments?scope=history')->assertOk()->assertJsonCount(1, 'data');

    expect($this->shipment->fresh()->status)->toBe(ShipmentStatus::Delivered);
});

test('the API keeps riders to their own jobs', function () {
    $other = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);
    Sanctum::actingAs($other->user);

    $this->postJson('/api/v1/rider/availability', ['action' => 'sign_in'])->assertOk();
    $this->getJson('/api/v1/rider/assignments')->assertOk()->assertJsonCount(0, 'data');
    $this->getJson("/api/v1/rider/deliveries/{$this->shipment->id}")->assertNotFound();
    $this->postJson("/api/v1/rider/deliveries/{$this->shipment->id}/status", ['status' => 'accepted'])->assertNotFound();
});

test('rider endpoints need a rider token', function () {
    $this->getJson('/api/v1/rider/assignments')->assertUnauthorized();

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson('/api/v1/rider/assignments')->assertForbidden();
});

test('the API validates status updates', function () {
    Sanctum::actingAs($this->rider->user);
    $this->postJson('/api/v1/rider/availability', ['action' => 'sign_in']);

    $this->postJson("/api/v1/rider/deliveries/{$this->shipment->id}/status", ['status' => 'teleported'])->assertUnprocessable()->assertJsonValidationErrors('status');
    $this->postJson("/api/v1/rider/deliveries/{$this->shipment->id}/status", ['status' => 'declined'])->assertUnprocessable()->assertJsonValidationErrors('note');
    $this->postJson("/api/v1/rider/deliveries/{$this->shipment->id}/status", ['status' => 'accepted', 'lat' => 200])->assertUnprocessable()->assertJsonValidationErrors('lat');
});
