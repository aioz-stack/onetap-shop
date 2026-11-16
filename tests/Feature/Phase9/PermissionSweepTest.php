<?php

use App\Models\LogisticsPartner;
use App\Models\RiderProfile;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/*
| Phase 9 permission sweep. It reads the live route table, so a route added later without the
| right protection fails here without anyone remembering to write a test for it.
*/

/** URL prefix => the role middleware every route under it must carry. */
const WEB_AREAS = [
    'admin' => ['role:admin'],
    'seller' => ['role:seller', 'seller.shop'],
    'logistics' => ['role:logistics', 'logistics.partner'],
    'rider' => ['role:rider'],
    'account' => ['role:customer'],
    'cart' => ['role:customer'],
    'checkout' => ['role:customer'],
    'crud' => ['role:admin'],
];

const API_AREAS = [
    'api/v1/seller' => ['role:seller', 'seller.shop'],
    'api/v1/logistics' => ['role:logistics', 'logistics.partner'],
    'api/v1/rider' => ['role:rider'],
    'api/v1/customer' => ['role:customer'],
    'api/v1/cart' => ['role:customer'],
    'api/v1/orders' => ['role:customer'],
    'api/v1/order-items' => ['role:customer'],
    'api/v1/refunds' => ['role:customer'],
    'api/v1/reviews' => ['role:customer'],
];

/** Routes that are public on purpose. */
const PUBLIC_ROUTES = [
    '/', 'search', 'products/{slug}', 'shops/{slug}', 'login', 'register', '{fallbackPlaceholder}',
    'api/v1/auth/login', 'api/v1/auth/register', 'api/v1/auth/password/forgot', 'api/v1/auth/password/reset', 'api/v1/config', 'api/v1/home', 'api/v1/categories', 'api/v1/products', 'api/v1/products/{slug}', 'api/v1/shops', 'api/v1/shops/{slug}',
    'up', 'storage/{path}', 'sanctum/csrf-cookie',
];

/**
 * @return list<RouteDefinition>
 */
function appRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->reject(fn (RouteDefinition $route) => str_starts_with($route->uri(), '_') || str_starts_with((string) $route->getActionName(), 'Laravel\\') || str_contains((string) $route->getActionName(), 'Ignition'))
        ->values()
        ->all();
}

function areaOf(string $uri, array $areas): ?string
{
    foreach (array_keys($areas) as $prefix) {
        if ($uri === $prefix || str_starts_with($uri, $prefix.'/')) {
            return $prefix;
        }
    }

    return null;
}

