<?php

use App\Enums\SellerStatus;
use App\Enums\ShopStatus;
use App\Models\Shop;
use App\Models\User;
use App\Services\Communication\ChatService;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->customer = User::factory()->customer()->create(['password' => 'password1']);
});

function addressData(array $overrides = []): array
{
    return array_merge(['recipient_name' => 'Juan', 'phone' => '09171234567', 'region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Pasig', 'barangay' => 'Kapitolyo', 'street' => '5 Elm St'], $overrides);
}

test('every account page renders', function (string $path, string $component) {
    $this->actingAs($this->customer)->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/account/profile', 'Customer/Account/Profile'],
    ['/account/addresses', 'Customer/Account/Addresses'],
    ['/account/orders', 'Customer/Account/Orders'],
    ['/account/cancellations', 'Customer/Account/Cancellations'],
    ['/account/refunds', 'Customer/Account/Refunds'],
    ['/account/reviews', 'Customer/Account/Reviews'],
    ['/account/notifications', 'Customer/Account/Notifications'],
    ['/account/chat', 'Customer/Account/Chat'],
    ['/account/security', 'Customer/Account/Security'],
    ['/account/verification', 'Customer/Account/Verification'],
    ['/account/sell', 'Customer/Account/SellerApplication'],
]);

test('profiles update with a validated photo', function () {
    Storage::fake('public');

    $this->actingAs($this->customer)->post('/account/profile', ['name' => 'New Name', 'email' => 'new@example.com', 'profile_picture' => UploadedFile::fake()->create('virus.exe', 10)])
        ->assertSessionHasErrors('profile_picture');

    $this->actingAs($this->customer)->post('/account/profile', ['name' => 'New Name', 'email' => 'new@example.com', 'gender' => 'female', 'profile_picture' => UploadedFile::fake()->image('me.jpg', 200, 200)])
        ->assertSessionHasNoErrors();

    $user = $this->customer->fresh();
    expect($user->name)->toBe('New Name')->and($user->email_verified_at)->toBeNull()->and($user->customerProfile->gender)->toBe('female');
    Storage::disk('public')->assertExists($user->profile_picture);
});

test('addresses keep exactly one default', function () {
    $this->actingAs($this->customer)->post('/account/addresses', addressData(['label' => 'Home']));
    $this->actingAs($this->customer)->post('/account/addresses', addressData(['label' => 'Office']));
    [$home, $office] = $this->customer->addresses()->oldest('id')->get();
    expect($home->is_default)->toBeTrue()->and($office->is_default)->toBeFalse();

    $this->actingAs($this->customer)->post("/account/addresses/{$office->id}/default");
    expect($home->fresh()->is_default)->toBeFalse()->and($office->fresh()->is_default)->toBeTrue();

    $this->actingAs($this->customer)->put("/account/addresses/{$office->id}", addressData(['label' => 'Office', 'is_default' => false]));
    expect($office->fresh()->is_default)->toBeTrue();

    $this->actingAs($this->customer)->delete("/account/addresses/{$office->id}");
    expect($home->fresh()->is_default)->toBeTrue();

    $this->actingAs(User::factory()->customer()->create())->delete("/account/addresses/{$home->id}")->assertNotFound();
    $this->actingAs($this->customer)->post('/account/addresses', addressData(['phone' => '123']))->assertSessionHasErrors('phone');
});

test('changing the password requires the current one and signs out other sessions', function () {
    DB::table('sessions')->insert(['id' => 'other-device', 'user_id' => $this->customer->id, 'payload' => '', 'last_activity' => time()]);
    $this->customer->createToken('phone');

    $this->actingAs($this->customer)->put('/account/security/password', ['current_password' => 'wrong', 'password' => 'newpass12', 'password_confirmation' => 'newpass12'])->assertSessionHasErrors('current_password');
    $this->actingAs($this->customer)->put('/account/security/password', ['current_password' => 'password1', 'password' => 'newpass12', 'password_confirmation' => 'newpass12'])->assertSessionHasNoErrors();

    expect(Hash::check('newpass12', $this->customer->fresh()->password))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'other-device')->exists())->toBeFalse()
        ->and($this->customer->tokens()->count())->toBe(0);
});

