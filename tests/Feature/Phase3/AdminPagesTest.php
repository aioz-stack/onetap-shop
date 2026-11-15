<?php

use App\Enums\ProductStatus;
use App\Enums\RoleName;
use App\Enums\SellerOrderStatus;
use App\Enums\ShipmentStatus;
use App\Models\LogisticsPartner;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\SellerOrder;
use App\Models\SellerProfile;
use App\Models\Shipment;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('username', 'admin')->first();
});

test('every admin page renders with real data', function (string $path, string $component) {
    SellerOrder::factory()->count(2)->create();
    SellerProfile::factory()->create();
    Shipment::factory()->status(ShipmentStatus::InTransit)->create();

    $this->actingAs($this->admin)
        ->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/admin', 'Admin/Dashboard'],
    ['/admin/users', 'Admin/Users/Index'],
    ['/admin/users/create', 'Admin/Users/Form'],
    ['/admin/verifications', 'Admin/Verifications/Index'],
    ['/admin/sellers', 'Admin/Sellers/Index'],
    ['/admin/products', 'Admin/Products/Index'],
    ['/admin/categories', 'Admin/Categories/Index'],
    ['/admin/orders', 'Admin/Orders/Index'],
    ['/admin/refunds', 'Admin/Refunds/Index'],
    ['/admin/logistics/partners', 'Admin/Logistics/Partners/Index'],
    ['/admin/logistics/partners/create', 'Admin/Logistics/Partners/Create'],
    ['/admin/logistics/riders', 'Admin/Logistics/Riders'],
    ['/admin/logistics/shipments?tab=transit', 'Admin/Logistics/Shipments'],
    ['/admin/logistics/cod', 'Admin/Logistics/Cod'],
    ['/admin/logistics/cod?view=remittances', 'Admin/Logistics/Cod'],
    ['/admin/finance', 'Admin/Finance/Overview'],
    ['/admin/finance/transactions', 'Admin/Finance/Transactions'],
    ['/admin/finance/withdrawals', 'Admin/Finance/Withdrawals'],
    ['/admin/finance/reports', 'Admin/Finance/Reports'],
    ['/admin/settings', 'Admin/Settings/Edit'],
    ['/admin/settings/security', 'Admin/Settings/Edit'],
    ['/admin/audit-logs', 'Admin/AuditLogs/Index'],
]);

test('detail pages render for each record type', function () {
    $order = SellerOrder::factory()->create();
    $seller = SellerProfile::factory()->create();
    $product = Product::factory()->create();
    $partner = LogisticsPartner::first();
    $user = User::where('username', 'customer')->first();

    foreach ([
        "/admin/orders/{$order->id}" => 'Admin/Orders/Show',
        "/admin/sellers/{$seller->id}" => 'Admin/Sellers/Show',
        "/admin/products/{$product->id}" => 'Admin/Products/Show',
        "/admin/logistics/partners/{$partner->id}" => 'Admin/Logistics/Partners/Show',
        "/admin/users/{$user->id}" => 'Admin/Users/Show',
        "/admin/users/{$user->id}/edit" => 'Admin/Users/Form',
    ] as $path => $component) {
        $this->actingAs($this->admin)->get($path)->assertOk()->assertInertia(fn (Assert $page) => $page->component($component));
    }
});

test('admin pages are forbidden to every other role', function (RoleName $role) {
    $user = User::factory()->withRole($role)->create();

    foreach (['/admin/users', '/admin/sellers', '/admin/products', '/admin/orders', '/admin/finance', '/admin/settings', '/admin/audit-logs'] as $path) {
        $this->actingAs($user)->get($path)->assertForbidden();
    }

    $this->actingAs($user)->post('/admin/categories', ['name' => 'Hack'])->assertForbidden();
})->with([RoleName::Customer, RoleName::Seller, RoleName::Logistics, RoleName::Rider]);

test('the sidebar receives live work-queue counts', function () {
    SellerProfile::factory()->count(2)->create();
    Product::factory()->status(ProductStatus::UnderReview)->create();

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('adminCounts.sellers', 2)
            ->where('adminCounts.products', 1)
            ->where('adminCounts.refunds', 0));
});

test('the dashboard counts orders by status and charts recent sales', function () {
    SellerOrder::factory()->create();
    SellerOrder::factory()->status(SellerOrderStatus::Completed)->create();
    SellerOrder::factory()->status(SellerOrderStatus::Cancelled)->create();

    $this->actingAs($this->admin)
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.orders', 3)
            ->where('stats.pendingOrders', 1)
            ->where('stats.completedOrders', 1)
            ->where('stats.cancelledOrders', 1)
            ->where('stats.revenue', '15.00')
            ->has('salesChart.labels', 30)
            ->where('salesChart.orders.29', 2));
});

test('riders list filters by partner', function () {
    $other = RiderProfile::factory()->create();

    $this->actingAs($this->admin)
        ->get("/admin/logistics/riders?partner={$other->logistics_partner_id}")
        ->assertInertia(fn (Assert $page) => $page->has('riders.data', 1)->where('riders.data.0.id', $other->id));
});
