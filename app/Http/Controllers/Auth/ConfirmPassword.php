<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Re-enter the password before sensitive pages (Laravel's `password.confirm` middleware).
 */
class ConfirmPassword extends Controller
{
    public function show(): Response
    {
        return Inertia::render('Auth/ConfirmPassword');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], ['password.current_password' => 'That password is incorrect.']);
        $request->session()->passwordConfirmed();

        return redirect()->intended(route($request->user()->homeRouteName()));
    }
}
