<?php

use App\Enums\RoleName;
use App\Enums\VerificationStatus;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\VerificationDocument;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->admin()->create();
});

test('users can be filtered by role, status and search', function () {
    User::factory()->customer()->create(['name' => 'Ana Customer']);
    User::factory()->customer()->suspended()->create(['name' => 'Ben Suspended']);
    User::factory()->withRole(RoleName::Rider)->create(['name' => 'Carlo Rider']);

    $this->actingAs($this->admin)->get('/admin/users?role=customer')
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 2)->where('roleCounts.customer', 2)->where('roleCounts.rider', 1));

    $this->actingAs($this->admin)->get('/admin/users?role=customer&status=suspended')
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.name', 'Ben Suspended'));

    $this->actingAs($this->admin)->get('/admin/users?search=carlo')
        ->assertInertia(fn (Assert $page) => $page->has('users.data', 1)->where('users.data.0.roles', ['rider']));
});

test('an admin can create a staff account with several roles', function () {
    $this->actingAs($this->admin)->post('/admin/users', [
        'name' => 'Second Admin',
        'username' => 'admin2',
        'email' => 'admin2@example.com',
        'password' => 'secret123',
        'roles' => ['admin', 'customer'],
    ])->assertRedirect();

    $user = User::where('username', 'admin2')->first();

    expect($user->roleNames())->toEqualCanonicalizing(['admin', 'customer'])
        ->and($user->role)->toBe('admin')
        ->and($user->customerProfile)->not->toBeNull()
        ->and(AuditLog::where('action', 'user.created')->where('auditable_id', $user->id)->exists())->toBeTrue();
});

test('creating an account validates input', function () {
    $this->actingAs($this->admin)->post('/admin/users', ['roles' => ['wizard']])
        ->assertSessionHasErrors(['name', 'username', 'email', 'password', 'roles.0']);
});

test('updating an account syncs roles and keeps the password when blank', function () {
    $user = User::factory()->withRole(RoleName::Customer, RoleName::Seller)->create(['username' => 'maria', 'password' => 'original1']);
    $hash = $user->password;

    $this->actingAs($this->admin)->put("/admin/users/{$user->id}", [
        'name' => 'Renamed',
        'username' => $user->username,
        'email' => $user->email,
        'password' => '',
        'roles' => ['customer'],
    ])->assertRedirect("/admin/users/{$user->id}");

    $user->refresh();
    expect($user->name)->toBe('Renamed')
        ->and($user->load('roles')->roleNames())->toBe(['customer'])
        ->and($user->password)->toBe($hash);
});

test('an admin cannot remove their own admin role or suspend themselves', function () {
    $this->actingAs($this->admin)->put("/admin/users/{$this->admin->id}", [
        'name' => $this->admin->name,
        'username' => $this->admin->username ?? 'adminx',
        'email' => $this->admin->email,
        'roles' => ['customer'],
    ])->assertSessionHasErrors('roles');

    $this->actingAs($this->admin)->post("/admin/users/{$this->admin->id}/suspend", ['reason' => 'test'])
        ->assertSessionHasErrors('status');

    expect($this->admin->fresh()->hasRole(RoleName::Admin))->toBeTrue();
});

test('suspending revokes api tokens and reactivating restores access', function () {
    $user = User::factory()->customer()->create();
    $user->createToken('phone');

    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/suspend", ['reason' => 'Fraud report'])->assertRedirect();

    expect($user->fresh()->status->value)->toBe('suspended')
        ->and($user->fresh()->suspension_reason)->toBe('Fraud report')
        ->and($user->tokens()->count())->toBe(0);

    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/reactivate")->assertRedirect();
    expect($user->fresh()->status->value)->toBe('active')->and($user->fresh()->suspended_at)->toBeNull();
});

test('suspending requires a reason', function () {
    $user = User::factory()->customer()->create();

    $this->actingAs($this->admin)->post("/admin/users/{$user->id}/suspend")->assertSessionHasErrors('reason');
});

test('verification documents can be approved or rejected once', function () {
    $document = VerificationDocument::factory()->create();
    $other = VerificationDocument::factory()->create();

    $this->actingAs($this->admin)->post("/admin/verifications/{$document->id}/approve")->assertRedirect();
    expect($document->fresh()->status)->toBe(VerificationStatus::Approved)
        ->and($document->user->fresh()->email_verified_at)->not->toBeNull();

    $this->actingAs($this->admin)->post("/admin/verifications/{$document->id}/reject", ['notes' => 'late'])->assertSessionHasErrors('status');

    $this->actingAs($this->admin)->post("/admin/verifications/{$other->id}/reject")->assertSessionHasErrors('notes');
    $this->actingAs($this->admin)->post("/admin/verifications/{$other->id}/reject", ['notes' => 'Blurry photo'])->assertRedirect();
    expect($other->fresh()->status)->toBe(VerificationStatus::Rejected);
});

test('verification files stream from the private disk to admins only', function () {
    Storage::fake('local');
    $path = UploadedFile::fake()->image('id.jpg')->store('verification', 'local');
    $document = VerificationDocument::factory()->create(['path' => $path]);

    $this->actingAs($this->admin)->get("/admin/verifications/{$document->id}/file")->assertOk();
    $this->actingAs($document->user)->get("/admin/verifications/{$document->id}/file")->assertForbidden();
});
