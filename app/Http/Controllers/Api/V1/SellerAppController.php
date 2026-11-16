<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\VoucherType;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\CustomerService;
use App\Http\Controllers\Seller\Marketing;
use App\Models\BankAccount;
use App\Models\CustomerProfile;
use App\Models\ProductReview;
use App\Models\ProductVariation;
use App\Models\Promotion;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Models\ShopAutoReply;
use App\Models\ShopCategory;
use App\Models\Voucher;
use App\Models\Withdrawal;
use App\Services\Administration\AuditLogger;
use App\Services\Administration\SettingsService;
use App\Services\Catalog\ReviewService;
use App\Services\Catalog\ShopProfileService;
use App\Services\Finance\SellerFinanceService;
use App\Services\Finance\WithdrawalService;
use App\Services\Marketing\VoucherService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Seller app screens beyond products and orders: shop settings, inventory, reviews, automated
 * replies, finance (password-confirmed) and marketing. Same services and rules as the Seller Center;
 * the shop always comes from the signed-in seller.
 */
class SellerAppController extends Controller
{
    public function __construct(
        private ShopProfileService $profiles,
        private SellerFinanceService $finance,
        private WithdrawalService $withdrawals,
        private ReviewService $reviews,
        private VoucherService $vouchers,
        private AuditLogger $auditLogger,
    ) {}

    // ---------------------------------------------------------------- shop settings

