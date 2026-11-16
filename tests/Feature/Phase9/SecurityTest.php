<?php

use App\Enums\SellerOrderStatus;
use App\Models\BankAccount;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
});

test('every response carries the baseline security headers', function (string $method, string $url) {
    $response = $method === 'json' ? $this->getJson($url) : $this->get($url);

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Permissions-Policy', 'camera=(self), geolocation=(self), microphone=()');
})->with([
    'storefront' => ['web', '/'],
    'login page' => ['web', '/login'],
    'not found page' => ['web', '/no-such-page'],
    'redirect to login' => ['web', '/admin'],
    'public API' => ['json', '/api/v1/categories'],
    'API 401' => ['json', '/api/v1/rider/assignments'],
]);

test('HSTS is only sent over HTTPS', function () {
    $this->get('/')->assertHeaderMissing('Strict-Transport-Security');
    $this->get('https://localhost/')->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
});

test('private files are never served without a signed link', function () {
    // Real files on the private disk, as identity documents and proof photos are stored.
    Storage::disk('local')->put('verification/999/id.jpg', 'secret-id');
    Storage::disk('local')->put('delivery-proofs/999/proof.jpg', 'secret-proof');

    try {
        foreach (['verification/999/id.jpg', 'delivery-proofs/999/proof.jpg'] as $path) {
            $response = $this->get('/storage/'.$path);
            expect($response->status())->toBeIn([403, 404])
                ->and($response->getContent())->not->toContain('secret-');
        }
    } finally {
        Storage::disk('local')->deleteDirectory('verification/999');
        Storage::disk('local')->deleteDirectory('delivery-proofs/999');
    }
});

test('uploads reject scripts disguised as documents or images', function () {
    Storage::fake('local');
    Storage::fake('public');
    $customer = User::factory()->customer()->create();

    $script = UploadedFile::fake()->createWithContent('id.php', '<?php echo "pwned";');
    $this->actingAs($customer)->post('/account/verification', ['type' => 'government_id', 'file' => $script])->assertSessionHasErrors('file');

    $fakeImage = UploadedFile::fake()->createWithContent('photo.jpg', '<?php echo "pwned";');
    $this->actingAs($customer)->post('/account/profile', ['name' => $customer->name, 'email' => $customer->email, 'profile_picture' => $fakeImage])->assertSessionHasErrors('profile_picture');

    expect(Storage::disk('local')->allFiles())->toBe([])->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('registration cannot grant itself a role or status', function () {
    $this->post('/register', [
        'name' => 'Sneaky', 'username' => 'sneaky', 'email' => 'sneaky@example.test', 'phone' => '09171112222',
        'password' => 'secret123', 'password_confirmation' => 'secret123', 'terms' => true,
        'role' => 'admin', 'roles' => ['admin'], 'status' => 'active', 'is_admin' => 1,
    ])->assertRedirect('/');

    $user = User::query()->where('username', 'sneaky')->sole();
    expect($user->roleNames())->toBe(['customer']);
    $this->get('/admin')->assertForbidden();
});

test('search input cannot break or widen queries', function () {
    Product::factory()->create(['name' => 'Ube Jam']);

    $this->get("/search?q=' OR 1=1 --")->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 0));
    $this->get('/search?q=%25')->assertOk();
    $this->get('/search?min=abc&max=1;DROP TABLE products&sort=price_asc;--')->assertOk()->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 1));
    $this->getJson("/api/v1/products?q=' UNION SELECT password FROM users --")->assertOk()->assertJsonCount(0, 'data');
});

test('user-supplied text is escaped in the page payload', function () {
    $product = Product::factory()->create(['name' => '<script>alert("x")</script> Pandesal']);

    $html = $this->get("/products/{$product->slug}")->assertOk()->getContent();
    expect($html)->not->toContain('<script>alert("x")</script>');
});

test('secrets never reach the browser or the API', function () {
    $shop = Shop::factory()->create();
    $seller = $shop->sellerProfile->user;
    BankAccount::factory()->create(['shop_id' => $shop->id, 'account_number' => '001234567890', 'account_last4' => '7890']);

    $page = $this->actingAs($seller)->withSession(['auth.password_confirmed_at' => now()->unix()])->get('/seller/finance')->assertOk()->getContent();
    expect($page)->not->toContain('001234567890')->and($page)->toContain('7890');

    // Stored encrypted at rest.
    expect(DB::table('bank_accounts')->value('account_number'))->not->toContain('001234567890');

    Sanctum::actingAs($seller);
    $me = $this->getJson('/api/v1/auth/me')->assertOk()->getContent();
    expect($me)->not->toContain('password')->and($me)->not->toContain('remember_token');

    $admin = User::factory()->admin()->create();
    $usersPage = $this->actingAs($admin)->get('/admin/users')->getContent();
    expect($usersPage)->not->toContain('$2y$');
});

test('write endpoints are rate limited', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $customer = $order->order->customer;

    // Each request after the first fails validation (one open request), but all count toward the limit.
    foreach (range(1, 10) as $attempt) {
        $this->actingAs($customer)->post("/account/orders/{$order->id}/refund", ['reason' => 'Damaged']);
    }

    $this->actingAs($customer)->post("/account/orders/{$order->id}/refund", ['reason' => 'Damaged'])->assertStatus(429);
});

test('another customer cannot see or act on someone else\'s order', function () {
    $order = SellerOrder::factory()->status(SellerOrderStatus::Delivered)->create();
    $stranger = User::factory()->customer()->create();

    $this->actingAs($stranger)->get("/account/orders/{$order->id}")->assertNotFound();
    $this->actingAs($stranger)->post("/account/orders/{$order->id}/confirm")->assertNotFound();
    $this->actingAs($stranger)->post("/account/orders/{$order->id}/refund", ['reason' => 'x'])->assertNotFound();
    expect($order->fresh()->status)->toBe(SellerOrderStatus::Delivered);
});
