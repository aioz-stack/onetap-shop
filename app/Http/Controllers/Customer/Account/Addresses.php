<?php

namespace App\Http\Controllers\Customer\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\UserAddress;
use App\Services\Identity\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Addresses extends Controller
{
    public function __construct(private AccountService $accounts) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Customer/Account/Addresses', [
            'addresses' => AddressResource::collection($request->user()->addresses()->orderByDesc('is_default')->latest('id')->get())->resolve(),
        ]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $this->accounts->saveAddress($request->user(), $request->validated());

        return $request->query('return') === 'checkout'
            ? redirect()->route('checkout.show')->with('success', 'Address saved.')
            : back()->with('success', 'Address saved.');
    }

    public function update(AddressRequest $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $this->accounts->saveAddress($request->user(), $request->validated(), $address);

        return back()->with('success', 'Address updated.');
    }

    public function destroy(Request $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $this->accounts->deleteAddress($address);

        return back()->with('success', 'Address removed.');
    }

    public function makeDefault(Request $request, UserAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);
        $this->accounts->saveAddress($request->user(), ['is_default' => true], $address);

        return back()->with('success', 'Default address updated.');
    }

    private function authorizeAddress(Request $request, UserAddress $address): void
    {
        abort_unless($address->user_id === $request->user()->id, 404);
    }
}
