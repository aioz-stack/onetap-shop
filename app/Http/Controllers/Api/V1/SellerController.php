<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\ProductStatus;
use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Seller\Products;
use App\Models\Product;
use App\Models\ProductVariation;
use App\Models\SellerOrder;
use App\Models\Shop;
use App\Services\Catalog\SellerProductService;
use App\Services\Ordering\SellerOrderService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Seller Center endpoints for the mobile apps. The shop always comes from the signed-in seller
 * (EnsureSellerShop); every action uses the same services and validation as the web Seller Center.
 */
class SellerController extends Controller
{
    public function __construct(private SellerProductService $products, private SellerOrderService $orders) {}

    public function dashboard(Request $request): JsonResponse
    {
        $shop = $this->shop($request)->load('balance');
        $orders = fn () => SellerOrder::query()->where('shop_id', $shop->id);

        return response()->json(['data' => [
            'shop' => $shop->only(['id', 'name', 'slug', 'vacation_mode']) + ['status' => $shop->status->value],
            'stats' => [
                'total_sales' => Money::format($orders()->where('status', SellerOrderStatus::Completed)->sum('items_subtotal')),
                'orders' => $orders()->count(),
                'pending_orders' => $orders()->whereIn('status', SellerOrderStatus::tabs()['to_ship']['statuses'])->count(),
                'products_sold' => (int) $shop->products()->withTrashed()->sum('sold_count'),
                'products' => $shop->products()->count(),
                'pending_balance' => Money::format($shop->balance?->pending_balance),
                'available_balance' => Money::format($shop->balance?->available_balance),
            ],
        ]]);
    }

    // ---------------------------------------------------------------- products

    public function products(Request $request): JsonResponse
    {
        $validated = $request->validate(['status' => ['nullable', Rule::enum(ProductStatus::class)], 'search' => ['nullable', 'string', 'max:100']]);
        $page = $this->shop($request)->products()
            ->with('primaryImage')
            ->withSum('variations as stock_total', 'stock')
            ->withSum('variations as reserved_total', 'reserved')
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', "%{$search}%"))
            ->latest('updated_at')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (Product $product) => $this->productRow($product))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function product(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        return response()->json(['data' => $this->productDetail($product)]);
    }

    public function storeProduct(Request $request): JsonResponse
    {
        $validated = $request->validate(Products::rules(true), Products::messages());
        $product = $this->products->create($this->shop($request), $request->user(), $validated, $request->file('images', []), $request->boolean('submit'));

        return response()->json(['data' => $this->productDetail($product)], 201);
    }

    public function updateProduct(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);
        $validated = $request->validate(Products::rules(false), Products::messages());

        $updated = $this->products->update(
            $product,
            $request->user(),
            $validated,
            $request->file('images', []),
            array_map('intval', $validated['remove_images'] ?? []),
            isset($validated['primary_image_id']) ? (int) $validated['primary_image_id'] : null,
            $request->boolean('submit'),
        );

