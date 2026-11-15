<?php

namespace App\Http\Controllers\Admin;

use App\Enums\VoucherType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\Marketing;
use App\Models\Voucher;
use App\Services\Administration\AuditLogger;
use App\Services\Marketing\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Platform-wide vouchers (no shop): valid in any shop and funded by Onetap, not the seller.
 */
class Vouchers extends Controller
{
    public function __construct(private VoucherService $vouchers, private AuditLogger $auditLogger) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Marketing/Vouchers', [
            'vouchers' => Voucher::query()->whereNull('shop_id')->withCount('redemptions')->withSum('redemptions', 'amount')->latest('id')->get()->map(fn (Voucher $voucher) => [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'name' => $voucher->name,
                'type' => $voucher->type->value,
                'value' => $voucher->value,
                'min_spend' => $voucher->min_spend,
                'max_discount' => $voucher->max_discount,
                'usage_limit' => $voucher->usage_limit,
                'per_user_limit' => $voucher->per_user_limit,
                'used_count' => $voucher->used_count,
                'discount_given' => $voucher->redemptions_sum_amount ?? '0.00',
                'starts_at' => $voucher->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $voucher->ends_at?->format('Y-m-d\TH:i'),
                'is_active' => $voucher->is_active,
                'label' => $this->vouchers->describe($voucher),
                'state' => Marketing::state($voucher->is_active, $voucher->starts_at, $voucher->ends_at, $voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit),
            ]),
            'types' => VoucherType::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $voucher = Voucher::query()->create(Marketing::validatedVoucher($request) + ['shop_id' => null]);
        $this->auditLogger->log('voucher.created', $voucher, newValues: ['code' => $voucher->code, 'scope' => 'platform'], actor: $request->user());

        return back()->with('success', "Platform voucher {$voucher->code} created.");
    }

    public function update(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->shop_id === null, 404);
        $voucher->update(Marketing::validatedVoucher($request, $voucher));
        $this->auditLogger->log('voucher.updated', $voucher, newValues: ['code' => $voucher->code], actor: $request->user());

        return back()->with('success', 'Voucher updated.');
    }

    public function destroy(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->shop_id === null, 404);

        if ($voucher->redemptions()->exists()) {
            $voucher->update(['is_active' => false]);

            return back()->with('success', 'Voucher was used before, so it was switched off instead of deleted.');
        }

        $this->auditLogger->log('voucher.deleted', $voucher, ['code' => $voucher->code], actor: $request->user());
        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }
}
