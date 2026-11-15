<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\ProductVariation;
use App\Services\Ordering\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class Cart extends Controller
{
    public function __construct(private CartService $carts) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Customer/Cart', $this->carts->summarize($user) + [
            'hasAddress' => $user->addresses()->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'variation_id' => ['required', 'integer', 'exists:product_variations,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
            'buy_now' => ['boolean'],
        ]);

        $variation = ProductVariation::query()->findOrFail($validated['variation_id']);
        $this->carts->add($request->user(), $variation, $validated['quantity']);

        if ($validated['buy_now'] ?? false) {
            // Buy now checks out just this item.
            $this->carts->selectAll($request->user(), false);
            $this->carts->cartFor($request->user())->items()->where('product_variation_id', $variation->id)->update(['is_selected' => true]);

            return redirect()->route('checkout.show');
        }

        return back()->with('success', 'Added to your cart.');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        $validated = $request->validate([
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.CartService::MAX_QUANTITY],
            'is_selected' => ['nullable', 'boolean'],
        ]);

        $this->carts->update($item->load('variation'), $validated['quantity'] ?? null, $validated['is_selected'] ?? null);

        return back();
    }

    public function destroy(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($request, $item);
        $item->delete();

        return back()->with('success', 'Removed from your cart.');
    }

    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'selected' => ['required', 'boolean'],
            'shop_id' => ['nullable', 'integer'],
        ]);

        $this->carts->selectAll($request->user(), $validated['selected'], $validated['shop_id'] ?? null);

        return back();
    }

    private function authorizeItem(Request $request, CartItem $item): void
    {
        abort_unless($item->cart?->user_id === $request->user()->id, 404);
    }
}
