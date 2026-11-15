<?php

use App\Enums\RiderStatus;
use App\Enums\RoleName;
use App\Enums\SellerOrderStatus;
use App\Enums\TransactionDirection;
use App\Enums\TransactionType;
use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Finance\LedgerService;
use App\Services\Finance\WithdrawalService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->admin = User::factory()->admin()->create();
});

function shopWithAvailable(string $amount): array
{
    $shop = Shop::factory()->create();
    DB::transaction(fn () => app(LedgerService::class)->creditAvailable($shop, $amount));

    return [$shop, BankAccount::factory()->create(['shop_id' => $shop->id])];
}

test('a withdrawal request holds the amount and enforces the minimum and balance', function () {
    [$shop, $bank] = shopWithAvailable('2000.00');
    $service = app(WithdrawalService::class);

    expect(fn () => $service->request($shop, $bank, '100.00', $this->admin))->toThrow(ValidationException::class);
    expect(fn () => $service->request($shop, $bank, '5000.00', $this->admin))->toThrow(ValidationException::class);

    $withdrawal = $service->request($shop, $bank, '1500.00', $this->admin);

    expect($withdrawal->status)->toBe(WithdrawalStatus::Pending)
        ->and($shop->balance()->first()->available_balance)->toBe('500.00');
});

test('an admin approves then records payment, which writes the ledger', function () {
    [$shop, $bank] = shopWithAvailable('2000.00');
    $withdrawal = app(WithdrawalService::class)->request($shop, $bank, '1500.00', $this->admin);

    $this->actingAs($this->admin)->post("/admin/finance/withdrawals/{$withdrawal->id}/pay", ['reference' => 'X'])->assertSessionHasErrors('status');

    $this->actingAs($this->admin)->post("/admin/finance/withdrawals/{$withdrawal->id}/approve")->assertRedirect();
    $this->actingAs($this->admin)->post("/admin/finance/withdrawals/{$withdrawal->id}/pay")->assertSessionHasErrors('reference');
    $this->actingAs($this->admin)->post("/admin/finance/withdrawals/{$withdrawal->id}/pay", ['reference' => 'BDO-778812'])->assertRedirect();

    $withdrawal->refresh();
    $balance = $shop->balance()->first();
    $transaction = Transaction::where('type', TransactionType::Withdrawal)->first();

    expect($withdrawal->status)->toBe(WithdrawalStatus::Paid)
        ->and($withdrawal->reference)->toBe('BDO-778812')
        ->and($balance->available_balance)->toBe('500.00')
        ->and($balance->total_withdrawn)->toBe('1500.00')
        ->and($transaction->amount)->toBe('1500.00')
        ->and($transaction->balance_after)->toBe('500.00');
});

test('rejecting a withdrawal returns the held amount', function () {
    [$shop, $bank] = shopWithAvailable('2000.00');
    $withdrawal = app(WithdrawalService::class)->request($shop, $bank, '1500.00', $this->admin);

    $this->actingAs($this->admin)->post("/admin/finance/withdrawals/{$withdrawal->id}/reject", ['reason' => 'Bank details mismatch'])->assertRedirect();

    expect($withdrawal->fresh()->status)->toBe(WithdrawalStatus::Rejected)
        ->and($shop->balance()->first()->available_balance)->toBe('2000.00');
});

test('finance figures come from completed orders', function () {
    SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['completed_at' => now()]);
    SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['completed_at' => now()]);
    SellerOrder::factory()->create();

    $this->actingAs($this->admin)->get('/admin/finance')
        ->assertInertia(fn (Assert $page) => $page
            ->where('allTime.gmv', '718.00')
            ->where('allTime.commission', '30.00')
            ->where('allTime.sellerEarnings', '570.00')
            ->where('thisMonth.commission', '30.00')
            ->has('monthly', 6));
});

