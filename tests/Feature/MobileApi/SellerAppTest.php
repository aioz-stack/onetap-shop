<?php

use App\Models\BankAccount;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\Promotion;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\ShopAutoReply;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Withdrawal;
use App\Services\Finance\LedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
    $this->token = appLogin($this, $this->seller, 'seller');
    $this->other = Shop::factory()->create();
});

test('sellers manage their shop profile, branding and vacation mode', function () {
    Storage::fake('public');

    withBearer($this, $this->token)->getJson('/api/v1/seller/shop')->assertOk()->assertJsonPath('data.id', $this->shop->id)->assertJsonStructure(['data' => ['categories', 'templates', 'decoration']]);

    withBearer($this, $this->token)->putJson('/api/v1/seller/shop', ['description' => 'Fresh bread daily', 'pickup_street' => '45 Rizal', 'pickup_barangay' => 'X', 'pickup_city' => 'Quezon City', 'pickup_province' => 'MM', 'pickup_region' => 'NCR', 'pickup_phone' => '0917'])
        ->assertUnprocessable()->assertJsonValidationErrors('pickup_phone');
    withBearer($this, $this->token)->putJson('/api/v1/seller/shop', ['description' => 'Fresh bread daily', 'pickup_street' => '45 Rizal', 'pickup_barangay' => 'X', 'pickup_city' => 'Quezon City', 'pickup_province' => 'MM', 'pickup_region' => 'NCR', 'pickup_phone' => '09181234567'])
        ->assertOk()->assertJsonPath('data.description', 'Fresh bread daily');

    withBearer($this, $this->token)->post('/api/v1/seller/shop/branding', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300), 'template' => 'spotlight', 'accent_color' => '#123456'], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.template', 'spotlight')->assertJsonPath('data.decoration.accent_color', '#123456');
    expect($this->shop->fresh()->logo)->not->toBeNull();

    withBearer($this, $this->token)->postJson('/api/v1/seller/shop/vacation', ['vacation_mode' => true, 'vacation_message' => 'Back Monday'])->assertOk()->assertJsonPath('data.vacation_mode', true);
    expect($this->shop->fresh()->vacation_mode)->toBeTrue();
});

test('inventory lists only this shop, with a low-stock filter', function () {
    $product = Product::factory()->create(['shop_id' => $this->shop->id]);
    $product->variations()->first()->update(['stock' => 3, 'reserved' => 0]);
    Product::factory()->create(['shop_id' => $this->other->id]);

    withBearer($this, $this->token)->getJson('/api/v1/seller/inventory')->assertOk()->assertJsonCount($product->variations()->count(), 'data');
    withBearer($this, $this->token)->getJson('/api/v1/seller/inventory?low=1')->assertOk()->assertJsonPath('data.0.available', 3);
});

test('sellers read and answer reviews of their products only', function () {
    // Reviews always belong to a purchased order item.
    $reviewFor = function (Shop $shop, int $rating, ?string $comment = null) {
        $order = SellerOrder::factory()->create(['shop_id' => $shop->id]);
        $item = OrderItem::query()->where('seller_order_id', $order->id)->firstOrFail();
        $item->product->update(['shop_id' => $shop->id]);

        return ProductReview::query()->create(['product_id' => $item->product_id, 'order_item_id' => $item->id, 'user_id' => $order->order->user_id, 'rating' => $rating, 'comment' => $comment, 'is_visible' => true]);
    };
    $review = $reviewFor($this->shop, 2, 'Too dry');
    $foreign = $reviewFor($this->other, 5);

    withBearer($this, $this->token)->getJson('/api/v1/seller/reviews?filter=unanswered')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.unanswered', 1);
    withBearer($this, $this->token)->postJson("/api/v1/seller/reviews/{$review->id}/reply", ['reply' => 'Sorry! Next batch is softer.'])->assertOk()->assertJsonPath('data.seller_reply', 'Sorry! Next batch is softer.');
    withBearer($this, $this->token)->postJson("/api/v1/seller/reviews/{$foreign->id}/reply", ['reply' => 'x'])->assertNotFound();
    withBearer($this, $this->token)->getJson('/api/v1/seller/reviews?filter=unanswered')->assertJsonCount(0, 'data');
});

