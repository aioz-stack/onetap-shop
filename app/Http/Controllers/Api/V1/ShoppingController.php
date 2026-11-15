<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\PaymentMethod;
use App\Enums\SellerOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Resources\UserResource;
use App\Models\CartItem;
use App\Models\OrderItem;
use App\Models\ProductVariation;
use App\Models\Refund;
use App\Models\SellerOrder;
use App\Models\UserAddress;
use App\Services\Catalog\ReviewService;
use App\Services\Identity\AccountService;
use App\Services\Ordering\CartService;
use App\Services\Ordering\CheckoutService;
use App\Services\Ordering\CustomerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Customer endpoints for the mobile apps: profile, addresses, cart, checkout and orders.
 * Every action goes through the same services as the web storefront.
 */
class ShoppingController extends Controller
{
    public function __construct(
        private CartService $carts,
        private CheckoutService $checkout,
        private CustomerOrderService $orders,
        private AccountService $accounts,
    ) {}

    public function profile(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles'));
    }

    public function updateProfile(Request $request): UserResource
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'regex:/^(\+63|0)9\d{9}$/', Rule::unique('users', 'phone')->ignore($user)],
        ]);

        return new UserResource($this->accounts->updateProfile($user, $validated)->load('roles'));
    }

    public function addresses(Request $request): AnonymousResourceCollection
    {
        return AddressResource::collection($request->user()->addresses()->orderByDesc('is_default')->get());
    }

    public function storeAddress(AddressRequest $request): JsonResponse
    {
        return (new AddressResource($this->accounts->saveAddress($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function updateAddress(AddressRequest $request, UserAddress $address): AddressResource
    {
        abort_unless($address->user_id === $request->user()->id, 404);

        return new AddressResource($this->accounts->saveAddress($request->user(), $request->validated(), $address));
    }

    public function destroyAddress(Request $request, UserAddress $address): JsonResponse
    {
        abort_unless($address->user_id === $request->user()->id, 404);
        $this->accounts->deleteAddress($address);

        return response()->json(['message' => 'Address removed.']);
    }

    public function cart(Request $request): JsonResponse
    {
        $address = $request->integer('address_id') ? $request->user()->addresses()->find($request->integer('address_id')) : null;

        return response()->json(['data' => $this->carts->summarize($request->user(), $address, vouchers: (array) $request->query('vouchers', []))]);
    }

    public function addToCart(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'variation_id' => ['required', 'integer', 'exists:product_variations,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
        ]);

        $this->carts->add($request->user(), ProductVariation::query()->findOrFail($validated['variation_id']), $validated['quantity']);

        return response()->json(['data' => $this->carts->summarize($request->user())], 201);
    }

    public function updateCartItem(Request $request, CartItem $item): JsonResponse
    {
        abort_unless($item->cart?->user_id === $request->user()->id, 404);
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
            'is_selected' => ['nullable', 'boolean'],
        ]);

        $this->carts->update($item->load('variation'), $validated['quantity'] ?? null, $validated['is_selected'] ?? null);

        return response()->json(['data' => $this->carts->summarize($request->user())]);
    }

    public function removeCartItem(Request $request, CartItem $item): JsonResponse
    {
        abort_unless($item->cart?->user_id === $request->user()->id, 404);
        $item->delete();

        return response()->json(['data' => $this->carts->summarize($request->user())]);
    }

    public function placeOrder(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:500'],
            'vouchers' => ['nullable', 'array'],
            'vouchers.*' => ['nullable', 'string', 'max:40'],
        ]);

        $address = UserAddress::query()->where('user_id', $request->user()->id)->findOrFail($validated['address_id']);
        $order = $this->checkout->placeOrder($request->user(), $address, PaymentMethod::from($validated['payment_method']), $validated['notes'] ?? null, array_filter($validated['vouchers'] ?? []));
        $order->load(['sellerOrders.shop', 'sellerOrders.items', 'sellerOrders.order']);

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'grand_total' => $order->grand_total,
                'shipping_total' => $order->shipping_total,
                'seller_orders' => CustomerOrderResource::collection($order->sellerOrders)->resolve(),
            ],
        ], 201);
    }

    public function orders(Request $request): AnonymousResourceCollection
    {
        $statuses = SellerOrderStatus::tabs()[$request->query('tab')]['statuses'] ?? null;

        return CustomerOrderResource::collection(
            SellerOrder::query()
                ->whereHas('order', fn ($orders) => $orders->where('user_id', $request->user()->id))
                ->when($statuses, fn ($query) => $query->whereIn('status', $statuses))
                ->with(['order:id,order_number,payment_method,payment_status,placed_at', 'shop:id,name,slug', 'items', 'refunds'])
                ->latest('id')
                ->paginate(15)
                ->withQueryString()
        );
    }

    public function order(Request $request, SellerOrder $order): CustomerOrderResource
    {
        abort_unless($order->order()->value('user_id') === $request->user()->id, 404);

        return new CustomerOrderResource($order->load(['order.address', 'shop:id,name,slug', 'items.review', 'statusHistories', 'shipment', 'refunds']));
    }

    public function cancelOrder(Request $request, SellerOrder $order): CustomerOrderResource
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return new CustomerOrderResource($this->orders->cancel($order, $request->user(), $validated['reason'])->load(['order', 'shop']));
    }

    public function confirmOrder(Request $request, SellerOrder $order): CustomerOrderResource
    {
        return new CustomerOrderResource($this->orders->confirmReceived($order, $request->user())->load(['order', 'shop']));
    }

    public function requestRefund(Request $request, SellerOrder $order): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
            'details' => ['nullable', 'string', 'max:2000'],
            'amount' => ['nullable', 'numeric', 'min:1'],
        ]);
        $refund = $this->orders->requestRefund($order, $request->user(), $validated);

        return response()->json(['data' => $refund->only(['id', 'reason', 'details', 'amount']) + ['status' => $refund->status->value, 'seller_order_id' => $order->id]], 201);
    }

    public function refunds(Request $request): JsonResponse
    {
        $refunds = Refund::query()->where('user_id', $request->user()->id)->with('sellerOrder:id,reference')->latest('id')->paginate(20);

        return response()->json([
            'data' => $refunds->getCollection()->map(fn (Refund $refund) => $refund->only(['id', 'reason', 'amount']) + ['status' => $refund->status->value, 'order' => $refund->sellerOrder?->reference, 'created_at' => $refund->created_at?->toIso8601String()])->values(),
            'meta' => ['current_page' => $refunds->currentPage(), 'last_page' => $refunds->lastPage(), 'total' => $refunds->total()],
        ]);
    }

    /**
     * Review one item of a completed order.
     */
    public function review(Request $request, OrderItem $item): JsonResponse
    {
        $validated = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:2000']]);
        $review = app(ReviewService::class)->create($item, $request->user(), $validated['rating'], $validated['comment'] ?? null);

        return response()->json(['data' => $review->only(['id', 'product_id', 'rating', 'comment']) + ['created_at' => $review->created_at?->toIso8601String()]], 201);
    }
}
