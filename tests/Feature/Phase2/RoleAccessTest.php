<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

dataset('panels', [
    'admin' => ['/admin', RoleName::Admin, 'Admin/Dashboard'],
    'seller' => ['/seller', RoleName::Seller, 'Seller/Dashboard'],
    'logistics' => ['/logistics', RoleName::Logistics, 'Logistics/Dashboard'],
    'rider' => ['/rider', RoleName::Rider, 'Rider/Dashboard'],
]);

test('each panel opens for its own role', function (string $path, RoleName $role, string $component) {
    $this->actingAs(User::factory()->withRole($role)->create())
        ->get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with('panels');

test('each panel is forbidden to every other role', function (string $path, RoleName $role) {
    foreach (RoleName::cases() as $otherRole) {
        if ($otherRole === $role) {
            continue;
        }

        $this->actingAs(User::factory()->withRole($otherRole)->create())
            ->get($path)
            ->assertForbidden()
            ->assertInertia(fn (Assert $page) => $page->component('Errors/Error')->where('status', 403));
    }
})->with('panels');

test('guests are sent to login from every panel', function (string $path) {
    $this->get($path)->assertRedirect('/login');
})->with('panels');

test('a user with several roles can open each of their panels', function () {
    $user = User::factory()->withRole(RoleName::Customer, RoleName::Seller)->create();

    $this->actingAs($user)->get('/seller')->assertOk();
    $this->actingAs($user)->get('/')->assertOk();
    $this->actingAs($user)->get('/admin')->assertForbidden();

    expect($user->fresh()->homeRouteName())->toBe('seller.dashboard');
});

test('permissions follow the role assignments', function () {
    $admin = User::factory()->admin()->create();
    $seller = User::factory()->withRole(RoleName::Seller)->create();

    expect($admin->hasPermission('sellers.approve'))->toBeTrue()
        ->and($seller->hasPermission('sellers.approve'))->toBeFalse()
        ->and($seller->hasPermission('seller.products.manage'))->toBeTrue();
});

test('removing a role updates the primary role column', function () {
    $user = User::factory()->withRole(RoleName::Customer, RoleName::Seller)->create();
    expect($user->fresh()->role)->toBe('seller');

    $user->removeRole(RoleName::Seller);

    expect($user->fresh()->role)->toBe('customer')
        ->and($user->fresh()->hasRole(RoleName::Seller))->toBeFalse();
});

test('shared inertia props expose the user roles and home', function () {
    $this->actingAs(User::factory()->withRole(RoleName::Rider)->create())
        ->get('/rider')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.role', 'rider')
            ->where('auth.user.roles', ['rider'])
            ->where('auth.user.home', route('rider.dashboard'))
        );
});
