<?php

use App\Enums\RoleName;
use App\Models\AuditLog;
use App\Models\CustomerProfile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
});

function validRegistration(array $overrides = []): array
{
    return array_merge([
        'name' => 'Ana Reyes',
        'username' => 'anareyes',
        'email' => 'ana@example.com',
        'phone' => '09171234567',
        'password' => 'secret123',
        'password_confirmation' => 'secret123',
        'terms' => true,
    ], $overrides);
}

test('a visitor can register as a customer', function () {
    $this->post('/register', validRegistration())->assertRedirect('/');

    $user = User::where('email', 'ana@example.com')->firstOrFail();

    $this->assertAuthenticatedAs($user);
    expect($user->hasRole(RoleName::Customer))->toBeTrue()
        ->and($user->role)->toBe('customer')
        ->and($user->customerProfile)->not->toBeNull()
        ->and($user->cart)->not->toBeNull()
        ->and(AuditLog::where('action', 'auth.registered')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('registration validates input', function () {
    User::factory()->create(['email' => 'taken@example.com', 'username' => 'taken']);

    $this->post('/register', validRegistration([
        'email' => 'taken@example.com',
        'username' => 'taken',
        'phone' => '12345',
        'password' => 'short',
        'password_confirmation' => 'different',
        'terms' => false,
    ]))->assertSessionHasErrors(['email', 'username', 'phone', 'password', 'terms']);

    $this->assertGuest();
});

test('registration links a valid referral code to the referrer', function () {
    $referrer = User::factory()->customer()->create();
    $profile = CustomerProfile::create(['user_id' => $referrer->id, 'referral_code' => 'FRIEND01']);

    $this->post('/register', validRegistration(['referral_code' => 'friend01']))->assertRedirect('/');

    expect(User::where('email', 'ana@example.com')->first()->customerProfile->referred_by)->toBe($profile->user_id);
});

test('users can log in with their email address', function () {
    User::factory()->customer()->create(['email' => 'juan@example.com', 'password' => 'password']);

    $this->post('/login', ['login' => 'juan@example.com', 'password' => 'password'])->assertRedirect('/');

    $this->assertAuthenticated();
});

test('a successful login records the time and an audit entry', function () {
    $user = User::factory()->customer()->create(['username' => 'juan', 'password' => 'password']);

    $this->post('/login', ['login' => 'juan', 'password' => 'password']);

    expect($user->fresh()->last_login_at)->not->toBeNull()
        ->and(AuditLog::where('action', 'auth.login')->where('user_id', $user->id)->exists())->toBeTrue();
});

test('each role lands on its own panel after login', function (RoleName $role, string $home) {
    User::factory()->withRole($role)->create(['username' => 'person', 'password' => 'password']);

    $this->post('/login', ['login' => 'person', 'password' => 'password'])->assertRedirect($home);
})->with([
    'admin' => [RoleName::Admin, '/admin'],
    'seller' => [RoleName::Seller, '/seller'],
    'logistics' => [RoleName::Logistics, '/logistics'],
    'rider' => [RoleName::Rider, '/rider'],
    'customer' => [RoleName::Customer, '/'],
]);

test('suspended users cannot log in', function () {
    User::factory()->customer()->suspended()->create(['username' => 'banned', 'password' => 'password']);

    $this->post('/login', ['login' => 'banned', 'password' => 'password'])->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('a user suspended mid-session is signed out on the next request', function () {
    $user = User::factory()->admin()->create();
    $this->actingAs($user);
    $user->update(['status' => 'suspended']);

    $this->get('/admin')->assertRedirect('/login');
    $this->assertGuest();
});

test('login is throttled after repeated failures', function () {
    User::factory()->create(['username' => 'target', 'password' => 'password']);
    RateLimiter::clear('target|127.0.0.1');

    foreach (range(1, 5) as $attempt) {
        $this->post('/login', ['login' => 'target', 'password' => 'wrong']);
    }

    // The countdown can tick from 60 to 59 seconds while the test runs, so match the message prefix.
    $this->post('/login', ['login' => 'target', 'password' => 'password'])->assertSessionHasErrors('login');
    expect(session('errors')->first('login'))->toMatch('/^Too many login attempts\. Try again in (59|60) seconds\.$/');

    $this->assertGuest();
});

test('signed-in users visiting the login page go to their panel', function () {
    $this->actingAs(User::factory()->withRole(RoleName::Seller)->create())
        ->get('/login')
        ->assertRedirect('/seller');
});
