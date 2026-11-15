<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class Users extends Controller
{
    public function __construct(private UserAccountService $accounts) {}

    public function index(Request $request): Response
    {
        $role = RoleName::tryFrom((string) $request->query('role'));
        $status = UserStatus::tryFrom((string) $request->query('status'));
        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->with('roles:id,name')
            ->when($role, fn ($query) => $query->whereHas('roles', fn ($roles) => $roles->where('name', $role->value)))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($search !== '', fn ($query) => $query->where(fn ($inner) => $inner
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user) => $this->row($user));

        $roleCounts = ['all' => User::query()->count()];

        foreach (RoleName::cases() as $roleName) {
            $roleCounts[$roleName->value] = User::query()->whereHas('roles', fn ($query) => $query->where('name', $roleName->value))->count();
        }

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['role' => $role?->value, 'status' => $status?->value, 'search' => $search],
            'roleCounts' => $roleCounts,
            'roles' => RoleName::options(),
            'statuses' => UserStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Users/Form', ['user' => null, 'roles' => RoleName::options()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);
        $user = $this->accounts->create(collect($validated)->except('roles')->all(), $this->roleEnums($validated['roles']), $request->user());

        return redirect()->route('admin.users.show', $user)->with('success', "Account for {$user->name} created.");
    }

    public function show(User $user): Response
    {
        $user->load(['roles:id,name', 'addresses', 'sellerProfile.shop:id,seller_profile_id,name,slug,status', 'riderProfile.logisticsPartner:id,name', 'logisticsPartner:id,user_id,name']);

        return Inertia::render('Admin/Users/Show', [
            'user' => $this->row($user) + [
                'suspended_at' => $user->suspended_at?->toIso8601String(),
                'suspension_reason' => $user->suspension_reason,
                'email_verified_at' => $user->email_verified_at?->toIso8601String(),
                'addresses' => $user->addresses->map->only(['id', 'label', 'recipient_name', 'phone', 'street', 'barangay', 'city', 'province', 'is_default']),
                'seller' => $user->sellerProfile ? [
                    'id' => $user->sellerProfile->id,
                    'business_name' => $user->sellerProfile->business_name,
                    'status' => $user->sellerProfile->status->value,
                    'shop' => $user->sellerProfile->shop?->only(['id', 'name', 'slug', 'status']),
                ] : null,
                'rider' => $user->riderProfile ? [
                    'status' => $user->riderProfile->status->value,
                    'availability' => $user->riderProfile->availability->value,
                    'partner' => $user->riderProfile->logisticsPartner?->name,
                ] : null,
                'logisticsPartner' => $user->logisticsPartner?->only(['id', 'name']),
                'ordersCount' => $user->orders()->count(),
                'documents' => $user->verificationDocuments()->latest()->get()->map(fn ($document) => [
                    'id' => $document->id,
                    'type' => $document->type,
                    'status' => $document->status->value,
                    'created_at' => $document->created_at?->toIso8601String(),
                ]),
            ],
            'isSelf' => $user->is(request()->user()),
        ]);
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Admin/Users/Form', [
            'user' => $user->only(['id', 'name', 'username', 'email', 'phone']) + ['roles' => $user->load('roles')->roleNames()],
            'roles' => RoleName::options(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $this->validated($request, $user);
        $this->accounts->update($user, collect($validated)->except('roles')->all(), $this->roleEnums($validated['roles']), $request->user());

        return redirect()->route('admin.users.show', $user)->with('success', 'Account updated.');
    }

    public function suspend(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->accounts->suspend($user, $request->user(), $validated['reason']);

        return back()->with('success', "{$user->name} has been suspended and signed out.");
    }

    public function reactivate(Request $request, User $user): RedirectResponse
    {
        $this->accounts->reactivate($user, $request->user());

        return back()->with('success', "{$user->name} can sign in again.");
    }

    /**
     * @return array<string, mixed>
     */
    private function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'phone' => $user->phone,
            'profile_picture' => $user->profile_picture,
            'status' => $user->status->value,
            'roles' => $user->roleNames(),
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?User $user = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', Rule::unique('users', 'username')->ignore($user)],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
            'password' => [$user ? 'nullable' : 'required', Password::min(8)->letters()->numbers()],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::enum(RoleName::class)],
        ], ['phone.regex' => 'Enter a valid Philippine mobile number (e.g. 09171234567).']);
    }

    /**
     * @param  list<string>  $roles
     * @return list<RoleName>
     */
    private function roleEnums(array $roles): array
    {
        return array_values(array_map(fn (string $role) => RoleName::from($role), array_unique($roles)));
    }
}
