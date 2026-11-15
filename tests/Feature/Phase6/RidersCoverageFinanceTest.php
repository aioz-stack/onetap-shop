<?php

use App\Enums\CollectionStatus;
use App\Enums\RemittanceStatus;
use App\Enums\RiderStatus;
use App\Enums\ShipmentStatus;
use App\Enums\TransactionType;
use App\Models\LogisticsPartner;
use App\Models\RiderCollection;
use App\Models\RiderProfile;
use App\Models\Shipment;
use App\Models\Transaction;
use App\Services\Finance\RemittanceService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->partner = LogisticsPartner::factory()->create();
    $this->operator = $this->partner->owner;
    $this->rider = RiderProfile::factory()->create(['logistics_partner_id' => $this->partner->id]);
});

function collectFor(RiderProfile $rider, string $amount = '359.00'): RiderCollection
{
    $shipment = Shipment::factory()->status(ShipmentStatus::Delivered)->create(['logistics_partner_id' => $rider->logistics_partner_id, 'delivered_at' => now()]);

    return RiderCollection::create(['shipment_id' => $shipment->id, 'rider_profile_id' => $rider->id, 'amount_due' => '359.00', 'amount_collected' => $amount, 'collected_at' => now(), 'status' => CollectionStatus::Collected]);
}

test('operators add riders who can sign in as riders', function () {
    $payload = ['name' => 'Ramon Cruz', 'username' => 'ramon', 'email' => 'ramon@example.com', 'phone' => '09191234567', 'password' => 'rider1234', 'vehicle_type' => 'motorcycle', 'plate_number' => 'NBC 1234'];

    $this->actingAs($this->operator)->post('/logistics/riders', ['phone' => 'x'] + $payload)->assertSessionHasErrors('phone');
    $this->actingAs($this->operator)->post('/logistics/riders', $payload)->assertRedirect();

    $rider = RiderProfile::whereHas('user', fn ($users) => $users->where('username', 'ramon'))->first();
    expect($rider->status)->toBe(RiderStatus::Pending)->and($rider->logistics_partner_id)->toBe($this->partner->id)->and($rider->user->hasRole('rider'))->toBeTrue();

    $this->actingAs($this->operator)->post("/logistics/riders/{$rider->id}/approve")->assertSessionHasNoErrors();
    expect($rider->fresh()->status)->toBe(RiderStatus::Active);

    auth()->logout();
    $this->post('/login', ['login' => 'ramon', 'password' => 'rider1234'])->assertRedirect('/rider');
});

