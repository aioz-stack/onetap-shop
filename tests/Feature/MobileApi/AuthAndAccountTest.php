<?php

use App\Enums\SellerOrderStatus;
use App\Models\DeviceToken;
use App\Models\RiderProfile;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
use App\Notifications\Alert;
use App\Notifications\PasswordResetCode;
use App\Services\Finance\LedgerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
});

test('each app only accepts accounts that can use it', function () {
    $customer = User::factory()->customer()->create();
    $seller = Shop::factory()->create()->sellerProfile->user;
    $rider = RiderProfile::factory()->create()->user;

    // Customer app.
    appLogin($this, $customer, 'customer');
    appLogin($this, $seller, 'customer'); // sellers can shop too
    $this->postJson('/api/v1/auth/login', ['login' => $rider->email, 'password' => 'password', 'app' => 'customer'])
        ->assertForbidden()->assertJsonPath('code', 'app_not_allowed')->assertJsonPath('apps', ['rider']);

    // Seller app needs an approved shop.
    appLogin($this, $seller, 'seller');
    $this->postJson('/api/v1/auth/login', ['login' => $customer->email, 'password' => 'password', 'app' => 'seller'])->assertForbidden();
    $pending = Shop::factory()->create();
    $pending->sellerProfile->update(['status' => 'pending']);
    $this->postJson('/api/v1/auth/login', ['login' => $pending->sellerProfile->user->email, 'password' => 'password', 'app' => 'seller'])->assertForbidden();

    // Rider app needs a rider profile.
    appLogin($this, $rider, 'rider');
    $this->postJson('/api/v1/auth/login', ['login' => $seller->email, 'password' => 'password', 'app' => 'rider'])->assertForbidden();

    // Tokens are named after the app and device.
    expect($seller->tokens()->pluck('name')->sort()->values()->all())->toBe(['customer:Test phone', 'seller:Test phone']);
});

test('me describes the account for every app', function () {
    $seller = Shop::factory()->create()->sellerProfile->user;
    $token = appLogin($this, $seller, 'seller');

    withBearer($this, $token)->getJson('/api/v1/auth/me')->assertOk()
        ->assertJsonPath('data.apps', ['customer', 'seller'])
        ->assertJsonPath('data.seller.status', 'approved')
        ->assertJsonPath('data.seller.shop.id', $seller->sellerProfile->shop->id)
        ->assertJsonPath('data.rider', null)
        ->assertJsonMissingPath('data.password');

    $rider = RiderProfile::factory()->create()->user;
    withBearer($this, appLogin($this, $rider, 'rider'))->getJson('/api/v1/auth/me')->assertJsonPath('data.apps', ['rider'])->assertJsonPath('data.rider.status', 'active');
});

test('app config is public', function () {
    $this->getJson('/api/v1/config')->assertOk()
        ->assertJsonPath('data.name', 'Onetap Marketplace')
        ->assertJsonPath('data.currency', 'PHP')
        ->assertJsonPath('data.payment_methods.0.code', 'cod')
        ->assertJsonPath('data.apps', ['customer', 'seller', 'rider']);
});

test('sign out here, or everywhere', function () {
    $user = User::factory()->customer()->create();
    $phone = appLogin($this, $user, 'customer');
    $tablet = appLogin($this, $user, 'customer');

    withBearer($this, $phone)->postJson('/api/v1/auth/logout')->assertOk();
    withBearer($this, $phone)->getJson('/api/v1/auth/me')->assertUnauthorized();
    withBearer($this, $tablet)->getJson('/api/v1/auth/me')->assertOk();

    $other = appLogin($this, $user, 'customer');
    withBearer($this, $other)->postJson('/api/v1/auth/logout-all')->assertOk();
    expect($user->tokens()->count())->toBe(0);
    withBearer($this, $tablet)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('forgot password sends a 6-digit code that resets the password once', function () {
    Notification::fake();
    $user = User::factory()->customer()->create(['email' => 'ana@example.test']);
    $oldToken = appLogin($this, $user, 'customer');

    // Same answer for unknown emails (no account discovery), and nothing is sent.
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'nobody@example.test'])->assertOk();
    Notification::assertNothingSent();

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ana@example.test'])->assertOk();
    $code = null;
    Notification::assertSentTo($user, PasswordResetCode::class, function (PasswordResetCode $notification) use (&$code) {
        $code = $notification->code;

        return strlen($notification->code) === 6 && $notification->via($notification) === ['mail'];
    });

    $wrong = $code === '000000' ? '111111' : '000000';
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'ana@example.test', 'code' => $wrong, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertUnprocessable()->assertJsonValidationErrors('code');
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'ana@example.test', 'code' => $code, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();

    // Signed out everywhere; the new password works; the code can't be reused.
    withBearer($this, $oldToken)->getJson('/api/v1/auth/me')->assertUnauthorized();
    appLogin($this, $user->fresh(), 'customer', 'newpass123');
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'ana@example.test', 'code' => $code, 'password' => 'another123', 'password_confirmation' => 'another123'])->assertUnprocessable();
});