test('automated replies, including off-work hours and the on/off switch', function () {
    withBearer($this, $this->token)->postJson('/api/v1/seller/auto-replies', ['type' => 'greeting', 'message' => 'Hi!'])->assertCreated();
    withBearer($this, $this->token)->postJson('/api/v1/seller/auto-replies', ['type' => 'greeting', 'message' => 'Again'])->assertUnprocessable()->assertJsonValidationErrors('type');
    $offWork = withBearer($this, $this->token)->postJson('/api/v1/seller/auto-replies', ['type' => 'off_work', 'message' => 'Closed', 'days' => [1, 2, 3], 'start' => '08:00', 'end' => '17:00'])
        ->assertCreated()->assertJsonPath('data.schedule.days', [1, 2, 3])->json('data');

    withBearer($this, $this->token)->patchJson("/api/v1/seller/auto-replies/{$offWork['id']}/active", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', false);
    withBearer($this, $this->token)->putJson("/api/v1/seller/auto-replies/{$offWork['id']}", ['message' => 'Closed now', 'days' => [1], 'start' => '09:00', 'end' => '18:00'])->assertOk()->assertJsonPath('data.is_active', false)->assertJsonPath('data.schedule.start', '09:00');
    withBearer($this, $this->token)->getJson('/api/v1/seller/auto-replies')->assertOk()->assertJsonCount(2, 'data.replies')->assertJsonPath('data.timezone', 'Asia/Manila');

    $foreign = $this->other->autoReplies()->create(['type' => 'greeting', 'message' => 'Theirs', 'is_active' => true]);
    withBearer($this, $this->token)->deleteJson("/api/v1/seller/auto-replies/{$foreign->id}")->assertNotFound();
    withBearer($this, $this->token)->deleteJson("/api/v1/seller/auto-replies/{$offWork['id']}")->assertNoContent();
    expect(ShopAutoReply::query()->where('shop_id', $this->shop->id)->count())->toBe(1);
});

test('finance: confirm password, then balance, statement, bank accounts and withdrawals', function () {
    app(LedgerService::class)->creditAvailable($this->shop, '1500.00');

    withBearer($this, $this->token)->getJson('/api/v1/seller/finance')->assertStatus(423);
    withBearer($this, $this->token)->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])->assertOk();

    withBearer($this, $this->token)->getJson('/api/v1/seller/finance')->assertOk()->assertJsonPath('data.balance.available', '1500.00')->assertJsonPath('data.minWithdrawal', '500.00');
    withBearer($this, $this->token)->getJson('/api/v1/seller/finance/statement?month='.now()->format('Y-m'))->assertOk()->assertJsonPath('data.statement.orders', 0);
    withBearer($this, $this->token)->getJson('/api/v1/seller/finance/statement?month=2026-13')->assertUnprocessable();

    $first = withBearer($this, $this->token)->postJson('/api/v1/seller/finance/bank-accounts', ['bank_name' => 'BDO', 'account_name' => 'Maria Santos', 'account_number' => '0012-3456-7890'])
        ->assertCreated()->assertJsonPath('data.last4', '7890')->assertJsonPath('data.is_default', true)->assertJsonMissingPath('data.account_number')->json('data');
    $second = withBearer($this, $this->token)->postJson('/api/v1/seller/finance/bank-accounts', ['bank_name' => 'GCash', 'account_name' => 'Maria Santos', 'account_number' => '09171234567'])->assertCreated()->json('data');
    withBearer($this, $this->token)->postJson("/api/v1/seller/finance/bank-accounts/{$second['id']}/default")->assertOk()->assertJsonPath('data.is_default', true);
    withBearer($this, $this->token)->getJson('/api/v1/seller/finance/bank-accounts')->assertJsonPath('data.0.id', $second['id']);

    withBearer($this, $this->token)->postJson('/api/v1/seller/finance/withdrawals', ['amount' => '400', 'bank_account_id' => $first['id']])->assertUnprocessable();
    withBearer($this, $this->token)->postJson('/api/v1/seller/finance/withdrawals', ['amount' => '1000', 'bank_account_id' => $first['id']])->assertCreated()->assertJsonPath('data.status', 'pending');
    withBearer($this, $this->token)->getJson('/api/v1/seller/finance/withdrawals')->assertJsonCount(1, 'data');
    withBearer($this, $this->token)->getJson('/api/v1/seller/finance/transactions')->assertOk();

    // An account with a payout in progress can't be removed; another shop's accounts are out of reach.
    withBearer($this, $this->token)->deleteJson("/api/v1/seller/finance/bank-accounts/{$first['id']}")->assertUnprocessable()->assertJsonValidationErrors('account');
    $foreign = BankAccount::factory()->create(['shop_id' => $this->other->id]);
    withBearer($this, $this->token)->postJson('/api/v1/seller/finance/withdrawals', ['amount' => '500', 'bank_account_id' => $foreign->id])->assertUnprocessable();
    withBearer($this, $this->token)->deleteJson("/api/v1/seller/finance/bank-accounts/{$foreign->id}")->assertNotFound();
    expect(Withdrawal::query()->count())->toBe(1);
});

