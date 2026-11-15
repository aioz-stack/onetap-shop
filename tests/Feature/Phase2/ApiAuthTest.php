<?php

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\CategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

test('a mobile client can register and receives a token', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ana Reyes',
        'username' => 'anareyes',
        'email' => 'ana@example.com',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'terms' => true,
        'device_name' => 'pixel',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.email', 'ana@example.com')
        ->assertJsonPath('data.roles', ['customer'])
        ->assertJsonStructure(['token', 'token_type']);

    expect(User::where('email', 'ana@example.com')->first()->tokens()->count())->toBe(1);
});

test('api registration returns json validation errors', function () {
    $this->postJson('/api/v1/auth/register', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'username', 'email', 'password', 'terms']);
});

test('a token issued at login authenticates /me and is revoked on logout', function () {
    User::factory()->withRole(RoleName::Seller)->create(['username' => 'seller', 'password' => 'password']);

    $token = $this->postJson('/api/v1/auth/login', ['login' => 'seller', 'password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.primary_role', 'seller')
        ->json('token');

    $this->withToken($token)->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.username', 'seller');

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

    expect(User::where('username', 'seller')->first()->tokens()->count())->toBe(0);
});

test('api login rejects bad credentials and suspended accounts', function () {
    User::factory()->create(['username' => 'good', 'password' => 'password']);
    User::factory()->suspended()->create(['username' => 'banned', 'password' => 'password']);

    $this->postJson('/api/v1/auth/login', ['login' => 'good', 'password' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('login');

    $this->postJson('/api/v1/auth/login', ['login' => 'banned', 'password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('login');
});

test('protected api routes return 401 json without a token', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJson(['message' => 'Unauthenticated.']);
});

test('a token stops working once the account is suspended', function () {
    $user = User::factory()->customer()->create();
    $token = $user->createToken('test')->plainTextToken;

    $user->update(['status' => 'suspended']);

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertForbidden();
    expect($user->tokens()->count())->toBe(0);
});

test('the public categories endpoint returns the seeded tree', function () {
    $this->seed(CategorySeeder::class);

    $this->getJson('/api/v1/categories')
        ->assertOk()
        ->assertJsonCount(8, 'data')
        ->assertJsonPath('data.0.name', 'Groceries')
        ->assertJsonCount(4, 'data.0.children');
});