test('reset codes expire and lock after too many wrong tries', function () {
    Notification::fake();
    $user = User::factory()->customer()->create(['email' => 'ben@example.test']);
    $code = null;

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ben@example.test']);
    Notification::assertSentTo($user, PasswordResetCode::class, function ($notification) use (&$code) {
        $code = $notification->code;

        return true;
    });

    foreach (range(1, 5) as $attempt) {
        $this->postJson('/api/v1/auth/password/reset', ['email' => 'ben@example.test', 'code' => $code === '123456' ? '654321' : '123456', 'password' => 'newpass123', 'password_confirmation' => 'newpass123']);
        app('cache')->store()->getStore(); // keep the attempts counter
    }
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'ben@example.test', 'code' => $code, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertUnprocessable();

    // A fresh code expires after 15 minutes.
    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'ben@example.test']);
    $fresh = null;
    Notification::assertSentTo($user, PasswordResetCode::class, function ($notification) use (&$fresh) {
        $fresh = $notification->code;

        return true;
    });
    $this->travel(16)->minutes();
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'ben@example.test', 'code' => $fresh, 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertUnprocessable();
});

test('changing the password keeps this phone signed in and signs out the others', function () {
    $user = User::factory()->customer()->create();
    $here = appLogin($this, $user, 'customer');
    $elsewhere = appLogin($this, $user, 'customer');

    withBearer($this, $here)->putJson('/api/v1/account/password', ['current_password' => 'wrong', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertUnprocessable();
    withBearer($this, $here)->putJson('/api/v1/account/password', ['current_password' => 'password', 'password' => 'newpass123', 'password_confirmation' => 'newpass123'])->assertOk();

    withBearer($this, $here)->getJson('/api/v1/auth/me')->assertOk();
    withBearer($this, $elsewhere)->getJson('/api/v1/auth/me')->assertUnauthorized();
});

test('any account updates its profile and photo', function () {
    Storage::fake('public');
    $rider = RiderProfile::factory()->create()->user;
    $token = appLogin($this, $rider, 'rider');

    withBearer($this, $token)->post('/api/v1/account', ['name' => 'Pedro R.', 'email' => $rider->email, 'phone' => '09171239999', 'profile_picture' => UploadedFile::fake()->image('me.jpg', 300, 300)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.name', 'Pedro R.');

    expect($rider->fresh()->profile_picture)->not->toBeNull();
    Storage::disk('public')->assertExists($rider->fresh()->profile_picture);

    withBearer($this, $token)->postJson('/api/v1/account', ['name' => 'X', 'email' => 'not-an-email'])->assertUnprocessable()->assertJsonValidationErrors('email');
});

test('password confirmation unlocks seller finance for this token only, for 3 hours', function () {
    $seller = Shop::factory()->create()->sellerProfile->user;
    $phone = appLogin($this, $seller, 'seller');
    $tablet = appLogin($this, $seller, 'seller');

    withBearer($this, $phone)->getJson('/api/v1/seller/finance')->assertStatus(423)->assertJsonPath('code', 'password_confirmation_required');
    withBearer($this, $phone)->postJson('/api/v1/auth/confirm-password', ['password' => 'wrong'])->assertUnprocessable();
    withBearer($this, $phone)->postJson('/api/v1/auth/confirm-password', ['password' => 'password'])->assertOk()->assertJsonPath('expires_in', 10800);

    withBearer($this, $phone)->getJson('/api/v1/seller/finance')->assertOk();
    withBearer($this, $tablet)->getJson('/api/v1/seller/finance')->assertStatus(423);

    $this->travel(3)->hours();
    $this->travel(1)->minutes();
    withBearer($this, $phone)->getJson('/api/v1/seller/finance')->assertStatus(423);
});

test('account deletion explains what is blocking it, then removes personal details', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Shipping)->create();
    $customer = $order->order->customer;
    $customer->addresses()->create(['recipient_name' => 'J', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'MM', 'city' => 'QC', 'barangay' => 'X', 'street' => '1']);
    $token = appLogin($this, $customer, 'customer');

    withBearer($this, $token)->getJson('/api/v1/account/deletion')->assertOk()->assertJsonPath('data.can_delete', false)->assertJsonCount(1, 'data.blockers');
    withBearer($this, $token)->deleteJson('/api/v1/account', ['password' => 'password'])->assertUnprocessable()->assertJsonValidationErrors('account');

    $order->update(['status' => SellerOrderStatus::Completed]);
    withBearer($this, $token)->getJson('/api/v1/account/deletion')->assertJsonPath('data.can_delete', true);
    withBearer($this, $token)->deleteJson('/api/v1/account', ['password' => 'wrong'])->assertUnprocessable()->assertJsonValidationErrors('password');
    withBearer($this, $token)->deleteJson('/api/v1/account', ['password' => 'password', 'reason' => 'Moving abroad'])->assertOk();

    $deleted = User::withTrashed()->find($customer->id);
    expect($deleted->trashed())->toBeTrue()
        ->and($deleted->name)->toBe('Deleted user')
        ->and($deleted->email)->toEndWith('@deleted.invalid')
        ->and($deleted->phone)->toBeNull()
        ->and($deleted->addresses()->count())->toBe(0)
        ->and($deleted->tokens()->count())->toBe(0)
        ->and($order->fresh()->order->address)->not->toBeNull(); // the order keeps its delivery snapshot

    withBearer($this, $token)->getJson('/api/v1/auth/me')->assertUnauthorized();
    $this->postJson('/api/v1/auth/login', ['login' => $customer->email, 'password' => 'password'])->assertUnprocessable();
});

test('sellers with money and staff accounts cannot delete themselves from the app', function () {
    $shop = Shop::factory()->create();
    app(LedgerService::class)->creditAvailable($shop, '100.00');
    $token = appLogin($this, $shop->sellerProfile->user, 'seller');

    withBearer($this, $token)->getJson('/api/v1/account/deletion')->assertJsonPath('data.can_delete', false)->assertJsonFragment(['Your shop still has a balance. Withdraw it first.']);

    $admin = User::factory()->admin()->create();
    $adminToken = $admin->createToken('x')->plainTextToken;
    withBearer($this, $adminToken)->getJson('/api/v1/account/deletion')->assertJsonPath('data.can_delete', false);
});

test('phones register for push, and alerts reach the right app', function () {
    Log::spy();
    $seller = Shop::factory()->create()->sellerProfile->user;
    $token = appLogin($this, $seller, 'seller');

    withBearer($this, $token)->postJson('/api/v1/devices', ['token' => 'fcm-seller-token', 'platform' => 'android', 'app' => 'seller'])->assertCreated();
    withBearer($this, $token)->postJson('/api/v1/devices', ['token' => 'fcm-seller-token', 'platform' => 'android', 'app' => 'seller'])->assertOk(); // refresh, not duplicate
    withBearer($this, $token)->postJson('/api/v1/devices', ['token' => 'fcm-shopping-token', 'platform' => 'ios', 'app' => 'customer'])->assertCreated();
    withBearer($this, $token)->postJson('/api/v1/devices', ['token' => 'x', 'platform' => 'windows', 'app' => 'seller'])->assertUnprocessable();
    expect(DeviceToken::query()->count())->toBe(2);

    // A seller alert goes to the seller app only.
    $seller->notify(new Alert('order.completed', 'Order completed', 'You earned ₱100.', '/seller/orders/1'));
    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context) => $message === 'Push notification' && $context['app'] === 'seller' && $context['devices'] === 1 && $context['title'] === 'Order completed')->once();

    // Admin-only links aren't pushed anywhere.
    $seller->notify(new Alert('x', 'Admin thing', 'Body', '/admin/orders/1'));
    Log::shouldHaveReceived('info')->withArgs(fn ($message) => $message === 'Push notification')->once();

    withBearer($this, $token)->deleteJson('/api/v1/devices', ['token' => 'fcm-seller-token'])->assertOk();
    expect(DeviceToken::query()->count())->toBe(1);

    // Signing out everywhere also drops push registrations.
    withBearer($this, $token)->postJson('/api/v1/auth/logout-all');
    expect(DeviceToken::query()->count())->toBe(0);
});
