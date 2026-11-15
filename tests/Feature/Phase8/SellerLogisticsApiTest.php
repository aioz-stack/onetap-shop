<?php

use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RiderProfile;
use App\Models\SellerOrder;
use App\Models\Shipment;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Storage::fake('public');
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
});

test('sellers manage products over the API', function () {
    $shop = Shop::factory()->create();
    $category = ProductCategory::factory()->create();
    Sanctum::actingAs($shop->sellerProfile->user);

    $this->getJson('/api/v1/seller/dashboard')->assertOk()->assertJsonPath('data.shop.id', $shop->id)->assertJsonPath('data.stats.products', 0);

    $this->post('/api/v1/seller/products', ['name' => 'Ub'], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors(['name', 'product_category_id', 'variations', 'images']);

    $created = $this->post('/api/v1/seller/products', [
        'name' => 'Ube Pandesal',
        'product_category_id' => $category->id,
        'variations' => [['label' => 'Pack of 6', 'price' => '90.00', 'stock' => 20, 'is_active' => true]],
        'images' => [UploadedFile::fake()->image('ube.jpg', 600, 600)],
        'submit' => true,
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.status', 'under_review')->json('data');

    $this->getJson('/api/v1/seller/products?status=under_review')->assertOk()->assertJsonCount(1, 'data');

    $variationId = $created['variations'][0]['id'];
    $this->post("/api/v1/seller/products/{$created['id']}", [
        'name' => 'Ube Pandesal (fresh)',
        'product_category_id' => $category->id,
        'variations' => [['id' => $variationId, 'label' => 'Pack of 6', 'price' => '95.00', 'stock' => 20, 'is_active' => true]],
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.name', 'Ube Pandesal (fresh)');

    $this->patchJson("/api/v1/seller/variations/{$variationId}/stock", ['stock' => 35])->assertOk()->assertJsonPath('data.stock', 35);

    // Another shop's product is invisible.
    $foreign = Product::factory()->create();
    $this->getJson("/api/v1/seller/products/{$foreign->id}")->assertNotFound();
    $this->patchJson("/api/v1/seller/variations/{$foreign->variations()->first()->id}/stock", ['stock' => 1])->assertNotFound();
    $this->deleteJson("/api/v1/seller/products/{$foreign->id}")->assertNotFound();

    $this->deleteJson("/api/v1/seller/products/{$created['id']}")->assertNoContent();
    expect(Product::query()->find($created['id']))->toBeNull();
});

test('sellers process orders over the API', function () {
    $partner = LogisticsPartner::factory()->create();
    $partner->serviceAreas()->create(['region' => 'NCR', 'province' => 'Metro Manila', 'city' => 'Quezon City', 'delivery_fee' => 59]);
    $order = SellerOrder::factory()->create();
    $order->order->address()->update(['city' => 'Quezon City']);
    Sanctum::actingAs($order->shop->sellerProfile->user);

    $this->getJson('/api/v1/seller/orders?tab=to_ship')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.reference', $order->reference);
    $this->getJson("/api/v1/seller/orders/{$order->id}")->assertOk()->assertJsonPath('data.address.city', 'Quezon City');

    $this->postJson("/api/v1/seller/orders/{$order->id}/status", ['action' => 'accept'])->assertOk()->assertJsonPath('data.status', 'processing');
    $this->postJson("/api/v1/seller/orders/{$order->id}/status", ['action' => 'ready'])->assertOk()->assertJsonPath('data.status', 'ready_for_pickup');
    expect($order->fresh()->shipment->logistics_partner_id)->toBe($partner->id);

    $this->postJson("/api/v1/seller/orders/{$order->id}/status", ['action' => 'cancel'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $this->getJson('/api/v1/seller/orders/'.SellerOrder::factory()->create()->id)->assertNotFound();

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson('/api/v1/seller/orders')->assertForbidden();
});

test('logistics partners dispatch and manage riders over the API', function () {
    $partner = LogisticsPartner::factory()->create();
    $rider = RiderProfile::factory()->create(['logistics_partner_id' => $partner->id]);
    $shipment = Shipment::factory()->create(['logistics_partner_id' => $partner->id]);
    $foreignShipment = Shipment::factory()->create();
    $foreignRider = RiderProfile::factory()->create();
    Sanctum::actingAs($partner->owner);

    $this->getJson('/api/v1/logistics/dashboard')->assertOk()->assertJsonPath('data.shipments.incoming', 1);
    $this->getJson('/api/v1/logistics/shipments')->assertOk()->assertJsonCount(1, 'data');

    $this->postJson("/api/v1/logistics/shipments/{$shipment->id}/assign", ['rider_id' => $foreignRider->id])->assertUnprocessable();
    $this->postJson("/api/v1/logistics/shipments/{$shipment->id}/assign", ['rider_id' => $rider->id])->assertOk()->assertJsonPath('data.status', 'assigned')->assertJsonPath('data.rider.id', $rider->id);
    $this->postJson("/api/v1/logistics/shipments/{$shipment->id}/unassign", ['reason' => 'Rain'])->assertOk()->assertJsonPath('data.status', 'pending_pickup');
    $this->getJson("/api/v1/logistics/shipments/{$foreignShipment->id}")->assertNotFound();

    $created = $this->postJson('/api/v1/logistics/riders', [
        'name' => 'Ramon Cruz', 'username' => 'ramon', 'email' => 'ramon@example.test', 'phone' => '09170001111',
        'password' => 'secret123', 'vehicle_type' => 'motorcycle', 'plate_number' => 'XYZ 999',
    ])->assertCreated()->assertJsonPath('data.status', 'pending')->json('data');

    $this->postJson("/api/v1/logistics/riders/{$created['id']}/status", ['action' => 'approve'])->assertOk()->assertJsonPath('data.status', 'active');
    $this->putJson("/api/v1/logistics/riders/{$created['id']}", ['name' => 'Ramon D. Cruz', 'email' => 'ramon@example.test', 'phone' => '09170001111', 'vehicle_type' => 'e_bike'])->assertOk()->assertJsonPath('data.vehicle_type', 'e_bike');
    $this->getJson('/api/v1/logistics/riders')->assertOk()->assertJsonCount(2, 'data');
    $this->getJson("/api/v1/logistics/riders/{$foreignRider->id}")->assertNotFound();
    $this->postJson("/api/v1/logistics/riders/{$foreignRider->id}/status", ['action' => 'suspend'])->assertNotFound();
});

test('customers request refunds and review items over the API', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $customer = $order->order->customer;
    Sanctum::actingAs($customer);

    $this->postJson("/api/v1/orders/{$order->id}/refund", ['reason' => 'Damaged box', 'amount' => '999999'])->assertUnprocessable()->assertJsonValidationErrors('amount');
    $this->postJson("/api/v1/orders/{$order->id}/refund", ['reason' => 'Damaged box'])->assertCreated()->assertJsonPath('data.status', 'requested');
    $this->getJson('/api/v1/refunds')->assertOk()->assertJsonCount(1, 'data');

    $completed = SellerOrder::factory()->status(SellerOrderStatus::Completed)->create(['order_id' => $order->order_id]);
    $item = OrderItem::query()->where('seller_order_id', $completed->id)->first();
    $this->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 6])->assertUnprocessable()->assertJsonValidationErrors('rating');
    $this->postJson("/api/v1/order-items/{$item->id}/review", ['rating' => 5, 'comment' => 'Masarap!'])->assertCreated()->assertJsonPath('data.rating', 5);

    // Other customers cannot touch this order.
    Sanctum::actingAs(User::factory()->customer()->create());
    $this->postJson("/api/v1/orders/{$order->id}/refund", ['reason' => 'x'])->assertNotFound();
});

test('shipment statuses stay consistent when driven from the API', function () {
    $partner = LogisticsPartner::factory()->create();
    $rider = RiderProfile::factory()->create(['logistics_partner_id' => $partner->id]);
    $shipment = Shipment::factory()->create(['logistics_partner_id' => $partner->id]);

    Sanctum::actingAs($partner->owner);
    $this->postJson("/api/v1/logistics/shipments/{$shipment->id}/assign", ['rider_id' => $rider->id])->assertOk();

    Sanctum::actingAs($rider->user);
    $this->postJson('/api/v1/rider/availability', ['action' => 'sign_in'])->assertOk();
    foreach (['accepted', 'arrived_at_pickup', 'picked_up'] as $status) {
        $this->postJson("/api/v1/rider/deliveries/{$shipment->id}/status", ['status' => $status])->assertOk();
    }

    expect($shipment->fresh()->status)->toBe(ShipmentStatus::PickedUp)
        ->and($shipment->sellerOrder->fresh()->status)->toBe(SellerOrderStatus::Shipping);

    Sanctum::actingAs($shipment->sellerOrder->shop->sellerProfile->user);
    $this->getJson("/api/v1/seller/orders/{$shipment->seller_order_id}")->assertJsonPath('data.shipment.status', 'picked_up');
});
