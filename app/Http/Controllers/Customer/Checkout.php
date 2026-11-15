<?php

namespace App\Http\Controllers\Customer;

use App\Enums\PaymentMethod;
use App\Http\Controllers\Controller;
use App\Http\Resources\AddressResource;
use App\Models\Order;
use App\Models\UserAddress;
use App\Services\Administration\SettingsService;
use App\Services\Ordering\CartService;
use App\Services\Ordering\CheckoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class Checkout extends Controller
{
    public function __construct(
        private CartService $carts,
        private CheckoutService $checkout,
        private SettingsService $settings,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $user = $request->user();
        $addressId = $request->integer('address') ?: null;
        $address = $addressId ? $user->addresses()->find($addressId) : null;
        $vouchers = array_filter((array) $request->query('vouchers', []), fn ($code) => is_string($code) && $code !== '');
        $cart = $this->carts->summarize($user, $address, selectedOnly: true, vouchers: $vouchers);

        if ($cart['summary']['selected_lines'] === 0) {
            return redirect()->route('cart.index')->with('error', 'Select at least one item to check out.');
        }

        return Inertia::render('Customer/Checkout', $cart + [
            'addresses' => AddressResource::collection($user->addresses()->orderByDesc('is_default')->get())->resolve(),
            'paymentMethods' => [
                ['value' => PaymentMethod::Cod->value, 'label' => 'Cash on Delivery', 'available' => $this->settings->get('payment', 'cod_enabled', '1') === '1', 'note' => 'Pay the rider when your order arrives.'],
                ['value' => PaymentMethod::Gcash->value, 'label' => 'GCash', 'available' => false, 'note' => 'Coming soon'],
                ['value' => PaymentMethod::Maya->value, 'label' => 'Maya', 'available' => false, 'note' => 'Coming soon'],
                ['value' => PaymentMethod::Card->value, 'label' => 'Credit / debit card', 'available' => false, 'note' => 'Coming soon'],
            ],
            'codLimit' => $this->settings->get('payment', 'cod_max_amount'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address_id' => ['required', 'integer'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'notes' => ['nullable', 'string', 'max:500'],
            'vouchers' => ['nullable', 'array'],
            'vouchers.*' => ['nullable', 'string', 'max:40'],
        ]);

        $address = UserAddress::query()->where('user_id', $request->user()->id)->find($validated['address_id']);

        if (! $address) {
            return back()->withErrors(['address_id' => 'Choose one of your saved addresses.']);
        }

        $order = $this->checkout->placeOrder($request->user(), $address, PaymentMethod::from($validated['payment_method']), $validated['notes'] ?? null, array_filter($validated['vouchers'] ?? []));

        return redirect()->route('checkout.success', $order->order_number);
    }

    public function success(Request $request, string $orderNumber): Response
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->where('user_id', $request->user()->id)
            ->with(['sellerOrders.shop:id,name', 'address'])
            ->firstOrFail();

        return Inertia::render('Customer/OrderPlaced', [
            'order' => [
                'order_number' => $order->order_number,
                'grand_total' => $order->grand_total,
                'payment_method' => $order->payment_method->value,
                'address' => $order->address?->only(['recipient_name', 'phone', 'street', 'barangay', 'city', 'province']),
                'seller_orders' => $order->sellerOrders->map(fn ($sellerOrder) => [
                    'id' => $sellerOrder->id,
                    'reference' => $sellerOrder->reference,
                    'shop' => $sellerOrder->shop?->name,
                    'total' => $sellerOrder->total,
                ]),
            ],
        ]);
    }
}
