<?php

namespace App\Models;

use App\Enums\RoleName;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * Roles ordered by which panel a user lands on when they hold several.
     *
     * @var list<RoleName>
     */
    public const ROLE_PRECEDENCE = [RoleName::Admin, RoleName::Logistics, RoleName::Rider, RoleName::Seller, RoleName::Customer];

    /**
     * Mirrors the column defaults so freshly created models behave like reloaded ones.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'role' => 'customer',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'profile_picture',
        'password',
        'role',
        'status',
        'suspended_at',
        'suspension_reason',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'suspended_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')->withTimestamps();
    }

    /**
     * @return HasOne<CustomerProfile, $this>
     */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class);
    }

    /**
     * @return HasOne<SellerProfile, $this>
     */
    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    /**
     * @return HasOne<RiderProfile, $this>
     */
    public function riderProfile(): HasOne
    {
        return $this->hasOne(RiderProfile::class);
    }

    /**
     * The logistics partner this user administers.
     *
     * @return HasOne<LogisticsPartner, $this>
     */
    public function logisticsPartner(): HasOne
    {
        return $this->hasOne(LogisticsPartner::class);
    }

    /**
     * @return HasMany<UserAddress, $this>
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(UserAddress::class);
    }

    /**
     * @return BelongsToMany<Conversation, $this>
     */
    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_participants')->withPivot('last_read_at')->withTimestamps();
    }

    /**
     * @return HasMany<ProductReview, $this>
     */
    public function productReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }

    /**
     * @return HasMany<VerificationDocument, $this>
     */
    public function verificationDocuments(): HasMany
    {
        return $this->hasMany(VerificationDocument::class);
    }

    /**
     * @return HasOne<ShoppingCart, $this>
     */
    public function cart(): HasOne
    {
        return $this->hasOne(ShoppingCart::class);
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return list<string>
     */
    public function roleNames(): array
    {
        return $this->roles->pluck('name')->values()->all();
    }

    public function hasRole(RoleName|string ...$roles): bool
    {
        $owned = $this->roleNames();

        foreach ($roles as $role) {
            if (in_array($role instanceof RoleName ? $role->value : $role, $owned, true)) {
                return true;
            }
        }

        return false;
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn ($query) => $query->where('name', $permission))
            ->exists();
    }

    /**
     * Attach a role and keep the legacy `role` column pointing at the primary role.
     */
    public function assignRole(RoleName $role): void
    {
        $roleModel = Role::query()->firstOrCreate(['name' => $role->value], ['label' => $role->label()]);
        $this->roles()->syncWithoutDetaching([$roleModel->id]);
        $this->unsetRelation('roles');

        $this->forceFill(['role' => $this->primaryRole()?->value ?? $role->value])->save();
    }

    public function removeRole(RoleName $role): void
    {
        $this->roles()->detach(Role::query()->where('name', $role->value)->pluck('id'));
        $this->unsetRelation('roles');

        $this->forceFill(['role' => $this->primaryRole()?->value ?? RoleName::Customer->value])->save();
    }

    public function primaryRole(): ?RoleName
    {
        foreach (self::ROLE_PRECEDENCE as $role) {
            if ($this->hasRole($role)) {
                return $role;
            }
        }

        return null;
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    /**
     * The route name of the panel this user lands on after logging in.
     */
    public function homeRouteName(): string
    {
        return match ($this->primaryRole()) {
            RoleName::Admin => 'admin.dashboard',
            RoleName::Logistics => 'logistics.dashboard',
            RoleName::Rider => 'rider.dashboard',
            RoleName::Seller => 'seller.dashboard',
            default => 'home',
        };
    }
}