test('verification uploads go to the private disk and wait for review', function () {
    Storage::fake('local');

    $this->actingAs($this->customer)->post('/account/verification', ['type' => 'government_id', 'file' => UploadedFile::fake()->create('id.docx', 10)])->assertSessionHasErrors('file');
    $this->actingAs($this->customer)->post('/account/verification', ['type' => 'government_id', 'file' => UploadedFile::fake()->image('id.jpg')])->assertSessionHasNoErrors();
    $this->actingAs($this->customer)->post('/account/verification', ['type' => 'government_id', 'file' => UploadedFile::fake()->image('id2.jpg')])->assertSessionHasErrors('type');

    $document = $this->customer->verificationDocuments()->first();
    Storage::disk('local')->assertExists($document->path);
    expect($document->status->value)->toBe('pending');
});

test('customers can apply to sell, and approval opens the pending shop', function () {
    $this->seed(CategorySeeder::class);
    $application = [
        'business_name' => 'Nena Store', 'business_type' => 'home_based', 'shop_name' => 'Aling Nena', 'pickup_street' => '1 Main',
        'pickup_barangay' => 'San Roque', 'pickup_city' => 'Marikina', 'pickup_province' => 'Metro Manila', 'pickup_region' => 'NCR', 'pickup_phone' => '09181234567', 'agree' => true,
    ];

    $this->actingAs($this->customer)->post('/account/sell', ['agree' => false] + $application)->assertSessionHasErrors('agree');
    $this->actingAs($this->customer)->post('/account/sell', $application)->assertSessionHasNoErrors();
    $this->actingAs($this->customer)->post('/account/sell', $application)->assertSessionHasErrors('business_name');

    $profile = $this->customer->fresh()->sellerProfile;
    expect($profile->status)->toBe(SellerStatus::Pending)->and($profile->shop->status)->toBe(ShopStatus::Pending)->and($profile->shop->slug)->toBe('aling-nena');

    $this->actingAs(User::factory()->admin()->create())->post("/admin/sellers/{$profile->id}/approve");
    expect($profile->shop->fresh()->status)->toBe(ShopStatus::Active)->and($this->customer->fresh()->hasRole('seller'))->toBeTrue();
});

test('customers can chat with a shop, get the greeting, and outsiders cannot read it', function () {
    $shop = Shop::factory()->create();
    $shop->autoReplies()->create(['type' => 'greeting', 'message' => 'Hello from the shop!']);
    $owner = $shop->sellerProfile->user;

    $this->actingAs($this->customer)->post("/account/chat/shops/{$shop->id}")->assertRedirect();
    $conversation = $this->customer->conversations()->first();
    $this->actingAs($this->customer)->post("/account/chat/shops/{$shop->id}")->assertRedirect("/account/chat/{$conversation->id}");

    $this->actingAs($this->customer)->post("/account/chat/{$conversation->id}", ['body' => 'Do you deliver on Sunday?'])->assertRedirect();
    $this->actingAs($this->customer)->post("/account/chat/{$conversation->id}", ['body' => 'And holidays?']);

    expect($conversation->messages()->count())->toBe(3)
        ->and($conversation->messages()->where('is_auto_reply', true)->value('body'))->toBe('Hello from the shop!')
        ->and(app(ChatService::class)->unreadCount($this->customer))->toBe(0);

    $this->actingAs($this->customer)->get("/account/chat/{$conversation->id}")->assertInertia(fn (Assert $page) => $page->has('active.messages', 3)->where('active.messages.1.auto', true));
    $this->actingAs(User::factory()->customer()->create())->get("/account/chat/{$conversation->id}")->assertNotFound();
    $this->actingAs($owner)->get('/seller')->assertOk();
});

test('the api manages profile and addresses', function () {
    $token = $this->customer->createToken('phone')->plainTextToken;

    $this->withToken($token)->postJson('/api/v1/customer/addresses', addressData())->assertCreated()->assertJsonPath('data.is_default', true);
    $this->withToken($token)->getJson('/api/v1/customer/addresses')->assertOk()->assertJsonCount(1, 'data');
    $this->withToken($token)->putJson('/api/v1/customer/profile', ['name' => 'Api Name', 'email' => $this->customer->email])->assertOk()->assertJsonPath('data.name', 'Api Name');
    $this->withToken($token)->postJson('/api/v1/customer/addresses', addressData(['phone' => 'x']))->assertUnprocessable()->assertJsonValidationErrors('phone');
});

test('login returns to the requested page only when the role can open it', function () {
    $this->get('/admin')->assertRedirect('/login');
    $this->post('/login', ['login' => $this->customer->email, 'password' => 'password1'])->assertRedirect('/');

    auth()->logout();
    $this->get('/account/orders')->assertRedirect('/login');
    $this->post('/login', ['login' => $this->customer->email, 'password' => 'password1'])->assertRedirect('/account/orders');
});
