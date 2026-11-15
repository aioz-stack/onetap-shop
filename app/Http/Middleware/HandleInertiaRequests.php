<?php

namespace App\Http\Middleware;

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
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