test('transactions export as csv with filters applied', function () {
    $shop = Shop::factory()->create(['name' => 'Csv Shop']);
    DB::transaction(function () use ($shop) {
        app(LedgerService::class)->record(TransactionType::Refund, TransactionDirection::Debit, '42.50', null, ['shop' => $shop, 'description' => 'Test refund']);
        app(LedgerService::class)->record(TransactionType::Commission, TransactionDirection::Credit, '9.99', null, ['description' => 'Other']);
    });

    $response = $this->actingAs($this->admin)->get('/admin/finance/transactions/export?type=refund');
    $csv = $response->streamedContent();

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    expect($csv)->toContain('Test refund')->toContain('42.50')->toContain('Csv Shop')->not->toContain('Other');

    $report = $this->actingAs($this->admin)->get('/admin/finance/reports/export')->streamedContent();
    expect(substr_count(trim($report), "\n"))->toBe(12);
});

test('an admin can create a logistics partner with its operator account', function () {
    $this->actingAs($this->admin)->post('/admin/logistics/partners', [
        'name' => 'Bayanihan Couriers',
        'code' => 'BYC',
        'base_delivery_fee' => '49.00',
        'status' => 'active',
        'owner_name' => 'Rosa Ops',
        'owner_username' => 'rosaops',
        'owner_email' => 'rosa@example.com',
        'owner_password' => 'secret123',
    ])->assertRedirect();

    $partner = LogisticsPartner::where('code', 'BYC')->first();
    expect($partner->owner->hasRole(RoleName::Logistics))->toBeTrue()->and($partner->base_delivery_fee)->toBe('49.00');

    $this->actingAs($partner->owner)->get('/logistics')->assertOk()->assertInertia(fn (Assert $page) => $page->where('partner.code', 'BYC'));
});

test('coverage areas can be added, deduplicated, updated and removed', function () {
    $partner = LogisticsPartner::factory()->create();
    $area = ['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Pasig', 'barangay' => 'Kapitolyo', 'delivery_fee' => '45.00', 'is_active' => true];

    $this->actingAs($this->admin)->post("/admin/logistics/partners/{$partner->id}/areas", $area)->assertRedirect();
    $this->actingAs($this->admin)->post("/admin/logistics/partners/{$partner->id}/areas", $area)->assertSessionHasErrors('city');
    $this->actingAs($this->admin)->post("/admin/logistics/partners/{$partner->id}/areas", ['barangay' => ''] + $area)->assertRedirect();

    expect($partner->serviceAreas()->count())->toBe(2)->and($partner->serviceAreas()->whereNull('barangay')->exists())->toBeTrue();

    $saved = $partner->serviceAreas()->where('barangay', 'Kapitolyo')->first();
    $this->actingAs($this->admin)->put("/admin/logistics/partners/{$partner->id}/areas/{$saved->id}", ['delivery_fee' => '39.00'] + $area)->assertRedirect();
    expect($saved->fresh()->delivery_fee)->toBe('39.00');

    $other = LogisticsPartner::factory()->create();
    $this->actingAs($this->admin)->delete("/admin/logistics/partners/{$other->id}/areas/{$saved->id}")->assertNotFound();
    $this->actingAs($this->admin)->delete("/admin/logistics/partners/{$partner->id}/areas/{$saved->id}")->assertRedirect();
    expect($partner->serviceAreas()->count())->toBe(1);
});

test('riders can be suspended and reactivated', function () {
    $rider = RiderProfile::factory()->create(['availability' => 'available']);

    $this->actingAs($this->admin)->post("/admin/logistics/riders/{$rider->id}/suspend")->assertRedirect();
    expect($rider->fresh()->status)->toBe(RiderStatus::Suspended)->and($rider->fresh()->availability->value)->toBe('offline');

    $this->actingAs($this->admin)->post("/admin/logistics/riders/{$rider->id}/suspend")->assertSessionHasErrors('status');
    $this->actingAs($this->admin)->post("/admin/logistics/riders/{$rider->id}/activate")->assertRedirect();
    expect($rider->fresh()->status)->toBe(RiderStatus::Active);
});
