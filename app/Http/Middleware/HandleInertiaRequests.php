<?php

namespace App\Http\Middleware;

use App\Enums\ProductStatus;
use App\Enums\RefundStatus;
use App\Enums\RoleName;
use App\Enums\SellerStatus;
use App\Enums\VerificationStatus;
use App\Enums\WithdrawalStatus;
use App\Models\Product;
use App\Models\Refund;
use App\Models\SellerProfile;
use App\Models\VerificationDocument;
use App\Models\Withdrawal;
use App\Services\Administration\SettingsService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root Blade template that renders the React application.
     */
    protected $rootView = 'app';

    public function __construct(private SettingsService $settings) {}

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default with every page.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user()?->loadMissing('roles');

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'role' => $user->primaryRole()?->value,
                    'roles' => $user->roleNames(),
                    'home' => route($user->homeRouteName()),
                    'profile_picture' => $user->profile_picture,
                ] : null,
            ],
            'marketplace' => fn () => [
                'name' => $this->settings->get('general', 'marketplace_name', config('app.name')),
                'tagline' => $this->settings->get('branding', 'tagline'),
                'currency' => $this->settings->get('general', 'currency', 'PHP'),
            ],
            // Work queues shown as sidebar badges. Only computed for admins on admin pages.
            'adminCounts' => fn () => $user?->hasRole(RoleName::Admin) && $request->is('admin*') ? [
                'sellers' => SellerProfile::query()->where('status', SellerStatus::Pending)->count(),
                'products' => Product::query()->where('status', ProductStatus::UnderReview)->count(),
                'refunds' => Refund::query()->where('status', RefundStatus::Requested)->count(),
                'withdrawals' => Withdrawal::query()->where('status', WithdrawalStatus::Pending)->count(),
                'verifications' => VerificationDocument::query()->where('status', VerificationStatus::Pending)->count(),
            ] : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
