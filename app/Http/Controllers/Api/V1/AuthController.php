<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Services\Identity\LoginService;
use App\Services\Identity\RegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private LoginService $loginService,
        private RegistrationService $registrationService,
        private AuditLogger $auditLogger,
        private SettingsService $settings,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        $user = $this->registrationService->registerCustomer($request->validated());
        $token = $this->issueToken($user, $request->input('device_name', 'api'));

        return response()->json([
            'data' => new UserResource($user->load('roles')),
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ]);

        $user = $this->loginService->verify($validated['login'], $validated['password']);
        $this->loginService->recordLogin($user, 'api');

        return response()->json([
            'data' => new UserResource($user->load('roles')),
            'token' => $this->issueToken($user, $validated['device_name'] ?? 'api'),
            'token_type' => 'Bearer',
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $this->auditLogger->log('auth.logout', $request->user(), newValues: ['channel' => 'api']);
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Issue a Sanctum token whose lifetime follows Admin → Settings → API.
     */
    private function issueToken(User $user, string $deviceName): string
    {
        $days = (int) $this->settings->get('api', 'token_expiry_days', 30);

        return $user->createToken($deviceName, ['*'], $days > 0 ? now()->addDays($days) : null)->plainTextToken;
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }
}
