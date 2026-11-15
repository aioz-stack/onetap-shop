<?php

namespace App\Models;

use App\Enums\ShopStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'seller_profile_id',
        'shop_category_id',
        'name',
        'slug',
        'description',
        'logo',
        'banner',
        'template',
        'decoration',
        'pickup_street',
        'pickup_barangay',
        'pickup_city',
        'pickup_province',
        'pickup_region',
        'pickup_phone',
        'status',
        'vacation_mode',
        'vacation_message',
        'rating_avg',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'decoration' => 'array',
            'status' => ShopStatus::class,
            'vacation_mode' => 'boolean',
            'rating_avg' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<SellerProfile, $this>
     */
    public function sellerProfile(): BelongsTo
    {
        return $this->belongsTo(SellerProfile::class);
    }

    /**
     * @return BelongsTo<ShopCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ShopCategory::class, 'shop_category_id');
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<SellerOrder, $this>
     */
    public function sellerOrders(): HasMany
    {
        return $this->hasMany(SellerOrder::class);
    }

    /**
     * @return HasOne<SellerBalance, $this>
     */
    public function balance(): HasOne
    {
        return $this->hasOne(SellerBalance::class);
    }

    /**
     * @return HasMany<BankAccount, $this>
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * @return HasMany<Withdrawal, $this>
     */
    public function withdrawals(): HasMany
    {
        return $this->hasMany(Withdrawal::class);
    }

    /**
     * @return HasMany<Voucher, $this>
     */
    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    /**
     * @return HasMany<ShopAutoReply, $this>
     */
    public function autoReplies(): HasMany
    {
        return $this->hasMany(ShopAutoReply::class);
    }
}
