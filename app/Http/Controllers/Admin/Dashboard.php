<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleName;
use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LogisticsPartner;
use App\Models\Product;
use App\Models\RiderProfile;
use App\Models\SellerOrder;
use App\Models\Transaction;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class Dashboard extends Controller
{
    public function index(): Response
    {
        $usersWithRole = fn (RoleName $role) => User::query()->whereHas('roles', fn ($query) => $query->where('name', $role->value))->count();
        $ordersIn = fn (array $statuses) => SellerOrder::query()->whereIn('status', $statuses)->count();
        $tabs = SellerOrderStatus::tabs();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'revenue' => Money::format(SellerOrder::query()->where('status', SellerOrderStatus::Completed)->sum('commission_amount')),
                'gmv' => Money::format(SellerOrder::query()->where('status', SellerOrderStatus::Completed)->sum('total')),
                'orders' => SellerOrder::query()->count(),
                'pendingOrders' => $ordersIn($tabs['to_ship']['statuses']),
                'completedOrders' => $ordersIn([SellerOrderStatus::Completed]),
                'cancelledOrders' => $ordersIn([SellerOrderStatus::Cancelled]),
                'customers' => $usersWithRole(RoleName::Customer),
                'sellers' => $usersWithRole(RoleName::Seller),
                'products' => Product::query()->count(),
                'logisticsPartners' => LogisticsPartner::query()->count(),
                'riders' => RiderProfile::query()->count(),
            ],
            'salesChart' => $this->salesChart(30),
            'recentTransactions' => Transaction::query()
                ->with('shop:id,name')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (Transaction $transaction) => [
                    'id' => $transaction->id,
                    'type' => $transaction->type->value,
                    'direction' => $transaction->direction->value,
                    'amount' => $transaction->amount,
                    'shop' => $transaction->shop?->name,
                    'description' => $transaction->description,
                    'created_at' => $transaction->created_at?->toIso8601String(),
                ]),
            'recentActivities' => AuditLog::query()
                ->with('user:id,name')
                ->latest('id')
                ->limit(8)
                ->get()
                ->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'user' => $log->user?->name ?? 'System',
                    'subject' => $log->auditable_type ? class_basename($log->auditable_type).' #'.$log->auditable_id : null,
                    'created_at' => $log->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Daily order count and gross sales (excluding cancelled orders) for the last N days.
     *
     * @return array{labels: list<string>, sales: list<float>, orders: list<int>}
     */
    private function salesChart(int $days): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $rows = SellerOrder::query()
            ->where('created_at', '>=', $start)
            ->where('status', '!=', SellerOrderStatus::Cancelled)
            ->get(['created_at', 'total'])
            ->groupBy(fn (SellerOrder $order) => $order->created_at->toDateString());

        $labels = $sales = $orders = [];

        for ($day = $start->copy(); $day->lte(Carbon::today()); $day->addDay()) {
            $key = $day->toDateString();
            $labels[] = $day->format('M j');
            $sales[] = round((float) ($rows[$key] ?? collect())->sum('total'), 2);
            $orders[] = ($rows[$key] ?? collect())->count();
        }

        return ['labels' => $labels, 'sales' => $sales, 'orders' => $orders];
    }
}
