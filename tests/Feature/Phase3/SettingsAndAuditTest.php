<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Services\Administration\SettingsService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->admin = User::factory()->admin()->create(['username' => 'boss']);
});

test('settings pages show the stored values', function () {
    $this->actingAs($this->admin)->get('/admin/settings/finance')
        ->assertInertia(fn (Assert $page) => $page
            ->where('group', 'finance')
            ->where('values.commission_rate', '5.00')
            ->has('groups', 9));

    $this->actingAs($this->admin)->get('/admin/settings/nope')->assertNotFound();
});

test('saving settings validates, normalizes, persists and audits', function () {
    $this->actingAs($this->admin)->put('/admin/settings/payment', ['cod_enabled' => true, 'cod_max_amount' => 'lots'])
        ->assertSessionHasErrors('cod_max_amount');

    $this->actingAs($this->admin)->put('/admin/settings/payment', ['cod_enabled' => false, 'cod_max_amount' => '15000'])->assertRedirect();

    $settings = app(SettingsService::class);
    expect($settings->get('payment', 'cod_enabled'))->toBe('0')
        ->and($settings->get('payment', 'cod_max_amount'))->toBe('15000.00')
        ->and(AuditLog::where('action', 'settings.updated')->exists())->toBeTrue();
});

test('public settings changes reach the shared frontend props', function () {
    $this->actingAs($this->admin)->put('/admin/settings/general', [
        'marketplace_name' => 'Onetap QC',
        'support_email' => 'help@example.com',
        'support_phone' => '',
        'timezone' => 'Asia/Manila',
    ])->assertRedirect();

    $this->get('/')->assertInertia(fn (Assert $page) => $page->where('marketplace.name', 'Onetap QC'));
});

test('the login lockout follows the security settings', function () {
    app(SettingsService::class)->set('security', 'max_login_attempts', '3');
    User::factory()->create(['username' => 'target', 'password' => 'password']);

    foreach (range(1, 3) as $attempt) {
        $this->post('/login', ['login' => 'target', 'password' => 'wrong']);
    }

    $this->post('/login', ['login' => 'target', 'password' => 'password'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

test('api tokens expire per the api settings', function () {
    app(SettingsService::class)->set('api', 'token_expiry_days', '7');
    User::factory()->customer()->create(['username' => 'mobile', 'password' => 'password']);

    $this->postJson('/api/v1/auth/login', ['login' => 'mobile', 'password' => 'password'])->assertOk();

    $token = User::where('username', 'mobile')->first()->tokens()->first();
    expect($token->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
});

test('audit logs can be filtered by area and user', function () {
    $this->actingAs($this->admin)->post('/admin/categories', ['name' => 'Audited']);
    $this->actingAs($this->admin)->put('/admin/settings/orders', ['auto_complete_days' => 5]);

    $this->actingAs($this->admin)->get('/admin/audit-logs?action=category')
        ->assertInertia(fn (Assert $page) => $page
            ->has('logs.data', 1)
            ->where('logs.data.0.action', 'category.created')
            ->where('logs.data.0.user.username', 'boss'));

    $this->actingAs($this->admin)->get('/admin/audit-logs?search=boss')
        ->assertInertia(fn (Assert $page) => $page->has('logs.data', 2)->where('actionGroups', ['category', 'settings']));
});
