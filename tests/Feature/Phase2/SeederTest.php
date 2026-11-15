<?php

use App\Models\LogisticsPartner;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('the database seeder is idempotent', function () {
    $this->seed();
    $counts = fn () => [User::count(), Role::count(), Permission::count(), SystemSetting::count(), Shop::count(), LogisticsPartner::count()];
    $first = $counts();

    $this->seed();

    expect($counts())->toBe($first)
        ->and(Role::count())->toBe(5)
        ->and(User::count())->toBe(5);
});

test('demo accounts are wired to their profiles', function () {
    $this->seed();

    $seller = User::where('username', 'seller')->first();
    $rider = User::where('username', 'rider')->first();
    $logistics = User::where('username', 'logistics')->first();

    expect($seller->roleNames())->toEqualCanonicalizing(['customer', 'seller'])
        ->and($seller->sellerProfile->shop->balance)->not->toBeNull()
        ->and($rider->riderProfile->logisticsPartner->is($logistics->logisticsPartner))->toBeTrue()
        ->and($logistics->logisticsPartner->serviceAreas)->toHaveCount(1);
});

test('dashboards read their numbers from the database', function () {
    $this->withoutVite();
    $this->seed();

    $this->actingAs(User::where('username', 'admin')->first())
        ->get('/admin')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.customers', 2)
            ->where('stats.sellers', 1)
            ->where('stats.logisticsPartners', 1)
            ->where('stats.riders', 1)
        );

    $this->actingAs(User::where('username', 'seller')->first())
        ->get('/seller')
        ->assertInertia(fn (Assert $page) => $page->where('shop.name', 'Santos Home Bakery'));
});
