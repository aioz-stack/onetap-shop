<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone registered for push notifications by one of the mobile apps.
 * `token_hash` (sha256 of the provider token) keeps the long token unique without a long index.
 */
class DeviceToken extends Model
{
    public const APPS = ['customer', 'seller', 'rider'];

    public const PLATFORMS = ['android', 'ios'];

    /**
     * @var list<string>
     */
    protected $fillable = ['user_id', 'app', 'platform', 'token', 'token_hash', 'last_used_at'];

    /**
     * @var list<string>
     */
    protected $hidden = ['token', 'token_hash'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['last_used_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