test('every non-public route requires a signed-in, active account', function () {
    $offenders = [];

    foreach (appRoutes() as $route) {
        if (in_array($route->uri(), PUBLIC_ROUTES, true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();
        $isApi = str_starts_with($route->uri(), 'api/');
        $auth = $isApi ? in_array('auth:sanctum', $middleware, true) : in_array('auth', $middleware, true);

        if (! $auth || ! in_array('active', $middleware, true)) {
            $offenders[] = implode('|', $route->methods()).' '.$route->uri();
        }
    }

    expect($offenders)->toBe([]);
});

test('every panel and API area enforces its role', function () {
    $offenders = [];

    foreach (appRoutes() as $route) {
        $uri = $route->uri();
        $areas = str_starts_with($uri, 'api/') ? API_AREAS : WEB_AREAS;
        $area = areaOf($uri, $areas);

        if (! $area) {
            continue;
        }

        $missing = array_diff($areas[$area], $route->gatherMiddleware());

        if ($missing) {
            $offenders[] = $uri.' is missing '.implode(', ', $missing);
        }
    }

    expect($offenders)->toBe([]);
});

test('state-changing web routes are protected by CSRF and API routes are rate limited', function () {
    $unthrottled = [];

    foreach (appRoutes() as $route) {
        // Framework routes (health check, signed local-disk URLs) carry no app middleware on purpose.
        if (in_array($route->uri(), ['up', 'storage/{path}'], true)) {
            continue;
        }

        $middleware = $route->gatherMiddleware();

        if (str_starts_with($route->uri(), 'api/')) {
            if (! collect($middleware)->contains(fn ($name) => str_starts_with($name, 'throttle:'))) {
                $unthrottled[] = $route->uri();
            }

            continue;
        }

        // Web routes run the "web" group, which includes CSRF verification for POST/PUT/PATCH/DELETE.
        expect($middleware)->toContain('web');
    }

    expect($unthrottled)->toBe([]);
});

/**
 * One fully set-up user per role (sellers need an approved shop, logistics an active partner, riders a profile).
 *
 * @return array<string, User>
 */
function sweepUsers(): array
{
    return [
        'admin' => User::factory()->admin()->create(),
        'customer' => User::factory()->customer()->create(),
        'seller' => Shop::factory()->create()->sellerProfile->user,
        'logistics' => LogisticsPartner::factory()->create()->owner,
        'rider' => RiderProfile::factory()->create()->user,
    ];
}

/**
 * Which roles may open a URL area. Sellers also hold the customer role, so they may shop.
 */
function allowedRoles(string $area): array
{
    return match ($area) {
        'admin', 'crud' => ['admin'],
        'seller' => ['seller'],
        'logistics' => ['logistics'],
        'rider' => ['rider'],
        'account', 'cart', 'checkout' => ['customer', 'seller'],
    };
}

test('every page without parameters opens for its role and is refused to everyone else', function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $users = sweepUsers();
    $checked = 0;
    $failures = [];

    foreach (appRoutes() as $route) {
        $uri = $route->uri();
        $area = areaOf($uri, WEB_AREAS);

        if (! $area || ! in_array('GET', $route->methods(), true) || str_contains($uri, '{') || str_ends_with($uri, '/export')) {
            continue;
        }

        $checked++;
        $url = '/'.$uri;

        auth()->forgetGuards();
        $this->flushSession();
        $guest = $this->get($url);
        if (! $guest->isRedirect(url('/login'))) {
            $failures[] = "guest {$url} → {$guest->status()}";
        }

        foreach ($users as $role => $user) {
            auth()->forgetGuards();
            $response = $this->actingAs($user)->withSession(['auth.password_confirmed_at' => time()])->get($url);
            $allowed = in_array($role, allowedRoles($area), true);
            // Allowed: the page itself, or a redirect to another page of the app (e.g. empty-cart checkout → cart).
            $ok = $allowed
                ? $response->isOk() || ($response->isRedirect() && ! str_contains((string) $response->headers->get('Location'), '/login'))
                : $response->status() === 403;

            if (! $ok) {
                $failures[] = "{$role} {$url} → {$response->status()} (expected ".($allowed ? '200' : '403').')';
            }
        }
    }

    expect($checked)->toBeGreaterThan(60)->and($failures)->toBe([]);
});

test('every API list endpoint answers its role and refuses the others', function () {
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $users = sweepUsers();
    $roleFor = [
        'api/v1/seller' => ['seller'],
        'api/v1/logistics' => ['logistics'],
        'api/v1/rider' => ['rider'],
        'api/v1/customer' => ['customer', 'seller'],
        'api/v1/cart' => ['customer', 'seller'],
        'api/v1/orders' => ['customer', 'seller'],
        'api/v1/refunds' => ['customer', 'seller'],
        'api/v1/reviews' => ['customer', 'seller'],
    ];
    $failures = [];

    foreach (appRoutes() as $route) {
        $uri = $route->uri();
        $area = areaOf($uri, API_AREAS);

        if (! $area || ! isset($roleFor[$area]) || ! in_array('GET', $route->methods(), true) || str_contains($uri, '{')) {
            continue;
        }

        app('auth')->forgetGuards();
        if ($this->getJson('/'.$uri)->status() !== 401) {
            $failures[] = "guest {$uri}";
        }

        foreach ($users as $role => $user) {
            app('auth')->forgetGuards();
            Sanctum::actingAs($user);
            $status = $this->getJson('/'.$uri)->status();
            // Seller finance additionally needs a recent password confirmation on the token (423 until then).
            $allowed = in_array($role, $roleFor[$area], true);
            $expected = ! $allowed ? 403 : (str_starts_with($uri, 'api/v1/seller/finance') ? 423 : 200);

            if ($status !== $expected) {
                $failures[] = "{$role} {$uri} → {$status} (expected {$expected})";
            }
        }
    }

    expect($failures)->toBe([]);
});

test('suspended accounts are locked out of web and API', function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $seller = Shop::factory()->create()->sellerProfile->user;
    $seller->update(['status' => 'suspended']);

    $this->actingAs($seller)->get('/seller')->assertRedirect('/login');
    $this->assertGuest();

    Sanctum::actingAs($seller->fresh());
    $this->getJson('/api/v1/seller/dashboard')->assertForbidden();
});

test('a rider whose partner is suspended, and a seller whose shop is pending, are refused their panels', function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);

    $partner = LogisticsPartner::factory()->create(['status' => 'suspended']);
    $this->actingAs($partner->owner)->get('/logistics')->assertForbidden();

    $shop = Shop::factory()->create();
    $shop->sellerProfile->update(['status' => 'pending']);
    $this->actingAs($shop->sellerProfile->user)->get('/seller')->assertForbidden();
});

test('the starter user screen follows the same safety rules as Admin → Users', function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->admin()->create(['username' => 'sweepadmin']);
    $otherAdmin = User::factory()->admin()->create();
    $customer = User::factory()->customer()->create(['username' => 'sweepcustomer']);

    // Admins (including yourself) cannot be deleted from this screen.
    $this->actingAs($admin)->delete("/crud/{$admin->id}")->assertSessionHas('error');
    $this->actingAs($admin)->delete("/crud/{$otherAdmin->id}")->assertSessionHas('error');
    expect($otherAdmin->fresh())->not->toBeNull();

    // Weak passwords are refused.
    $this->actingAs($admin)->post('/crud', ['name' => 'X', 'username' => 'weak', 'email' => 'weak@example.test', 'role' => 'customer', 'password' => 'abc123'])->assertSessionHasErrors('password');

    // Changing the role replaces it instead of piling roles up, and you cannot demote yourself.
    $this->actingAs($admin)->put("/crud/{$customer->id}", ['name' => $customer->name, 'username' => $customer->username, 'email' => $customer->email, 'role' => 'rider'])->assertSessionHasNoErrors();
    expect($customer->fresh()->roleNames())->toBe(['rider']);
    $this->actingAs($admin)->put("/crud/{$admin->id}", ['name' => $admin->name, 'username' => $admin->username, 'email' => $admin->email, 'role' => 'customer'])->assertSessionHasErrors('roles');

    // Deletes are audited.
    $this->actingAs($admin)->delete("/crud/{$customer->id}")->assertSessionHasNoErrors();
    $this->assertSoftDeleted('users', ['id' => $customer->id]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'user.deleted', 'auditable_id' => $customer->id]);
});
