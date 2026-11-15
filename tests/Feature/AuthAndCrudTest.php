<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

test('guests are redirected away from protected pages', function () {
    $this->get('/dashboard')->assertRedirect('/login');
    $this->get('/crud')->assertRedirect('/login');
});

test('the storefront home is public', function () {
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Customer/Home'));
});

test('the login screen renders as an inertia page', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Auth/Login'));
});

test('a user can log in with their username and password', function () {
    User::factory()->admin()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'login' => 'admin',
        'password' => 'password',
    ])->assertRedirect('/admin');

    $this->assertAuthenticated();
});

test('invalid credentials return a validation error', function () {
    User::factory()->create([
        'username' => 'admin',
        'password' => 'password',
    ]);

    $this->post('/login', [
        'login' => 'admin',
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('the dashboard route sends users to their own panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/dashboard')
        ->assertRedirect('/admin');
});

test('the users index lists users through inertia', function () {
    User::factory()->count(3)->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get('/crud')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Crud/Index')
            ->has('users.data', 4)
        );
});

test('the create, edit and show pages render through inertia', function () {
    $target = User::factory()->create(['username' => 'target']);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->get('/crud/create')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Crud/Create'));

    $this->actingAs($admin)->get("/crud/{$target->id}/edit")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Crud/Edit')
            ->where('user.id', $target->id)
        );

    $this->actingAs($admin)->get("/crud/{$target->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Crud/Show')
            ->where('user.username', 'target')
        );
});

test('an admin can create, update and delete a user', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/crud', [
        'name' => 'New Person',
        'username' => 'newperson',
        'email' => 'new@example.com',
        'role' => 'customer',
        'password' => 'secret123',
    ])->assertRedirect('/crud');

    $this->assertDatabaseHas('users', ['username' => 'newperson', 'email' => 'new@example.com']);

    $created = User::where('username', 'newperson')->firstOrFail();

    $this->actingAs($admin)->put("/crud/{$created->id}", [
        'name' => 'Renamed Person',
        'username' => 'newperson',
        'email' => 'new@example.com',
        'role' => 'customer',
    ])->assertRedirect('/crud');

    $this->assertDatabaseHas('users', ['id' => $created->id, 'name' => 'Renamed Person']);

    $this->actingAs($admin)->delete("/crud/{$created->id}")->assertRedirect('/crud');

    // Users are soft-deleted so order and finance history stays intact.
    $this->assertSoftDeleted('users', ['id' => $created->id]);
});

test('creating a user validates required fields', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post('/crud', [])
        ->assertSessionHasErrors(['name', 'username', 'email', 'role', 'password']);
});

test('non-admins cannot open the user crud', function () {
    $this->actingAs(User::factory()->customer()->create())
        ->get('/crud')
        ->assertForbidden();
});

test('a user can log out', function () {
    $this->actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('unknown routes render the inertia 404 page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertInertia(fn (Assert $page) => $page->component('Errors/NotFound'));
});
