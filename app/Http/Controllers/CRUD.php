<?php

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\Administration\AuditLogger;
use App\Services\Identity\UserAccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class CRUD extends Controller
{
    /**
     * List the users (paginated).
     */
    public function __construct(private UserAccountService $accounts, private AuditLogger $auditLogger) {}

    /**
     * Sellers keep their customer role (they shop too), matching Admin → Users.
     *
     * @return list<RoleName>
     */
    private static function rolesFor(RoleName $role): array
    {
        return $role === RoleName::Seller ? [RoleName::Customer, RoleName::Seller] : [$role];
    }

    public function index(): Response
    {
        // Paginate the users so the page stays fast when the table grows.
        $users = User::query()->latest()->paginate(10);

        return Inertia::render('Crud/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Show the form for creating a new user.
     */
    public function create(): Response
    {
        // An empty User so the React form can share the same shape as edit.
        return Inertia::render('Crud/Create', [
            'user' => new User,
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::enum(RoleName::class)],
            'password' => ['required', 'string', Password::min(8)->letters()->numbers()],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('profile_picture')) {
            $validated['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        // Same rules as Admin → Users: audited, with profiles created for the role.
        $this->accounts->create(collect($validated)->except('role')->all(), self::rolesFor(RoleName::from($validated['role'])), $request->user());

        return redirect()->route('crud.index')->with('success', 'User created successfully.');
    }

    /**
     * Show a single user in read-only mode.
     */
    public function show(User $crud): Response
    {
        return Inertia::render('Crud/Show', [
            'user' => $crud,
        ]);
    }

    /**
     * Show the form for editing the given user.
     */
    public function edit(User $crud): Response
    {
        return Inertia::render('Crud/Edit', [
            'user' => $crud,
        ]);
    }

    /**
     * Update the given user.
     */
    public function update(Request $request, User $crud): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$crud->id],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,'.$crud->id],
            'role' => ['required', Rule::enum(RoleName::class)],
            'password' => ['nullable', 'string', Password::min(8)->letters()->numbers()],
            'profile_picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);

        if ($request->hasFile('profile_picture')) {
            if ($crud->profile_picture) {
                Storage::disk('public')->delete($crud->profile_picture);
            }

            $validated['profile_picture'] = $request->file('profile_picture')->store('profile-pictures', 'public');
        }

        // Replaces the user's roles (a seller keeps the customer role) and refuses to demote yourself.
        $this->accounts->update($crud, collect($validated)->except('role')->all(), self::rolesFor(RoleName::from($validated['role'])), $request->user());

        return redirect()->route('crud.index')->with('success', 'User updated successfully.');
    }

    /**
     * Remove the given user.
     */
    public function destroy(Request $request, User $crud): RedirectResponse
    {
        if ($crud->is($request->user()) || $crud->hasRole(RoleName::Admin)) {
            return back()->with('error', 'Administrators cannot be deleted here. Remove the admin role first, or suspend the account from Admin → Users.');
        }

        $this->auditLogger->log('user.deleted', $crud, ['username' => $crud->username, 'roles' => $crud->roleNames()], actor: $request->user());

        if ($crud->profile_picture) {
            Storage::disk('public')->delete($crud->profile_picture);
        }

        $crud->delete();

        return redirect()->route('crud.index')->with('success', 'User deleted successfully.');
    }
}
