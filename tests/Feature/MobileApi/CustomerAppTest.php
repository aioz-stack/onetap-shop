<?php

use App\Enums\SellerOrderStatus;
use App\Models\Conversation;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
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
    $this->customer = User::factory()->customer()->create();
    $this->token = appLogin($this, $this->customer, 'customer');
    $this->shop = Shop::factory()->create(['name' => 'Santos Bakery']);
});

test('the home feed works signed out and signed in', function () {
    Product::factory()->count(3)->create(['shop_id' => $this->shop->id, 'sold_count' => 5]);

    $this->getJson('/api/v1/home')->assertOk()
        ->assertJsonStructure(['data' => ['banners', 'categories', 'featured', 'recommended', 'best_sellers', 'new_arrivals', 'popular_shops']])
        ->assertJsonCount(3, 'data.new_arrivals')
        ->assertJsonPath('data.new_arrivals.0.shop.name', 'Santos Bakery');

    withBearer($this, $this->token)->getJson('/api/v1/home')->assertOk()->assertJsonCount(3, 'data.best_sellers');
});

test('customers chat with a shop and the seller answers from the seller app', function () {
    $this->shop->autoReplies()->create(['type' => 'greeting', 'message' => 'Hi! How can we help?', 'is_active' => true]);
    $seller = $this->shop->sellerProfile->user;
    $sellerToken = appLogin($this, $seller, 'seller');

    // Start (idempotent), send, and get the greeting back in the same response.
    $chatId = withBearer($this, $this->token)->postJson('/api/v1/chats', ['shop_id' => $this->shop->id])->assertCreated()->assertJsonPath('data.shop.name', 'Santos Bakery')->json('data.id');
    withBearer($this, $this->token)->postJson('/api/v1/chats', ['shop_id' => $this->shop->id])->assertOk()->assertJsonPath('data.id', $chatId);

    $sent = withBearer($this, $this->token)->postJson("/api/v1/chats/{$chatId}/messages", ['body' => 'Do you have ube pandesal?'])->assertCreated()
        ->assertJsonPath('data.message.mine', true)
        ->assertJsonPath('data.auto_replies.0.body', 'Hi! How can we help?')
        ->json('data');

    // Seller app: shop inbox shows the customer and the unread chat.
    withBearer($this, $sellerToken)->getJson('/api/v1/chats?scope=shop')->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.role', 'shop')
        ->assertJsonPath('data.0.customer.name', $this->customer->name)
        ->assertJsonPath('data.0.unread', true);
    withBearer($this, $sellerToken)->postJson("/api/v1/chats/{$chatId}/messages", ['body' => 'Yes, fresh this morning!'])->assertCreated();

    // Customer polls for messages after the last one they had.
    $lastId = $sent['auto_replies'][0]['id'];
    withBearer($this, $this->token)->getJson("/api/v1/chats/{$chatId}?after_id={$lastId}")->assertOk()
        ->assertJsonCount(1, 'data.messages')
        ->assertJsonPath('data.messages.0.body', 'Yes, fresh this morning!')
        ->assertJsonPath('data.messages.0.mine', false);

    // Customer list shows the shop chat; opening it marked it read.
    withBearer($this, $this->token)->getJson('/api/v1/chats')->assertOk()->assertJsonPath('data.0.role', 'customer')->assertJsonPath('data.0.unread', false)->assertJsonPath('meta.unread_messages', 0);
});

test('chats are private to their participants', function () {
    $chatId = withBearer($this, $this->token)->postJson('/api/v1/chats', ['shop_id' => $this->shop->id])->json('data.id');
    $stranger = User::factory()->customer()->create();
    $strangerToken = appLogin($this, $stranger, 'customer');

    withBearer($this, $strangerToken)->getJson("/api/v1/chats/{$chatId}")->assertNotFound();
    withBearer($this, $strangerToken)->postJson("/api/v1/chats/{$chatId}/messages", ['body' => 'hi'])->assertNotFound();

    // A customer can't open the shop inbox, and a seller can't chat with their own shop.
    withBearer($this, $this->token)->getJson('/api/v1/chats?scope=shop')->assertForbidden();
    $sellerToken = appLogin($this, $this->shop->sellerProfile->user, 'customer');
    withBearer($this, $sellerToken)->postJson('/api/v1/chats', ['shop_id' => $this->shop->id])->assertUnprocessable()->assertJsonValidationErrors('shop');
    expect(Conversation::query()->count())->toBe(1);
});

test('customers see items to review and their reviews', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Completed)->create();
    $order->order->update(['user_id' => $this->customer->id]);
    $item = OrderItem::query()->where('seller_order_id', $order->id)->first();

    withBearer($this, $this->token)->getJson('/api/v1/reviews/pending')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.order_item_id', $item->id);
    withBearer($this, $this->token)->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 4, 'comment' => 'Masarap'])->assertCreated();

    withBearer($this, $this->token)->getJson('/api/v1/reviews/pending')->assertJsonCount(0, 'data');
    withBearer($this, $this->token)->getJson('/api/v1/reviews')->assertOk()->assertJsonPath('data.0.rating', 4)->assertJsonPath('data.0.comment', 'Masarap');
});

test('customers upload identity documents privately', function () {
    Storage::fake('local');

    withBearer($this, $this->token)->getJson('/api/v1/customer/verification')->assertOk()->assertJsonCount(0, 'data.documents')->assertJsonPath('data.types.0.value', 'government_id');
    withBearer($this, $this->token)->post('/api/v1/customer/verification', ['type' => 'government_id', 'file' => UploadedFile::fake()->image('id.jpg')], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'pending');
    withBearer($this, $this->token)->post('/api/v1/customer/verification', ['type' => 'government_id', 'file' => UploadedFile::fake()->createWithContent('id.php', '<?php')], ['Accept' => 'application/json'])->assertUnprocessable();

    expect(Storage::disk('local')->allFiles())->toHaveCount(1);
});

test('customers apply to sell from the app', function () {
    withBearer($this, $this->token)->getJson('/api/v1/customer/seller-application')->assertOk()->assertJsonPath('data.application', null);

    $application = [
        'business_name' => 'Lola Kakanin', 'business_type' => 'home_based', 'shop_name' => 'Lola Kakanin',
        'pickup_street' => '1 Rizal St', 'pickup_barangay' => 'Bagong Pag-asa', 'pickup_city' => 'Quezon City', 'pickup_province' => 'Metro Manila', 'pickup_region' => 'NCR', 'pickup_phone' => '09171230000',
    ];

    withBearer($this, $this->token)->postJson('/api/v1/customer/seller-application', $application)->assertUnprocessable()->assertJsonValidationErrors('agree');
    withBearer($this, $this->token)->postJson('/api/v1/customer/seller-application', $application + ['agree' => true])->assertCreated()->assertJsonPath('data.status', 'pending');
    withBearer($this, $this->token)->getJson('/api/v1/customer/seller-application')->assertJsonPath('data.application.status', 'pending');

    // Not approved yet, so the seller app still refuses this account.
    $this->postJson('/api/v1/auth/login', ['login' => $this->customer->email, 'password' => 'password', 'app' => 'seller'])->assertForbidden();
});
