<?php

use App\Enums\SellerOrderStatus;
use App\Models\Conversation;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->shop = Shop::factory()->create(['rating_avg' => 4.5]);
    $this->product = Product::factory()->create(['shop_id' => $this->shop->id, 'rating_count' => 3]);
    Product::factory()->create(['shop_id' => $this->shop->id, 'rating_count' => 2]);
    SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['shop_id' => $this->shop->id]);
});

test('the product page shows the seller shop with its numbers', function () {
    $this->get("/products/{$this->product->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Customer/Product')
        ->where('product.shop.id', $this->shop->id)
        ->where('product.shop.slug', $this->shop->slug)
        ->has('product.shop.joined_at')
        ->where('shopStats.products', $this->shop->products()->visible()->count())
        ->where('shopStats.ratings', (int) $this->shop->products()->sum('rating_count'))
        ->where('shopStats.orders_completed', 1)
        ->where('isOwnShop', false));
});

test('chat seller from a product opens a conversation with that shop', function () {
    $customer = User::factory()->customer()->create();

    $response = $this->actingAs($customer)->post("/account/chat/shops/{$this->shop->id}");

    $conversation = Conversation::query()->where('shop_id', $this->shop->id)->sole();
    $response->assertRedirect("/account/chat/{$conversation->id}");
});

test('a seller viewing their own product is told it is their shop, not offered a chat', function () {
    $owner = $this->shop->sellerProfile->user;

    $this->actingAs($owner)->get("/products/{$this->product->slug}")->assertOk()->assertInertia(fn (Assert $page) => $page->where('isOwnShop', true));
    $this->actingAs($owner)->post("/account/chat/shops/{$this->shop->id}")->assertSessionHasErrors('shop');
});
