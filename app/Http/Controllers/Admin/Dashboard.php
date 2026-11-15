<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\LogisticsPartner;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\SellerOrder;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends Controller
{
    public function index(): Response
    {
        $usersWithRole = fn (RoleName $role) => User::query()->whereHas('roles', fn ($query) => $query->where('name', $role->value))->count();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'customers' => $usersWithRole(RoleName::Customer),
                'sellers' => $usersWithRole(RoleName::Seller),
                'products' => Product::query()->count(),
                'orders' => SellerOrder::query()->count(),
                'pendingOrders' => SellerOrder::query()->whereIn('status', [SellerOrderStatus::Pending, SellerOrderStatus::Processing])->count(),
                'logisticsPartners' => LogisticsPartner::query()->count(),
                'riders' => RiderProfile::query()->count(),
                'revenue' => (string) SellerOrder::query()->where('status', SellerOrderStatus::Completed)->sum('commission_amount'),
            ],
        ]);
    }
}
