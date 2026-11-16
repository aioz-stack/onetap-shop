<?php

use App\Models\User;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Mobile API: sign in like an app does (POST /api/v1/auth/login with `app`) and return the bearer token.
 */
function appLogin($test, User $user, string $app, string $password = 'password'): string
{
    app('auth')->forgetGuards();

    return $test->postJson('/api/v1/auth/login', ['login' => $user->email, 'password' => $password, 'app' => $app, 'device_name' => 'Test phone'])
        ->assertOk()
        ->json('token');
}

/**
 * Mobile API: send the next request with this bearer token (fresh guard, like a separate app request).
 */
function withBearer($test, string $token)
{
    app('auth')->forgetGuards();

    return $test->withToken($token);
}
