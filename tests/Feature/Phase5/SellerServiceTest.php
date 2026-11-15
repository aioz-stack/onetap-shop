<?php

use App\Enums\SellerOrderStatus;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
use App\Services\Catalog\ReviewService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
    $this->customer = User::factory()->customer()->create();
});

function openChat($test): Conversation
{
    $test->actingAs($test->customer)->post("/account/chat/shops/{$test->shop->id}");

    return $test->customer->conversations()->first();
}

test('keyword and away auto-replies answer customers', function () {
    $this->actingAs($this->seller)->post('/seller/auto-replies', ['type' => 'keyword', 'keyword' => 'delivery', 'message' => 'We deliver daily 8am-6pm.', 'is_active' => true])->assertSessionHasNoErrors();
    $this->actingAs($this->seller)->post('/seller/auto-replies', ['type' => 'offline', 'message' => 'We are away until Monday.', 'is_active' => true]);
    $this->actingAs($this->seller)->post('/seller/auto-replies', ['type' => 'offline', 'message' => 'Dup'])->assertSessionHasErrors('type');
    $this->actingAs($this->seller)->post('/seller/auto-replies', ['type' => 'keyword', 'message' => 'No keyword'])->assertSessionHasErrors('keyword');

    $conversation = openChat($this);
    $this->actingAs($this->customer)->post("/account/chat/{$conversation->id}", ['body' => 'When is DELIVERY?']);
    expect($conversation->messages()->where('is_auto_reply', true)->pluck('body')->all())->toBe(['We deliver daily 8am-6pm.']);

    $this->shop->update(['vacation_mode' => true]);
    $this->actingAs($this->customer)->post("/account/chat/{$conversation->id}", ['body' => 'Hello?']);
    expect($conversation->messages()->latest('id')->value('body'))->toBe('We are away until Monday.');
});

test('sellers read and answer chats from their inbox only', function () {
    $conversation = openChat($this);
    $this->actingAs($this->customer)->post("/account/chat/{$conversation->id}", ['body' => 'Is the cake eggless?']);

    $this->actingAs($this->seller)->get('/seller/chat')->assertInertia(fn (Assert $page) => $page->has('conversations', 1)->where('conversations.0.unread', true)->where('sellerCounts.messages', 1));
    $this->actingAs($this->seller)->get("/seller/chat/{$conversation->id}")->assertInertia(fn (Assert $page) => $page->where('active.customer', $this->customer->name));
    $this->actingAs($this->seller)->post("/seller/chat/{$conversation->id}", ['body' => 'Yes, it is!'])->assertRedirect();

    expect($conversation->messages()->latest('id')->value('user_id'))->toBe($this->seller->id);

    $other = Shop::factory()->create()->sellerProfile->user;
    $this->actingAs($other)->get("/seller/chat/{$conversation->id}")->assertNotFound();
});

test('sellers reply to reviews of their own products', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['shop_id' => $this->shop->id]);
    $item = $order->items()->first();
    $review = app(ReviewService::class)->create($item, $order->order->customer, 5, 'Great!');

    $this->actingAs($this->seller)->get('/seller/reviews?filter=unanswered')->assertInertia(fn (Assert $page) => $page->has('reviews.data', 1)->where('summary.unanswered', 1));
    $this->actingAs($this->seller)->post("/seller/reviews/{$review->id}/reply", ['reply' => 'Salamat po!'])->assertSessionHasNoErrors();
    expect($review->fresh()->seller_reply)->toBe('Salamat po!');

    $this->get("/products/{$item->product->slug}")->assertInertia(fn (Assert $page) => $page->where('reviews.data.0.seller_reply', 'Salamat po!'));
    $this->actingAs(Shop::factory()->create()->sellerProfile->user)->post("/seller/reviews/{$review->id}/reply", ['reply' => 'Hijack'])->assertNotFound();
});

test('shop settings update the profile, branding and vacation mode', function () {
    Storage::fake('public');

    $this->actingAs($this->seller)->put('/seller/shop', ['description' => 'New description', 'pickup_street' => '1 Main', 'pickup_barangay' => 'B', 'pickup_city' => 'Pasig', 'pickup_province' => 'MM', 'pickup_region' => 'NCR', 'pickup_phone' => 'bad'])->assertSessionHasErrors('pickup_phone');
    $this->actingAs($this->seller)->put('/seller/shop', ['description' => 'New description', 'pickup_street' => '1 Main', 'pickup_barangay' => 'B', 'pickup_city' => 'Pasig', 'pickup_province' => 'MM', 'pickup_region' => 'NCR', 'pickup_phone' => '09181234567'])->assertSessionHasNoErrors();

    $this->actingAs($this->seller)->post('/seller/shop/branding', ['logo' => UploadedFile::fake()->image('logo.png', 300, 300), 'template' => 'spotlight', 'accent_color' => '#ff0000', 'announcement' => 'Sale today!'])->assertSessionHasNoErrors();
    $shop = $this->shop->fresh();
    Storage::disk('public')->assertExists($shop->logo);
    expect($shop->template)->toBe('spotlight')->and($shop->decoration['announcement'])->toBe('Sale today!')->and($shop->pickup_city)->toBe('Pasig');

    $this->get("/shops/{$shop->slug}")->assertInertia(fn (Assert $page) => $page->where('shop.template', 'spotlight')->where('shop.decoration.accent_color', '#ff0000'));

    $this->actingAs($this->seller)->put('/seller/shop/vacation', ['vacation_mode' => true, 'vacation_message' => 'Back Monday'])->assertSessionHasNoErrors();
    $variation = Product::factory()->create(['shop_id' => $shop->id])->variations()->first();
    $this->actingAs($this->customer)->post('/cart/items', ['variation_id' => $variation->id, 'quantity' => 1])->assertSessionHasErrors('variation_id');
});

test('the referral page lists customers who joined with the code', function () {
    $code = $this->seller->customerProfile()->firstOrCreate([], ['referral_code' => 'SANTOS01'])->referral_code;

    $this->get("/register?ref={$code}")->assertInertia(fn (Assert $page) => $page->where('referralCode', 'SANTOS01'));
    $this->post('/register', ['name' => 'Friend', 'username' => 'friend1', 'email' => 'f@example.com', 'password' => 'secret123', 'password_confirmation' => 'secret123', 'referral_code' => $code, 'terms' => true]);
    auth()->logout();

    $this->actingAs($this->seller)->get('/seller/referrals')->assertInertia(fn (Assert $page) => $page->where('code', 'SANTOS01')->has('referred', 1)->where('referred.0.name', 'Friend'));
});
