<?php

namespace App\Http\Controllers\Seller;

use App\Enums\VoucherType;
use App\Models\CustomerProfile;
use App\Models\Promotion;
use App\Models\Voucher;
use App\Services\Administration\AuditLogger;
use App\Services\Marketing\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vouchers, discount promotions and referral stats for a shop.
 */
class Marketing extends SellerController
{
    public function __construct(private VoucherService $vouchers, private AuditLogger $auditLogger) {}

    public function vouchers(Request $request): Response
    {
        return Inertia::render('Seller/Marketing/Vouchers', [
            'vouchers' => $this->shop($request)->vouchers()->withCount('redemptions')->withSum('redemptions', 'amount')->latest('id')->get()->map(fn (Voucher $voucher) => [
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
                'state' => $this->state($voucher->is_active, $voucher->starts_at, $voucher->ends_at, $voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit),
            ]),
            'types' => VoucherType::options(),
        ]);
    }

    public function storeVoucher(Request $request): RedirectResponse
    {
        $voucher = $this->shop($request)->vouchers()->create($this->validatedVoucher($request));
        $this->auditLogger->log('voucher.created', $voucher, newValues: ['code' => $voucher->code]);

        return back()->with('success', "Voucher {$voucher->code} created.");
    }

    public function updateVoucher(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->shop_id === $this->shop($request)->id, 404);
        $voucher->update($this->validatedVoucher($request, $voucher));

        return back()->with('success', 'Voucher updated.');
    }

    public function destroyVoucher(Request $request, Voucher $voucher): RedirectResponse
    {
        abort_unless($voucher->shop_id === $this->shop($request)->id, 404);

        if ($voucher->redemptions()->exists()) {
            $voucher->update(['is_active' => false]);

            return back()->with('success', 'Voucher was used before, so it was switched off instead of deleted.');
        }

        $voucher->delete();

        return back()->with('success', 'Voucher deleted.');
    }

    public function promotions(Request $request): Response
    {
        $shop = $this->shop($request);

        return Inertia::render('Seller/Marketing/Promotions', [
            'promotions' => Promotion::query()->where('shop_id', $shop->id)->with('products:id,name')->latest('id')->get()->map(fn (Promotion $promotion) => [
                'id' => $promotion->id,
                'name' => $promotion->name,
                'discount_percent' => $promotion->discount_percent,
                'starts_at' => $promotion->starts_at?->format('Y-m-d\TH:i'),
                'ends_at' => $promotion->ends_at?->format('Y-m-d\TH:i'),
                'is_active' => $promotion->is_active,
                'product_ids' => $promotion->products->pluck('id'),
                'products' => $promotion->products->pluck('name'),
                'state' => $this->state($promotion->is_active, $promotion->starts_at, $promotion->ends_at),
            ]),
            'products' => $shop->products()->whereIn('status', ['live', 'under_review', 'unpublished', 'draft'])->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function storePromotion(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $validated = $this->validatedPromotion($request);
        $promotion = Promotion::query()->create(collect($validated)->except('product_ids')->all() + ['shop_id' => $shop->id]);
        $promotion->products()->sync($validated['product_ids']);
        $this->auditLogger->log('promotion.created', $promotion, newValues: ['percent' => $promotion->discount_percent]);

        return back()->with('success', "Promotion “{$promotion->name}” saved.");
    }

    public function updatePromotion(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($promotion->shop_id === $this->shop($request)->id, 404);
        $validated = $this->validatedPromotion($request);
        $promotion->update(collect($validated)->except('product_ids')->all());
        $promotion->products()->sync($validated['product_ids']);

        return back()->with('success', 'Promotion updated.');
    }

    public function destroyPromotion(Request $request, Promotion $promotion): RedirectResponse
    {
        abort_unless($promotion->shop_id === $this->shop($request)->id, 404);
        $promotion->delete();

        return back()->with('success', 'Promotion deleted. Past orders keep the prices customers paid.');
    }

    /**
     * Referral program: the owner's personal referral code and the customers who joined with it.
     */
    public function referrals(Request $request): Response
    {
        $user = $request->user();
        $profile = $user->customerProfile()->firstOrCreate([], ['referral_code' => strtoupper(\Illuminate\Support\Str::random(8))]);
        $referred = CustomerProfile::query()->where('referred_by', $user->id)->with('user:id,name,created_at')->latest('id')->get();

        return Inertia::render('Seller/Marketing/Referrals', [
            'code' => $profile->referral_code,
            'link' => route('register', ['ref' => $profile->referral_code]),
            'referred' => $referred->map(fn ($customer) => ['name' => $customer->user?->name, 'joined_at' => $customer->user?->created_at?->toIso8601String()]),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedVoucher(Request $request, ?Voucher $voucher = null): array
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);

        $validated = $request->validate([
            'code' => ['required', 'alpha_num', 'min:4', 'max:20', Rule::unique('vouchers', 'code')->ignore($voucher)],
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(VoucherType::class)],
            'value' => [Rule::requiredIf(fn () => $request->input('type') !== VoucherType::FreeShipping->value), 'nullable', 'numeric', 'min:1', $request->input('type') === VoucherType::Percent->value ? 'max:90' : 'max:100000'],
            'min_spend' => ['nullable', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'numeric', 'min:1'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
        ], ['code.alpha_num' => 'Use letters and numbers only.', 'value.max' => 'That discount is too large.']);

        $validated['value'] = $validated['value'] ?? 0;
        $validated['min_spend'] = $validated['min_spend'] ?? 0;

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedPromotion(Request $request): array
    {
        $shopId = $this->shop($request)->id;

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'discount_percent' => ['required', 'numeric', 'min:1', 'max:90'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'is_active' => ['boolean'],
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer', Rule::exists('products', 'id')->where('shop_id', $shopId)],
        ], ['product_ids.required' => 'Choose at least one product.', 'product_ids.*.exists' => 'Choose products from your shop.']);
    }

    private function state(bool $active, $startsAt, $endsAt, bool $exhausted = false): string
    {
        return match (true) {
            ! $active => 'inactive',
            $exhausted => 'closed',
            $startsAt && $startsAt->isFuture() => 'pending',
            $endsAt && $endsAt->isPast() => 'closed',
            default => 'active',
        };
    }
}
