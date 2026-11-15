<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permission names grouped by area, with the roles that receive them.
     *
     * @var array<string, array<string, list<RoleName>>>
     */
    private const PERMISSIONS = [
        'admin' => [
            'admin.dashboard.view' => [RoleName::Admin],
            'users.manage' => [RoleName::Admin],
            'sellers.approve' => [RoleName::Admin],
            'products.moderate' => [RoleName::Admin],
            'categories.manage' => [RoleName::Admin],
            'orders.manage_all' => [RoleName::Admin],
            'finance.manage' => [RoleName::Admin],
            'settings.manage' => [RoleName::Admin],
            'audit_logs.view' => [RoleName::Admin],
            'logistics_partners.manage' => [RoleName::Admin],
        ],
        'customer' => [
            'cart.manage' => [RoleName::Customer],
            'orders.place' => [RoleName::Customer],
            'reviews.write' => [RoleName::Customer],
            'refunds.request' => [RoleName::Customer],
        ],
        'seller' => [
            'seller.dashboard.view' => [RoleName::Seller],
            'seller.products.manage' => [RoleName::Seller],
            'seller.orders.manage' => [RoleName::Seller],
            'seller.shop.manage' => [RoleName::Seller],
            'seller.finance.manage' => [RoleName::Seller],
            'seller.marketing.manage' => [RoleName::Seller],
        ],
        'logistics' => [
            'logistics.dashboard.view' => [RoleName::Logistics],
            'logistics.riders.manage' => [RoleName::Logistics],
            'logistics.shipments.manage' => [RoleName::Logistics],
            'logistics.coverage.manage' => [RoleName::Logistics],
            'logistics.finance.manage' => [RoleName::Logistics],
        ],
        'rider' => [
            'rider.dashboard.view' => [RoleName::Rider],
            'rider.deliveries.update' => [RoleName::Rider],
            'rider.remittances.submit' => [RoleName::Rider],
        ],
    ];

    public function run(): void
    {
        $roles = [];

        foreach (RoleName::cases() as $roleName) {
            $roles[$roleName->value] = Role::query()->updateOrCreate(
                ['name' => $roleName->value],
                ['label' => $roleName->label()],
            );
        }

        foreach (self::PERMISSIONS as $group => $permissions) {
            foreach ($permissions as $name => $grantedTo) {
                $permission = Permission::query()->updateOrCreate(
                    ['name' => $name],
                    ['label' => Str::headline(str_replace('.', ' ', $name)), 'group' => $group],
                );

                foreach ($grantedTo as $roleName) {
                    $roles[$roleName->value]->permissions()->syncWithoutDetaching([$permission->id]);
                }
            }
        }
    }
}
