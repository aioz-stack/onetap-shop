<?php

namespace App\Http\Controllers\Customer\Account;

use App\Http\Controllers\Controller;
use App\Services\Identity\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class Profile extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function edit(Request $request): Response
    {
        $user = $request->user()->load('customerProfile');

        return Inertia::render('Customer/Account/Profile', [
            'profile' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'phone' => $user->phone,
                'profile_picture' => $user->profile_picture,
                'email_verified' => (bool) $user->email_verified_at,
                'birthdate' => $user->customerProfile?->birthdate?->toDateString(),
                'gender' => $user->customerProfile?->gender,
                'referral_code' => $user->customerProfile?->referral_code,
                'member_since' => $user->created_at?->toIso8601String(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'gender' => ['nullable', Rule::in(['female', 'male', 'other', 'prefer_not_to_say'])],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_picture' => ['boolean'],
        ], ['phone.regex' => 'Enter a valid Philippine mobile number (e.g. 09171234567).']);

        $this->accounts->updateProfile(
            $user,
            collect($validated)->only(['name', 'email', 'phone'])->all(),
            $request->file('profile_picture'),
            $request->boolean('remove_picture'),
        );

        $user->customerProfile()->updateOrCreate([], [
            'birthdate' => $validated['birthdate'] ?? null,
            'gender' => $validated['gender'] ?? null,
        ] + ($user->customerProfile ? [] : ['referral_code' => strtoupper(Str::random(8))]));

        return back()->with('success', 'Profile saved.');
    }
}