        return response()->json(['data' => $this->productDetail($updated)]);
    }

    public function productAction(Request $request, Product $product, string $action): JsonResponse
    {
        $this->authorizeProduct($request, $product);

        $result = match ($action) {
            'submit' => $this->products->submit($product, $request->user()),
            'unpublish' => $this->products->unpublish($product, $request->user()),
            'republish' => $this->products->republish($product, $request->user()),
        };

        return response()->json(['data' => $this->productDetail($result)]);
    }

    public function destroyProduct(Request $request, Product $product): JsonResponse
    {
        $this->authorizeProduct($request, $product);
        $this->products->delete($product, $request->user());

        return response()->json(null, 204);
    }

    public function adjustStock(Request $request, ProductVariation $variation): JsonResponse
    {
        $this->authorizeProduct($request, $variation->product()->withTrashed()->firstOrFail());
        $validated = $request->validate(['stock' => ['required', 'integer', 'min:0', 'max:100000'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->products->adjustStock($variation, $validated['stock'], $request->user(), $validated['note'] ?? null);
        $variation->refresh();

        return response()->json(['data' => ['id' => $variation->id, 'stock' => $variation->stock, 'reserved' => $variation->reserved, 'available' => max(0, $variation->stock - $variation->reserved)]]);
    }

    // ---------------------------------------------------------------- orders

    public function orders(Request $request): JsonResponse
    {
        $tabs = SellerOrderStatus::tabs();
        $validated = $request->validate(['tab' => ['nullable', Rule::in(array_keys($tabs))]]);
        $page = $this->shop($request)->sellerOrders()
            ->with(['order:id,order_number,payment_method,placed_at,user_id', 'order.customer:id,name', 'items', 'shipment:id,seller_order_id,tracking_number,status'])
            ->when($validated['tab'] ?? null, fn ($query, $tab) => $query->whereIn('status', $tabs[$tab]['statuses']))
            ->latest('id')
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (SellerOrder $order) => $this->orderRow($order))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'tabs' => array_keys($tabs)],
        ]);
    }

    public function order(Request $request, SellerOrder $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $order->load(['order.customer:id,name', 'order.address', 'items', 'shipment', 'statusHistories']);

        return response()->json(['data' => $this->orderRow($order) + [
            'address' => $order->order?->address?->only(['recipient_name', 'phone', 'street', 'barangay', 'city', 'province', 'postal_code']),
            'history' => $order->statusHistories->sortByDesc('id')->values()->map(fn ($history) => ['to' => $history->to_status, 'note' => $history->note, 'created_at' => $history->created_at?->toIso8601String()]),
        ]]);
    }

    /**
     * Seller order actions: accept, ready (for pickup) or cancel (with a reason).
     */
    public function orderStatus(Request $request, SellerOrder $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);
        $validated = $request->validate([
            'action' => ['required', Rule::in(['accept', 'ready', 'cancel'])],
            'reason' => ['required_if:action,cancel', 'nullable', 'string', 'max:255'],
        ]);
        $shop = $this->shop($request);

        $updated = match ($validated['action']) {
            'accept' => $this->orders->accept($order, $shop, $request->user()),
            'ready' => $this->orders->readyForPickup($order, $shop, $request->user()),
            'cancel' => $this->orders->cancel($order, $shop, $request->user(), $validated['reason']),
        };

        return response()->json(['data' => $this->orderRow($updated->fresh(['order.customer', 'items', 'shipment']))]);
    }

    // ---------------------------------------------------------------- helpers

    private function shop(Request $request): Shop
    {
        return $request->attributes->get('shop');
    }

    private function authorizeProduct(Request $request, Product $product): void
    {
        abort_unless($product->shop_id === $this->shop($request)->id, 404);
    }

    private function authorizeOrder(Request $request, SellerOrder $order): void
    {
        abort_unless($order->shop_id === $this->shop($request)->id, 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function productRow(Product $product): array
    {
        return [
            'id' => $product->id,
            'name' => $product->name,
            'slug' => $product->slug,
            'status' => $product->status->value,
            'rejection_reason' => $product->rejection_reason,
            'price' => $product->base_price,
            'available' => max(0, (int) $product->stock_total - (int) $product->reserved_total),
            'sold_count' => $product->sold_count,
            'image' => $product->primaryImage ? asset('storage/'.$product->primaryImage->path) : null,
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productDetail(Product $product): array
    {
        $product->load(['images' => fn ($query) => $query->orderBy('sort_order'), 'variations' => fn ($query) => $query->orderByDesc('is_default')->orderBy('id')])
            ->loadSum('variations as stock_total', 'stock')
            ->loadSum('variations as reserved_total', 'reserved');

        return $this->productRow($product) + [
            'description' => $product->description,
            'brand' => $product->brand,
            'product_category_id' => $product->product_category_id,
            'weight_grams' => $product->weight_grams,
            'images' => $product->images->map(fn ($image) => ['id' => $image->id, 'url' => asset('storage/'.$image->path), 'is_primary' => $image->is_primary]),
            'variations' => $product->variations->map(fn (ProductVariation $variation) => $variation->only(['id', 'label', 'price', 'compare_at_price', 'stock', 'reserved', 'sku', 'is_active'])),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(SellerOrder $order): array
    {
        return [
            'id' => $order->id,
            'reference' => $order->reference,
            'status' => $order->status->value,
            'customer' => $order->order?->customer?->name,
            'payment_method' => $order->order?->payment_method->value,
            'items' => $order->items->map(fn ($item) => ['product_name' => $item->product_name, 'variation' => $item->variation_label, 'quantity' => $item->quantity, 'unit_price' => $item->unit_price, 'line_total' => $item->line_total]),
            'items_subtotal' => $order->items_subtotal,
            'shipping_fee' => $order->shipping_fee,
            'discount' => $order->discount,
            'total' => $order->total,
            'commission_amount' => $order->commission_amount,
            'seller_earning' => $order->seller_earning,
            'shipment' => $order->shipment ? ['tracking_number' => $order->shipment->tracking_number, 'status' => $order->shipment->status->value] : null,
            'placed_at' => $order->order?->placed_at?->toIso8601String(),
        ];
    }
}
