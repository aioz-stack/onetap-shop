<?php

use App\Enums\SellerOrderStatus;
use App\Enums\WithdrawalStatus;
use App\Models\BankAccount;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Services\Finance\EarningsService;
use App\Services\Finance\LedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
    $this->seller->update(['password' => 'secret123']);
});

test('finance pages require a fresh password confirmation', function () {
    $this->actingAs($this->seller)->get('/seller/finance')->assertRedirect('/confirm-password');

    $this->actingAs($this->seller)->post('/confirm-password', ['password' => 'wrong'])->assertSessionHasErrors('password');
    $this->actingAs($this->seller)->post('/confirm-password', ['password' => 'secret123'])->assertRedirect('/seller/finance');

    $this->actingAs($this->seller)->get('/seller/finance')->assertOk()->assertInertia(fn (Assert $page) => $page->component('Seller/Finance/Overview'));
});

test('bank accounts are stored encrypted with only the last four digits exposed', function () {
    $this->actingAs($this->seller)->withSession(['auth.password_confirmed_at' => time()])
        ->post('/seller/finance/bank-accounts', ['bank_name' => 'BDO', 'account_name' => 'Maria Santos', 'account_number' => '0012-3456-7890'])
        ->assertSessionHasNoErrors();

    $account = BankAccount::first();
    $raw = DB::table('bank_accounts')->value('account_number');

    expect($account->account_number)->toBe('001234567890')
        ->and($account->account_last4)->toBe('7890')
        ->and($account->is_default)->toBeTrue()
        ->and($raw)->not->toContain('1234567890');

    $this->actingAs($this->seller)->withSession(['auth.password_confirmed_at' => time()])->get('/seller/finance')
        ->assertInertia(fn (Assert $page) => $page->where('bankAccounts.0.last4', '7890')->missing('bankAccounts.0.account_number'));
});

test('sellers withdraw from their available balance', function () {
    DB::transaction(fn () => app(LedgerService::class)->creditAvailable($this->shop, '1200.00'));
    $account = BankAccount::factory()->create(['shop_id' => $this->shop->id]);
    $confirmed = fn () => $this->actingAs($this->seller)->withSession(['auth.password_confirmed_at' => time()]);

    $confirmed()->post('/seller/finance/withdrawals', ['amount' => '100', 'bank_account_id' => $account->id])->assertSessionHasErrors('amount');
    $confirmed()->post('/seller/finance/withdrawals', ['amount' => '5000', 'bank_account_id' => $account->id])->assertSessionHasErrors('amount');
    $confirmed()->post('/seller/finance/withdrawals', ['amount' => '1000', 'bank_account_id' => BankAccount::factory()->create()->id])->assertSessionHasErrors('bank_account_id');
    $confirmed()->post('/seller/finance/withdrawals', ['amount' => '1000', 'bank_account_id' => $account->id])->assertSessionHasNoErrors();

    expect($this->shop->withdrawals()->first()->status)->toBe(WithdrawalStatus::Pending)
        ->and($this->shop->balance()->first()->available_balance)->toBe('200.00');
});

test('the income statement summarizes completed orders for the month', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['shop_id' => $this->shop->id, 'completed_at' => now()]);
    app(EarningsService::class)->postCompletion($order);

    $this->actingAs($this->seller)->withSession(['auth.password_confirmed_at' => time()])->get('/seller/finance/statement')
        ->assertInertia(fn (Assert $page) => $page
            ->where('statement.gross_sales', '300.00')
            ->where('statement.commission', '15.00')
            ->where('statement.net_income', '285.00')
            ->where('statement.orders', 1)
            ->has('details.data', 1));

    $this->actingAs($this->seller)->withSession(['auth.password_confirmed_at' => time()])->get('/seller/finance/transactions')
        ->assertInertia(fn (Assert $page) => $page->has('transactions.data', 1)->where('transactions.data.0.type', 'sale_earning'));
});

test('a seller cannot remove another shop account or one with payout history', function () {
    $other = BankAccount::factory()->create();
    $used = BankAccount::factory()->create(['shop_id' => $this->shop->id]);
    $used->withdrawals()->create(['shop_id' => $this->shop->id, 'amount' => '500', 'status' => WithdrawalStatus::Paid]);
    $confirmed = fn () => $this->actingAs($this->seller)->withSession(['auth.password_confirmed_at' => time()]);

    $confirmed()->delete("/seller/finance/bank-accounts/{$other->id}")->assertNotFound();
    $confirmed()->delete("/seller/finance/bank-accounts/{$used->id}")->assertSessionHas('error');
    expect(BankAccount::find($used->id))->not->toBeNull();
});
