<?php

namespace App\Http\Controllers\Customer\Account;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Models\ShopCategory;
use App\Services\Identity\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Account security, identity verification and the "sell on Onetap" application.
 */
class Security extends Controller
{
    public const DOCUMENT_TYPES = ['government_id' => 'Government ID', 'selfie_with_id' => 'Selfie holding your ID', 'business_permit' => 'Business permit', 'dti_registration' => 'DTI / SEC registration'];

    public function __construct(private AccountService $accounts) {}

    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Customer/Account/Security', [
            'sessions' => DB::table('sessions')
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity')
                ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
                ->map(fn ($session) => [
                    'current' => $session->id === $request->session()->getId(),
                    'ip_address' => $session->ip_address,
                    'device' => mb_strimwidth((string) $session->user_agent, 0, 90, '…'),
                    'last_active' => date(DATE_ATOM, $session->last_activity),
                ]),
            'tokens' => $user->tokens()->count(),
            'lastLogin' => $user->last_login_at?->toIso8601String(),
        ]);
    }

    public function password(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $this->accounts->changePassword($request->user(), $validated['current_password'], $validated['password'], $request->session()->getId());

        return back()->with('success', 'Password changed. Other devices were signed out.');
    }

    public function signOutOthers(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $count = $this->accounts->signOutOtherDevices($request->user(), $request->session()->getId());

        return back()->with('success', "Signed out of {$count} other session(s).");
    }

    public function verification(Request $request): Response
    {
        return Inertia::render('Customer/Account/Verification', [
            'documents' => $request->user()->verificationDocuments()->latest('id')->get()->map(fn ($document) => [
                'id' => $document->id,
                'type' => $document->type,
                'type_label' => self::DOCUMENT_TYPES[$document->type] ?? $document->type,
                'status' => $document->status->value,
                'notes' => $document->status === VerificationStatus::Rejected ? $document->notes : null,
                'created_at' => $document->created_at?->toIso8601String(),
            ]),
            'types' => collect(self::DOCUMENT_TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]);
    }

    public function uploadVerification(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(self::DOCUMENT_TYPES))],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $this->accounts->uploadVerification($request->user(), $validated['type'], $request->file('file'));

        return back()->with('success', 'Document uploaded. We will review it within 1–2 business days.');
    }

    public function sellerApplication(Request $request): Response
    {
        $profile = $request->user()->sellerProfile?->load('shop');

        return Inertia::render('Customer/Account/SellerApplication', [
            'application' => $profile ? [
                'status' => $profile->status->value,
                'business_name' => $profile->business_name,
                'business_type' => $profile->business_type,
                'tin' => $profile->tin,
                'rejection_reason' => $profile->rejection_reason,
                'shop' => $profile->shop?->only(['name', 'shop_category_id', 'description', 'pickup_street', 'pickup_barangay', 'pickup_city', 'pickup_province', 'pickup_region', 'pickup_phone']),
            ] : null,
            'shopCategories' => ShopCategory::query()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function applyToSell(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'business_name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(['sole_proprietor', 'partnership', 'corporation', 'cooperative', 'home_based'])],
            'tin' => ['nullable', 'string', 'max:30'],
            'shop_name' => ['required', 'string', 'max:100'],
            'shop_category_id' => ['nullable', 'exists:shop_categories,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'pickup_street' => ['required', 'string', 'max:255'],
            'pickup_barangay' => ['required', 'string', 'max:100'],
            'pickup_city' => ['required', 'string', 'max:100'],
            'pickup_province' => ['required', 'string', 'max:100'],
            'pickup_region' => ['required', 'string', 'max:100'],
            'pickup_phone' => ['required', 'string', 'regex:/^(\+63|0)9\d{9}$/'],
            'agree' => ['accepted'],
        ], ['pickup_phone.regex' => 'Enter a valid Philippine mobile number.', 'agree.accepted' => 'Please accept the seller policies.']);

        $this->accounts->applyToSell($request->user(), $validated);

        return back()->with('success', 'Application submitted. We will email you once it is reviewed.');
    }
}
