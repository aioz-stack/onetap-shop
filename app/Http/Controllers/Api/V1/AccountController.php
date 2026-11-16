<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Customer\Account\Security;
use App\Models\DeviceToken;
use App\Models\ShopCategory;
use App\Services\Administration\SettingsService;
use App\Services\Identity\AccountDeletionService;
use App\Services\Identity\AccountService;
use App\Services\Identity\PasswordResetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Account endpoints shared by the customer, seller and rider apps, plus app configuration,
 * push device registration and in-app account deletion.
 */
class AccountController extends Controller
{
    public function __construct(private AccountService $accounts, private AccountDeletionService $deletion, private SettingsService $settings) {}

    /**
     * Public app configuration: branding, currency, payment methods and support contacts.
     */
    public function config(): JsonResponse
    {
        $public = $this->settings->public();

        return response()->json(['data' => [
            'name' => $public['general']['marketplace_name'] ?? config('app.name'),
            'currency' => $public['general']['currency'] ?? 'PHP',
            'support_email' => $public['general']['support_email'] ?? null,
            'support_phone' => $public['general']['support_phone'] ?: null,
            'tagline' => $public['branding']['tagline'] ?? null,
            'primary_color' => $public['branding']['primary_color'] ?? '#0f9d76',
            'cod_max_amount' => $public['payment']['cod_max_amount'] ?? null,
            'default_delivery_fee' => $public['shipping']['default_delivery_fee'] ?? null,
            'free_shipping_threshold' => $public['shipping']['free_shipping_threshold'] ?? null,
            'payment_methods' => [
                ['code' => 'cod', 'label' => 'Cash on Delivery', 'available' => (bool) ($public['payment']['cod_enabled'] ?? true)],
                ['code' => 'gcash', 'label' => 'GCash', 'available' => false],
                ['code' => 'maya', 'label' => 'Maya', 'available' => false],
                ['code' => 'card', 'label' => 'Credit / debit card', 'available' => false],
            ],
            'apps' => array_keys(AuthController::APP_REQUIREMENTS),
            'password_reset_code_minutes' => PasswordResetService::EXPIRES_MINUTES,
        ]]);
    }

    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => AuthController::profile($request->user())]);
    }

    /**
     * Update name, email, phone and photo (multipart when sending a photo).
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=4000,max_height=4000'],
            'remove_picture' => ['boolean'],
        ], ['phone.regex' => 'Enter a valid Philippine mobile number (e.g. 09171234567).']);

        $this->accounts->updateProfile(
            $user,
            collect($validated)->only(['name', 'email', 'phone'])->all(),
            $request->file('profile_picture'),
            $request->boolean('remove_picture'),
        );

        return response()->json(['data' => AuthController::profile($user->fresh())]);
    }

    /**
     * Change password. Other phones and browsers are signed out; this phone stays signed in.
     */
    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ]);

        $token = $request->user()->currentAccessToken();
        $this->accounts->changePassword($request->user(), $validated['current_password'], $validated['password'], null, $token instanceof PersonalAccessToken ? $token->id : null);

        return response()->json(['message' => 'Password changed. Your other devices were signed out.']);
    }

    /**
     * Whether the account can be deleted now, and if not, why.
     */
    public function deletionCheck(Request $request): JsonResponse
    {
        $blockers = $this->deletion->blockers($request->user());

        return response()->json(['data' => ['can_delete' => $blockers === [], 'blockers' => $blockers]]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string'], 'reason' => ['nullable', 'string', 'max:500']]);
        $this->deletion->delete($request->user(), $validated['password'], $validated['reason'] ?? null);

        return response()->json(['message' => 'Your account has been deleted.']);
    }

    // ------------------------------------------------------------------ push devices

    /**
     * Register (or refresh) this phone's push token. A token moves to the latest account that registers it.
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', Rule::in(DeviceToken::PLATFORMS)],
            'app' => ['required', Rule::in(DeviceToken::APPS)],
        ]);

        $device = DeviceToken::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $validated['token'])],
            ['user_id' => $request->user()->id, 'token' => $validated['token'], 'platform' => $validated['platform'], 'app' => $validated['app'], 'last_used_at' => now()],
        );

        return response()->json(['data' => ['id' => $device->id, 'app' => $device->app, 'platform' => $device->platform]], $device->wasRecentlyCreated ? 201 : 200);
    }

    public function unregisterDevice(Request $request): JsonResponse
    {
        $validated = $request->validate(['token' => ['required', 'string', 'max:512']]);
        $request->user()->deviceTokens()->where('token_hash', hash('sha256', $validated['token']))->delete();

        return response()->json(['message' => 'Device removed.']);
    }

    // ------------------------------------------------------------------ customer: verification and selling

    public function verification(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'documents' => $request->user()->verificationDocuments()->latest('id')->get()->map(fn ($document) => [
                'id' => $document->id,
                'type' => $document->type,
                'type_label' => Security::DOCUMENT_TYPES[$document->type] ?? $document->type,
                'status' => $document->status->value,
                'notes' => $document->status === VerificationStatus::Rejected ? $document->notes : null,
                'created_at' => $document->created_at?->toIso8601String(),
            ]),
            'types' => collect(Security::DOCUMENT_TYPES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]]);
    }

    /**
     * Upload an identity document (multipart). Stored privately; only admins can open it.
     */
    public function uploadVerification(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in(array_keys(Security::DOCUMENT_TYPES))],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $document = $this->accounts->uploadVerification($request->user(), $validated['type'], $request->file('file'));

        return response()->json(['data' => ['id' => $document->id, 'type' => $document->type, 'status' => $document->status->value]], 201);
    }

    public function sellerApplication(Request $request): JsonResponse
    {
        $profile = $request->user()->sellerProfile()->with('shop')->first();

        return response()->json(['data' => [
            'application' => $profile ? [
                'status' => $profile->status->value,
                'business_name' => $profile->business_name,
                'business_type' => $profile->business_type,
                'tin' => $profile->tin,
                'rejection_reason' => $profile->rejection_reason,
                'shop' => $profile->shop?->only(['name', 'shop_category_id', 'description', 'pickup_street', 'pickup_barangay', 'pickup_city', 'pickup_province', 'pickup_region', 'pickup_phone']),
            ] : null,
            'business_types' => Security::BUSINESS_TYPES,
            'shop_categories' => ShopCategory::query()->orderBy('name')->get(['id', 'name']),
        ]]);
    }

    public function applyToSell(Request $request): JsonResponse
    {
        $validated = $request->validate(Security::sellerApplicationRules(), Security::sellerApplicationMessages());
        $profile = $this->accounts->applyToSell($request->user(), $validated);

        return response()->json(['data' => ['status' => $profile->status->value, 'business_name' => $profile->business_name]], 201);
    }
}
