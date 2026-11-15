<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RefundStatus;
use App\Http\Controllers\Controller;
use App\Models\Refund;
use App\Services\Ordering\RefundService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Refunds extends Controller
{
    public function __construct(private RefundService $refunds) {}

    public function index(Request $request): Response
    {
        $status = RefundStatus::tryFrom((string) $request->query('status', 'requested')) ?? RefundStatus::Requested;
        $counts = [];

        foreach (RefundStatus::cases() as $case) {
            $counts[$case->value] = Refund::query()->where('status', $case)->count();
        }

        return Inertia::render('Admin/Refunds/Index', [
            'refunds' => Refund::query()
                ->with(['user:id,name', 'sellerOrder:id,reference,shop_id,total,status', 'sellerOrder.shop:id,name', 'resolver:id,name'])
                ->where('status', $status)
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Refund $refund) => [
                    'id' => $refund->id,
                    'reason' => $refund->reason,
                    'details' => $refund->details,
                    'amount' => $refund->amount,
                    'status' => $refund->status->value,
                    'customer' => $refund->user?->name,
                    'order' => $refund->sellerOrder?->only(['id', 'reference', 'total']) + ['status' => $refund->sellerOrder?->status->value],
                    'shop' => $refund->sellerOrder?->shop?->name,
                    'resolver' => $refund->resolver?->name,
                    'resolved_at' => $refund->resolved_at?->toIso8601String(),
                    'created_at' => $refund->created_at?->toIso8601String(),
                ]),
            'status' => $status->value,
            'counts' => $counts,
        ]);
    }

    public function approve(Request $request, Refund $refund): RedirectResponse
    {
        $validated = $request->validate([
            'restock' => ['boolean'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $this->refunds->approve($refund, $request->user(), (bool) ($validated['restock'] ?? false), $validated['note'] ?? null);

        return back()->with('success', 'Refund approved.');
    }

    public function reject(Request $request, Refund $refund): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $this->refunds->reject($refund, $request->user(), $validated['reason']);

        return back()->with('success', 'Refund rejected and the order restored.');
    }
}