test('vouchers and promotions over the API stay inside the seller\'s shop', function () {
    $voucher = withBearer($this, $this->token)->postJson('/api/v1/seller/vouchers', ['code' => 'bake10', 'name' => 'Ten off', 'type' => 'fixed', 'value' => 10, 'per_user_limit' => 1])
        ->assertCreated()->assertJsonPath('data.code', 'BAKE10')->assertJsonPath('data.label', '₱10.00 off')->json('data');
    withBearer($this, $this->token)->putJson("/api/v1/seller/vouchers/{$voucher['id']}", ['code' => 'BAKE10', 'name' => 'Ten off', 'type' => 'fixed', 'value' => 15, 'per_user_limit' => 2])->assertOk()->assertJsonPath('data.value', '15.00');
    withBearer($this, $this->token)->getJson('/api/v1/seller/vouchers')->assertOk()->assertJsonCount(1, 'data.vouchers');

    $mine = Product::factory()->create(['shop_id' => $this->shop->id]);
    $theirs = Product::factory()->create(['shop_id' => $this->other->id]);
    withBearer($this, $this->token)->postJson('/api/v1/seller/promotions', ['name' => 'Payday', 'discount_percent' => 20, 'product_ids' => [$theirs->id]])->assertUnprocessable()->assertJsonValidationErrors('product_ids.0');
    $promotion = withBearer($this, $this->token)->postJson('/api/v1/seller/promotions', ['name' => 'Payday', 'discount_percent' => 20, 'product_ids' => [$mine->id], 'is_active' => true])
        ->assertCreated()->assertJsonPath('data.products.0.id', $mine->id)->json('data');
    withBearer($this, $this->token)->deleteJson("/api/v1/seller/promotions/{$promotion['id']}")->assertNoContent();

    $foreignVoucher = Voucher::query()->create(['shop_id' => $this->other->id, 'code' => 'THEIRS', 'name' => 'x', 'type' => 'fixed', 'value' => 5, 'per_user_limit' => 1]);
    $foreignPromotion = Promotion::query()->create(['shop_id' => $this->other->id, 'name' => 'x', 'discount_percent' => 5]);
    withBearer($this, $this->token)->deleteJson("/api/v1/seller/vouchers/{$foreignVoucher->id}")->assertNotFound();
    withBearer($this, $this->token)->deleteJson("/api/v1/seller/promotions/{$foreignPromotion->id}")->assertNotFound();

    withBearer($this, $this->token)->getJson('/api/v1/seller/referrals')->assertOk()->assertJsonStructure(['data' => ['code', 'link', 'referred']]);
});

test('seller endpoints refuse customers and riders', function () {
    $customerToken = appLogin($this, User::factory()->customer()->create(), 'customer');

    foreach (['/api/v1/seller/shop', '/api/v1/seller/inventory', '/api/v1/seller/reviews', '/api/v1/seller/auto-replies', '/api/v1/seller/vouchers'] as $url) {
        withBearer($this, $customerToken)->getJson($url)->assertForbidden();
    }
});
