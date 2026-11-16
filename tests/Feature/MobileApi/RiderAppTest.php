<?php

use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Services\Logistics\DispatchService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->partner = LogisticsPartner::factory()->create(['rider_fee_per_delivery' => '35.00']);
    $this->rider = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id, 'vehicle_type' => 'motorcycle']);
    $this->token = appLogin($this, $this->rider->user, 'rider');
});

test('riders see their profile, pay rate and shifts, and update their phone', function () {
    withBearer($this, $this->token)->getJson('/api/v1/rider/profile')->assertOk()
        ->assertJsonPath('data.partner', $this->partner->name)
        ->assertJsonPath('data.vehicle_type', 'motorcycle')
        ->assertJsonPath('data.rate_per_delivery', '35.00');

    withBearer($this, $this->token)->putJson('/api/v1/rider/profile', ['phone' => '12345'])->assertUnprocessable()->assertJsonValidationErrors('phone');
    withBearer($this, $this->token)->putJson('/api/v1/rider/profile', ['phone' => '09175550000'])->assertOk()->assertJsonPath('data.phone', '09175550000');

    withBearer($this, $this->token)->postJson('/api/v1/rider/availability', ['action' => 'sign_in'])->assertOk();
    $this->travel(2)->hours();
    withBearer($this, $this->token)->postJson('/api/v1/rider/availability', ['action' => 'sign_out'])->assertOk();
    withBearer($this, $this->token)->getJson('/api/v1/rider/shifts')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.minutes', 120);
});

test('assignments use the same tabs as the rider web app, with counts', function () {
    $new = Shipment::factory()->create(['logistics_partner_id' => $this->partner->id]);
    $accepted = Shipment::factory()->create(['logistics_partner_id' => $this->partner->id]);
    foreach ([$new, $accepted] as $shipment) {
        app(DispatchService::class)->assign($shipment, $this->rider, $this->partner->owner);
    }

    withBearer($this, $this->token)->postJson('/api/v1/rider/availability', ['action' => 'sign_in']);
    withBearer($this, $this->token)->postJson("/api/v1/rider/deliveries/{$accepted->id}/status", ['status' => 'accepted'])->assertOk();

    withBearer($this, $this->token)->getJson('/api/v1/rider/assignments?tab=new')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $new->id)
        ->assertJsonPath('meta.counts.new', 1)
        ->assertJsonPath('meta.counts.pickup', 1)
        ->assertJsonPath('meta.counts.delivery', 0);
    withBearer($this, $this->token)->getJson('/api/v1/rider/assignments?tab=pickup')->assertJsonPath('data.0.status', ShipmentStatus::Accepted->value);
    withBearer($this, $this->token)->getJson('/api/v1/rider/assignments?tab=pickup&search='.$new->tracking_number)->assertJsonCount(0, 'data');
    withBearer($this, $this->token)->getJson('/api/v1/rider/assignments?tab=nonsense')->assertUnprocessable();

    // The older `scope` parameter still works.
    withBearer($this, $this->token)->getJson('/api/v1/rider/assignments?scope=open')->assertJsonCount(2, 'data');
});