    public function shop(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->profiles->present($this->currentShop($request)) + [
            'categories' => ShopCategory::query()->orderBy('name')->get(['id', 'name']),
            'templates' => collect(ShopProfileService::TEMPLATES)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
        ]]);
    }

    public function updateShop(Request $request): JsonResponse
    {
        $validated = $request->validate(ShopProfileService::profileRules(), ['pickup_phone.regex' => 'Enter a valid Philippine mobile number.']);
        $shop = $this->profiles->updateProfile($this->currentShop($request), $validated);

        return response()->json(['data' => $this->profiles->present($shop)]);
    }

    /**
     * Logo, banner, template and accent colour (multipart when sending images).
     */
    public function branding(Request $request): JsonResponse
    {
        $validated = $request->validate(ShopProfileService::brandingRules());
        $shop = $this->profiles->updateBranding($this->currentShop($request), $validated + ['remove_logo' => $request->boolean('remove_logo'), 'remove_banner' => $request->boolean('remove_banner')], $request->file('logo'), $request->file('banner'));

        return response()->json(['data' => $this->profiles->present($shop)]);
    }

    public function vacation(Request $request): JsonResponse
    {
        $validated = $request->validate(ShopProfileService::vacationRules());
        $shop = $this->profiles->setVacation($this->currentShop($request), (bool) $validated['vacation_mode'], $validated['vacation_message'] ?? null);

        return response()->json(['data' => ['vacation_mode' => $shop->vacation_mode, 'vacation_message' => $shop->vacation_message]]);
    }

    // ---------------------------------------------------------------- inventory

    public function inventory(Request $request): JsonResponse
    {
        $validated = $request->validate(['search' => ['nullable', 'string', 'max:100'], 'low' => ['nullable', 'boolean']]);
        $shop = $this->currentShop($request);
        $page = ProductVariation::query()
            ->whereHas('product', fn ($products) => $products->where('shop_id', $shop->id)->when($validated['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%")))
            ->with('product:id,name,status')
            ->when($request->boolean('low'), fn ($query) => $query->whereRaw('stock - reserved <= 5'))
            ->orderBy('product_id')
            ->orderByDesc('is_default')
            ->paginate(30);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ProductVariation $variation) => [
                'id' => $variation->id,
                'product' => $variation->product ? ['id' => $variation->product->id, 'name' => $variation->product->name, 'status' => $variation->product->status->value] : null,
                'label' => $variation->label,
                'sku' => $variation->sku,
                'price' => $variation->price,
                'stock' => $variation->stock,
                'reserved' => $variation->reserved,
                'available' => max(0, $variation->stock - $variation->reserved),
                'is_active' => $variation->is_active,
            ])->values(),
            'meta' => $this->meta($page),
        ]);
    }

    // ---------------------------------------------------------------- reviews

    public function reviews(Request $request): JsonResponse
    {
        $validated = $request->validate(['filter' => ['nullable', 'in:unanswered,1,2,3,4,5']]);
        $shop = $this->currentShop($request);
        $filter = $validated['filter'] ?? null;
        $base = fn () => ProductReview::query()->whereHas('product', fn ($products) => $products->withTrashed()->where('shop_id', $shop->id));

        $page = $base()
            ->with(['product' => fn ($products) => $products->withTrashed()->select('id', 'name', 'slug'), 'user:id,name'])
            ->when($filter === 'unanswered', fn ($query) => $query->whereNull('seller_reply'))
            ->when(in_array($filter, ['1', '2', '3', '4', '5'], true), fn ($query) => $query->where('rating', (int) $filter))
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ProductReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'customer' => $review->user?->name,
                'product' => $review->product?->only(['id', 'name', 'slug']),
                'seller_reply' => $review->seller_reply,
                'replied_at' => $review->replied_at?->toIso8601String(),
                'created_at' => $review->created_at?->toIso8601String(),
            ])->values(),
            'meta' => $this->meta($page) + [
                'average' => (float) $shop->rating_avg,
                'unanswered' => $base()->whereNull('seller_reply')->count(),
            ],
        ]);
    }

    public function replyToReview(Request $request, ProductReview $review): JsonResponse
    {
        $validated = $request->validate(['reply' => ['required', 'string', 'max:1000']]);
        $review = $this->reviews->reply($review, $this->currentShop($request), $validated['reply']);

        return response()->json(['data' => ['id' => $review->id, 'seller_reply' => $review->seller_reply, 'replied_at' => $review->replied_at?->toIso8601String()]]);
    }

    // ---------------------------------------------------------------- automated replies

    public function autoReplies(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'replies' => $this->currentShop($request)->autoReplies()->orderBy('type')->orderBy('id')->get()->map(fn (ShopAutoReply $reply) => $this->autoReplyRow($reply))->values(),
            'types' => ['greeting', 'off_work', 'offline', 'keyword'],
            'timezone' => (string) app(SettingsService::class)->get('general', 'timezone', 'Asia/Manila'),
        ]]);
    }

    public function storeAutoReply(Request $request): JsonResponse
    {
        $shop = $this->currentShop($request);
        $validated = CustomerService::validatedReply($request);

        if ($validated['type'] !== 'keyword' && $shop->autoReplies()->where('type', $validated['type'])->exists()) {
            return response()->json(['message' => 'You already have one. Edit it instead.', 'errors' => ['type' => ['You already have one. Edit it instead.']]], 422);
        }

        return response()->json(['data' => $this->autoReplyRow($shop->autoReplies()->create($validated))], 201);
    }

    public function updateAutoReply(Request $request, ShopAutoReply $reply): JsonResponse
    {
        abort_unless($reply->shop_id === $this->currentShop($request)->id, 404);
        $reply->update(CustomerService::validatedReply($request, $reply));

        return response()->json(['data' => $this->autoReplyRow($reply->fresh())]);
    }

    public function toggleAutoReply(Request $request, ShopAutoReply $reply): JsonResponse
    {
        abort_unless($reply->shop_id === $this->currentShop($request)->id, 404);
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $reply->update(['is_active' => (bool) $validated['is_active']]);

        return response()->json(['data' => $this->autoReplyRow($reply)]);
    }

    public function destroyAutoReply(Request $request, ShopAutoReply $reply): JsonResponse
    {
        abort_unless($reply->shop_id === $this->currentShop($request)->id, 404);
        $reply->delete();

        return response()->json(null, 204);
    }

    // ---------------------------------------------------------------- finance (password-confirmed)

    public function financeOverview(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->finance->overview($this->currentShop($request)) + ['banks' => SellerFinanceService::BANKS]]);
    }

    public function statement(Request $request): JsonResponse
    {
        $validated = $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $result = $this->finance->statement($this->currentShop($request), $validated['month'] ?? null);
        $page = $result['details']->paginate(20);

        return response()->json([
            'data' => [
                'month' => $result['start']->format('Y-m'),
                'month_label' => $result['start']->format('F Y'),
                'statement' => $result['statement'],
                'orders' => $page->getCollection()->map(fn (SellerOrder $order) => SellerFinanceService::statementRow($order))->values(),
            ],
            'meta' => $this->meta($page),
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $page = $this->currentShop($request)->transactions()->latest('id')->paginate(30);

        return response()->json(['data' => $page->getCollection()->map(fn ($transaction) => SellerFinanceService::transactionRow($transaction))->values(), 'meta' => $this->meta($page)]);
    }

    public function bankAccounts(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->currentShop($request)->bankAccounts()->orderByDesc('is_default')->get()->map(fn (BankAccount $account) => SellerFinanceService::bankRow($account))->values()]);
    }

    public function storeBankAccount(Request $request): JsonResponse
    {
        $validated = $request->validate(SellerFinanceService::bankAccountRules(), ['account_number.regex' => 'Enter 8 to 20 digits.']);

        return response()->json(['data' => SellerFinanceService::bankRow($this->finance->addBankAccount($this->currentShop($request), $validated))], 201);
    }

    public function defaultBankAccount(Request $request, BankAccount $account): JsonResponse
    {
        return response()->json(['data' => SellerFinanceService::bankRow($this->finance->makeDefault($this->currentShop($request), $account))]);
    }

    public function destroyBankAccount(Request $request, BankAccount $account): JsonResponse
    {
        $this->finance->removeBankAccount($this->currentShop($request), $account);

        return response()->json(null, 204);
    }

    public function withdrawalsIndex(Request $request): JsonResponse
    {
        $page = $this->currentShop($request)->withdrawals()->with('bankAccount')->latest('id')->paginate(20);

        return response()->json(['data' => $page->getCollection()->map(fn (Withdrawal $withdrawal) => SellerFinanceService::withdrawalRow($withdrawal))->values(), 'meta' => $this->meta($page)]);
    }

    public function withdraw(Request $request): JsonResponse
    {
        $shop = $this->currentShop($request);
        $validated = $request->validate(['amount' => ['required', 'numeric', 'min:1'], 'bank_account_id' => ['required', 'integer']]);
        $account = $shop->bankAccounts()->find($validated['bank_account_id']);

        if (! $account) {
            return response()->json(['message' => 'Choose one of your payout accounts.', 'errors' => ['bank_account_id' => ['Choose one of your payout accounts.']]], 422);
        }

        $withdrawal = $this->withdrawals->request($shop, $account, Money::format($validated['amount']), $request->user());

        return response()->json(['data' => SellerFinanceService::withdrawalRow($withdrawal->load('bankAccount'))], 201);
    }

    // ---------------------------------------------------------------- marketing

    public function vouchersIndex(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'vouchers' => $this->currentShop($request)->vouchers()->withCount('redemptions')->withSum('redemptions', 'amount')->latest('id')->get()->map(fn (Voucher $voucher) => $this->voucherRow($voucher))->values(),
            'types' => VoucherType::options(),
        ]]);
    }

    public function storeVoucher(Request $request): JsonResponse
    {
        $voucher = $this->currentShop($request)->vouchers()->create(Marketing::validatedVoucher($request))->refresh();
        $this->auditLogger->log('voucher.created', $voucher, newValues: ['code' => $voucher->code], actor: $request->user());

        return response()->json(['data' => $this->voucherRow($voucher->loadCount('redemptions'))], 201);
    }

    public function updateVoucher(Request $request, Voucher $voucher): JsonResponse
    {
        abort_unless($voucher->shop_id === $this->currentShop($request)->id, 404);
        $voucher->update(Marketing::validatedVoucher($request, $voucher));

        return response()->json(['data' => $this->voucherRow($voucher->loadCount('redemptions'))]);
    }

    /**
     * Vouchers that were already used are switched off instead of deleted, to keep order records.
     */
    public function destroyVoucher(Request $request, Voucher $voucher): JsonResponse
    {
        abort_unless($voucher->shop_id === $this->currentShop($request)->id, 404);

        if ($voucher->redemptions()->exists()) {
            $voucher->update(['is_active' => false]);

            return response()->json(['data' => $this->voucherRow($voucher->loadCount('redemptions')), 'message' => 'Voucher was used before, so it was switched off instead of deleted.']);
        }

        $voucher->delete();

        return response()->json(null, 204);
    }

    public function promotionsIndex(Request $request): JsonResponse
    {
        $shop = $this->currentShop($request);

        return response()->json(['data' => [
            'promotions' => Promotion::query()->where('shop_id', $shop->id)->with('products:id,name')->latest('id')->get()->map(fn (Promotion $promotion) => $this->promotionRow($promotion))->values(),
            'products' => $shop->products()->whereIn('status', ['live', 'under_review', 'unpublished', 'draft'])->orderBy('name')->get(['id', 'name']),
        ]]);
    }

    public function storePromotion(Request $request): JsonResponse
    {
        $shop = $this->currentShop($request);
        $validated = Marketing::validatedPromotion($request, $shop->id);
        $promotion = Promotion::query()->create(collect($validated)->except('product_ids')->all() + ['shop_id' => $shop->id])->refresh();
        $promotion->products()->sync($validated['product_ids']);
        $this->auditLogger->log('promotion.created', $promotion, newValues: ['percent' => $promotion->discount_percent], actor: $request->user());

        return response()->json(['data' => $this->promotionRow($promotion->load('products:id,name'))], 201);
    }

    public function updatePromotion(Request $request, Promotion $promotion): JsonResponse
    {
        $shop = $this->currentShop($request);
        abort_unless($promotion->shop_id === $shop->id, 404);
        $validated = Marketing::validatedPromotion($request, $shop->id);
        $old = ['percent' => $promotion->discount_percent, 'active' => $promotion->is_active, 'products' => $promotion->products()->pluck('products.id')->all()];
        $promotion->update(collect($validated)->except('product_ids')->all());
        $promotion->products()->sync($validated['product_ids']);
        $this->auditLogger->log('promotion.updated', $promotion, $old, ['percent' => $promotion->discount_percent, 'active' => $promotion->is_active, 'products' => array_map('intval', $validated['product_ids'])], $request->user());

        return response()->json(['data' => $this->promotionRow($promotion->load('products:id,name'))]);
    }

    public function destroyPromotion(Request $request, Promotion $promotion): JsonResponse
    {
        abort_unless($promotion->shop_id === $this->currentShop($request)->id, 404);
        $this->auditLogger->log('promotion.deleted', $promotion, ['name' => $promotion->name, 'percent' => $promotion->discount_percent], actor: $request->user());
        $promotion->delete();

        return response()->json(null, 204);
    }

    public function referrals(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->customerProfile()->firstOrCreate([], ['referral_code' => strtoupper(Str::random(8))]);
        $referred = CustomerProfile::query()->where('referred_by', $user->id)->with('user:id,name,created_at')->latest('id')->get();

        return response()->json(['data' => [
            'code' => $profile->referral_code,
            'link' => route('register', ['ref' => $profile->referral_code]),
            'referred' => $referred->map(fn ($customer) => ['name' => $customer->user?->name, 'joined_at' => $customer->user?->created_at?->toIso8601String()])->values(),
        ]]);
    }

    // ---------------------------------------------------------------- helpers

    private function currentShop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    /**
     * @return array{current_page: int, last_page: int, total: int}
     */
    private function meta($page): array
    {
        return ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()];
    }

    /**
     * @return array<string, mixed>
     */
    private function autoReplyRow(ShopAutoReply $reply): array
    {
        return $reply->only(['id', 'type', 'keyword', 'message', 'schedule', 'is_active']);
    }

    /**
     * @return array<string, mixed>
     */
    private function voucherRow(Voucher $voucher): array
    {
        return [
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
            'discount_given' => Money::format($voucher->redemptions_sum_amount ?? 0),
            'starts_at' => $voucher->starts_at?->toIso8601String(),
            'ends_at' => $voucher->ends_at?->toIso8601String(),
            'is_active' => $voucher->is_active,
            'label' => $this->vouchers->describe($voucher),
            'state' => Marketing::state($voucher->is_active, $voucher->starts_at, $voucher->ends_at, $voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function promotionRow(Promotion $promotion): array
    {
        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'discount_percent' => $promotion->discount_percent,
            'starts_at' => $promotion->starts_at?->toIso8601String(),
            'ends_at' => $promotion->ends_at?->toIso8601String(),
            'is_active' => $promotion->is_active,
            'products' => $promotion->products->map->only(['id', 'name'])->values(),
            'state' => Marketing::state($promotion->is_active, $promotion->starts_at, $promotion->ends_at),
        ];
    }
}