test('operators edit their riders and cannot touch other partners riders', function () {
    $this->actingAs($this->operator)->put("/logistics/riders/{$this->rider->id}", ['name' => 'Renamed Rider', 'email' => $this->rider->user->email, 'phone' => '09201234567', 'vehicle_type' => 'e_bike', 'plate_number' => 'EB-1'])->assertSessionHasNoErrors();
    expect($this->rider->fresh()->vehicle_type)->toBe('e_bike')->and($this->rider->user->fresh()->name)->toBe('Renamed Rider');

    $foreign = RiderProfile::factory()->create();
    $this->actingAs($this->operator)->get("/logistics/riders/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->operator)->post("/logistics/riders/{$foreign->id}/suspend")->assertNotFound();
});

test('suspending a rider sends their uncollected pickups back to the queue', function () {
    $waiting = Shipment::factory()->create(['logistics_partner_id' => $this->partner->id]);
    $this->actingAs($this->operator)->post("/logistics/shipments/{$waiting->id}/assign", ['rider_id' => $this->rider->id]);

    $this->actingAs($this->operator)->post("/logistics/riders/{$this->rider->id}/suspend")->assertSessionHasNoErrors();

    expect($this->rider->fresh()->status)->toBe(RiderStatus::Suspended)
        ->and($this->rider->fresh()->availability->value)->toBe('offline')
        ->and($waiting->fresh()->status)->toBe(ShipmentStatus::PendingPickup);
});

test('the rider page shows performance', function () {
    collectFor($this->rider);

    $this->actingAs($this->operator)->get("/logistics/riders/{$this->rider->id}")->assertInertia(fn (Assert $page) => $page
        ->where('performance.cod_collected', '359.00')
        ->where('performance.cod_unremitted', '359.00'));
});

test('operators manage their own coverage, with duplicates rejected', function () {
    $area = ['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Marikina', 'barangay' => 'Concepcion', 'delivery_fee' => '49', 'is_active' => true];

    $this->actingAs($this->operator)->post('/logistics/coverage', $area)->assertSessionHasNoErrors();
    $this->actingAs($this->operator)->post('/logistics/coverage', ['city' => 'marikina', 'barangay' => 'CONCEPCION'] + $area)->assertSessionHasErrors('city');

    $saved = $this->partner->serviceAreas()->first();
    $this->actingAs($this->operator)->put("/logistics/coverage/{$saved->id}", ['delivery_fee' => '45'] + $area)->assertSessionHasNoErrors();
    expect($saved->fresh()->delivery_fee)->toBe('45.00');

    $foreign = LogisticsPartner::factory()->create()->serviceAreas()->create($area);
    $this->actingAs($this->operator)->delete("/logistics/coverage/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->operator)->delete("/logistics/coverage/{$saved->id}")->assertSessionHasNoErrors();
    expect($this->partner->serviceAreas()->count())->toBe(0);
});

test('riders remit collected cash and the partner confirms it into the ledger', function () {
    collectFor($this->rider);
    collectFor($this->rider);

    $remittance = app(RemittanceService::class)->submit($this->rider, '718.00');
    expect($remittance->expected_amount)->toBe('718.00')->and($remittance->discrepancy)->toBe('0.00')->and($remittance->collections()->count())->toBe(2);
    expect(fn () => app(RemittanceService::class)->submit($this->rider, '10'))->toThrow(ValidationException::class);

    $this->actingAs($this->operator)->get('/logistics/remittances')->assertInertia(fn (Assert $page) => $page->has('remittances.data', 1)->where('counts.submitted', 1));
    $this->actingAs($this->operator)->post("/logistics/remittances/{$remittance->id}/confirm", ['received_amount' => '718'])->assertSessionHas('success');

    expect($remittance->fresh()->status)->toBe(RemittanceStatus::Confirmed)
        ->and($remittance->collections()->where('status', CollectionStatus::Remitted)->count())->toBe(2)
        ->and(Transaction::where('type', TransactionType::Remittance)->value('amount'))->toBe('718.00');
});

test('a short count leaves the remittance disputed', function () {
    collectFor($this->rider);
    $remittance = app(RemittanceService::class)->submit($this->rider, '359.00');

    $this->actingAs($this->operator)->post("/logistics/remittances/{$remittance->id}/confirm", ['received_amount' => '300', 'notes' => 'Short by 59'])->assertSessionHas('error');

    expect($remittance->fresh()->status)->toBe(RemittanceStatus::Disputed)
        ->and($remittance->fresh()->discrepancy)->toBe('-59.00')
        ->and($remittance->collections()->first()->status)->toBe(CollectionStatus::Discrepancy)
        ->and(Transaction::where('type', TransactionType::Remittance)->exists())->toBeFalse();

    $this->actingAs($this->operator)->post("/logistics/remittances/{$remittance->id}/confirm", ['received_amount' => '359'])->assertSessionHas('success');
    expect($remittance->fresh()->status)->toBe(RemittanceStatus::Confirmed);
});

test('daily COD, earnings and the CSV report use real figures', function () {
    collectFor($this->rider);

    $this->actingAs($this->operator)->get('/logistics/cod')->assertInertia(fn (Assert $page) => $page
        ->where('summary.collected', '359.00')
        ->where('summary.outstanding', '359.00')
        ->has('byRider', 1));

    $this->actingAs($this->operator)->get('/logistics/earnings')->assertInertia(fn (Assert $page) => $page
        ->where('summary.allTimeFees', '59.00')
        ->where('monthly.0.delivered', 1)
        ->where('monthly.0.fees', '59.00'));

    $csv = $this->actingAs($this->operator)->get('/logistics/earnings/export')->streamedContent();
    expect($csv)->toContain('Delivery fees')->toContain('59.00');

    $this->actingAs($this->operator)->get('/logistics')->assertInertia(fn (Assert $page) => $page->where('stats.delivered', 1)->where('stats.codToday', '359.00'));
});
