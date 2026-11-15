<?php

namespace Database\Seeders;

use App\Enums\PartnerStatus;
use App\Enums\RiderAvailability;
use App\Enums\RiderStatus;
use App\Enums\RoleName;
use App\Enums\SellerStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\LogisticsPartner;
use App\Models\ShopCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Local development accounts, one per panel. All use the password "password".
 * Never run this seeder in production.
 */
class DemoAccountsSeeder extends Seeder
{
    private const PASSWORD = 'password';

    public function run(): void
    {
        $this->account('admin', 'Onetap Admin', 'admin@onetap.test', [RoleName::Admin]);

        $customer = $this->account('customer', 'Juan Dela Cruz', 'customer@onetap.test', [RoleName::Customer]);
        $customer->addresses()->firstOrCreate(['label' => 'Home'], [
            'recipient_name' => 'Juan Dela Cruz',
            'phone' => '09171234567',
            'region' => 'NCR',
            'province' => 'Metro Manila',
            'city' => 'Quezon City',
            'barangay' => 'Bagong Pag-asa',
            'street' => '12 Mabini St.',
            'postal_code' => '1105',
            'is_default' => true,
        ]);

        $seller = $this->account('seller', 'Maria Santos', 'seller@onetap.test', [RoleName::Customer, RoleName::Seller]);
        $sellerProfile = $seller->sellerProfile()->firstOrCreate([], [
            'business_name' => 'Santos Home Bakery',
            'business_type' => 'sole_proprietor',
            'status' => SellerStatus::Approved,
            'approved_at' => now(),
        ]);
        $shop = $sellerProfile->shop()->firstOrCreate([], [
            'name' => 'Santos Home Bakery',
            'slug' => 'santos-home-bakery',
            'description' => 'Freshly baked pandesal, ensaymada and cakes every morning.',
            'shop_category_id' => ShopCategory::query()->where('slug', 'bakery')->value('id'),
            'pickup_street' => '45 Rizal Ave.',
            'pickup_barangay' => 'Bagong Pag-asa',
            'pickup_city' => 'Quezon City',
            'pickup_province' => 'Metro Manila',
            'pickup_region' => 'NCR',
            'pickup_phone' => '09181234567',
            'status' => ShopStatus::Active,
        ]);
        $shop->balance()->firstOrCreate([]);

        $logisticsOwner = $this->account('logistics', 'Onetap Express Ops', 'logistics@onetap.test', [RoleName::Logistics]);
        $partner = LogisticsPartner::query()->firstOrCreate(['code' => 'OTX'], [
            'user_id' => $logisticsOwner->id,
            'name' => 'Onetap Express',
            'contact_phone' => '0281234567',
            'contact_email' => 'logistics@onetap.test',
            'base_delivery_fee' => 59,
            'rider_fee_per_delivery' => 35,
            'status' => PartnerStatus::Active,
        ]);
        $partner->serviceAreas()->firstOrCreate(
            ['city' => 'Quezon City', 'barangay' => null],
            ['region' => 'NCR', 'province' => 'Metro Manila', 'delivery_fee' => 59],
        );

        $rider = $this->account('rider', 'Pedro Reyes', 'rider@onetap.test', [RoleName::Rider]);
        $rider->riderProfile()->firstOrCreate([], [
            'logistics_partner_id' => $partner->id,
            'vehicle_type' => 'motorcycle',
            'plate_number' => 'ABC 1234',
            'status' => RiderStatus::Active,
            'availability' => RiderAvailability::Offline,
        ]);
    }

    /**
     * @param  list<RoleName>  $roles
     */
    private function account(string $username, string $name, string $email, array $roles): User
    {
        $user = User::query()->firstOrCreate(['username' => $username], [
            'name' => $name,
            'email' => $email,
            'password' => self::PASSWORD,
            'status' => UserStatus::Active,
            'email_verified_at' => now(),
        ]);

        foreach ($roles as $role) {
            $user->assignRole($role);
        }

        if ($user->hasRole(RoleName::Customer)) {
            $user->customerProfile()->firstOrCreate([], ['referral_code' => strtoupper(Str::random(8))]);
            $user->cart()->firstOrCreate([]);
        }

        return $user;
    }
}
