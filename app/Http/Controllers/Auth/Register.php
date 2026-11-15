<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Identity\RegistrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class Register extends Controller
{
    public function __construct(private RegistrationService $registrationService) {}

    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Register', ['referralCode' => strtoupper(substr((string) $request->query('ref'), 0, 20)) ?: null]);
    }

    /**
     * Register a customer account and sign them in.
     */
    public function store(RegisterRequest $request): RedirectResponse
    {
        $user = $this->registrationService->registerCustomer($request->validated());

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('home')->with('success', 'Welcome to Onetap, '.$user->name.'!');
    }
}
