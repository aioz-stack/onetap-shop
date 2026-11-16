<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Services\Identity\LoginService;
use App\Services\Identity\PasswordResetService;
use App\Services\Identity\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Sign-in for the customer, seller and rider apps.
 *
 * Each app sends `app` when signing in; accounts that can't use that app get a 403 with a clear
 * message (for example a rider opening the seller app). Tokens are named "<app>:<device name>"
 * and expire after Admin → Settings → API → token lifetime.
 */
class AuthController extends Controller
{
    public const APP_REQUIREMENTS = [
        'customer' => 'This account cannot shop. Sign in with a customer account.',
        'seller' => 'This account has no active shop. Apply to sell on Onetap, or wait for your shop to be approved.',
        'rider' => 'This account is not a rider. Ask your logistics partner to add you.',
    ];

    public function __construct(
        private LoginService $loginService,
        private RegistrationService $registrationService,
        private AuditLogger $auditLogger,
        private SettingsService $settings,
        private PasswordResetService $passwordResets,
    ) {}

    /**
     * Customer sign-up (customer app). Sellers apply from inside the customer app; riders are added by partners.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registrationService->registerCustomer($request->validated());
        $token = $this->issueToken($user, 'customer', $request->input('device_name', 'api'));

        return response()->json([
            'data' => $this->profile($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'app' => ['nullable', Rule::in(array_keys(self::APP_REQUIREMENTS))],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->loginService->verify($validated['login'], $validated['password']);
        $app = $validated['app'] ?? null;

        if ($app && ! in_array($app, $user->mobileApps(), true)) {
            return response()->json(['message' => self::APP_REQUIREMENTS[$app], 'code' => 'app_not_allowed', 'apps' => $user->mobileApps()], 403);
        }

        $this->loginService->recordLogin($user, $app ? "api:{$app}" : 'api');

        return response()->json([
            'data' => $this->profile($user),
            'token' => $this->issueToken($user, $app ?? 'api', $validated['device_name'] ?? 'api'),
            'token_type' => 'Bearer',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profile($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auditLogger->log('auth.logout', $request->user(), newValues: ['channel' => 'api']);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Sign out everywhere: every API token (all phones) and every web session.
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $user = $request->user();
        $count = $user->tokens()->count();
        $user->tokens()->delete();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        $user->deviceTokens()->delete();
        $this->auditLogger->log('auth.logout_all', $user, newValues: ['tokens' => $count], actor: $user);

        return response()->json(['message' => 'Signed out of all devices.']);
    }

    /**
     * Re-enter the password before sensitive screens (seller finance). Valid for this token only.
     */
    public function confirmPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is incorrect.']);
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->forceFill(['password_confirmed_at' => now()])->save();
        }

        return response()->json(['message' => 'Password confirmed.', 'expires_in' => (int) config('auth.password_timeout', 10800)]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $validated = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $this->passwordResets->sendCode($validated['email']);

        // Same answer whether or not the email is registered, so accounts can't be discovered this way.
        return response()->json(['message' => 'If that email has an Onetap account, we sent a 6-digit code to it. It expires in '.PasswordResetService::EXPIRES_MINUTES.' minutes.']);
    }

    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'code' => ['required', 'digits:6'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->numbers()],
        ]);

        $this->passwordResets->reset($validated['email'], $validated['code'], $validated['password']);

        return response()->json(['message' => 'Password changed. Sign in with your new password.']);
    }

    /**
     * The signed-in account as the apps need it: profile, roles, which apps it can use,
     * and a summary of the shop (seller) or rider profile.
     *
     * @return array<string, mixed>
     */
    public static function profile(User $user): array
    {
        $user->loadMissing(['roles', 'sellerProfile.shop', 'riderProfile.logisticsPartner']);
        $shop = $user->sellerProfile?->shop;
        $rider = $user->riderProfile;

        return (new UserResource($user))->resolve() + [
            'apps' => $user->mobileApps(),
            'seller' => $user->sellerProfile ? [
                'status' => $user->sellerProfile->status->value,
                'shop' => $shop ? ['id' => $shop->id, 'name' => $shop->name, 'slug' => $shop->slug, 'status' => $shop->status->value, 'logo_url' => $shop->logo ? asset('storage/'.$shop->logo) : null] : null,
            ] : null,
            'rider' => $rider ? [
                'id' => $rider->id,
                'status' => $rider->status->value,
                'availability' => $rider->availability->value,
                'partner' => $rider->logisticsPartner?->name,
            ] : null,
        ];
    }

    /**
     * A Sanctum token whose lifetime follows Admin → Settings → API.
     */
    private function issueToken(User $user, string $app, string $deviceName): string
    {
        $days = (int) $this->settings->get('api', 'token_expiry_days', 30);

        return $user->createToken("{$app}:{$deviceName}", ['*'], $days > 0 ? now()->addDays($days) : null)->plainTextToken;
    }
}
