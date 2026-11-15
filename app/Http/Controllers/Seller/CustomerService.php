<?php

namespace App\Http\Controllers\Seller;

use App\Models\Conversation;
use App\Models\ProductReview;
use App\Models\ShopAutoReply;
use App\Services\Catalog\ReviewService;
use App\Services\Communication\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Seller chat inbox, automated replies and review management.
 */
class CustomerService extends SellerController
{
    public function __construct(private ChatService $chat, private ReviewService $reviews) {}

    public function inbox(Request $request, ?Conversation $conversation = null): Response
    {
        $shop = $this->shop($request);
        $user = $request->user();

        if ($conversation) {
            abort_unless($conversation->shop_id === $shop->id, 404);
            $this->chat->markRead($conversation, $user);
        }

        $conversations = $this->chat->shopInbox($shop)->map(function (Conversation $item) use ($user) {
            $customer = $item->participants->firstWhere('id', '!=', $user->id);
            $last = $item->messages->first();
            $readAt = $item->participants->firstWhere('id', $user->id)?->pivot?->last_read_at;

            return [
                'id' => $item->id,
                'customer' => $customer?->name ?? 'Customer',
                'last_message' => $last?->body,
                'last_message_at' => $item->last_message_at?->toIso8601String(),
                'unread' => $last && $last->user_id !== $user->id && (! $readAt || $last->created_at > $readAt),
            ];
        });

        return Inertia::render('Seller/CustomerService/Inbox', [
            'conversations' => $conversations,
            'active' => $conversation ? [
                'id' => $conversation->id,
                'customer' => $conversation->participants()->where('users.id', '!=', $user->id)->value('name'),
                'messages' => $conversation->messages()->oldest('id')->limit(200)->get()->map(fn ($message) => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'mine' => $message->user_id === $user->id,
                    'auto' => $message->is_auto_reply,
                    'created_at' => $message->created_at?->toIso8601String(),
                ]),
            ] : null,
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        abort_unless($conversation->shop_id === $this->shop($request)->id, 404);
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $this->chat->send($conversation, $request->user(), $validated['body']);

        return back();
    }

    public function autoReplies(Request $request): Response
    {
        return Inertia::render('Seller/CustomerService/AutoReplies', [
            'replies' => $this->shop($request)->autoReplies()->orderBy('type')->orderBy('id')->get()->map->only(['id', 'type', 'keyword', 'message', 'is_active']),
        ]);
    }

    public function storeAutoReply(Request $request): RedirectResponse
    {
        $shop = $this->shop($request);
        $validated = $this->validatedReply($request);

        if ($validated['type'] !== 'keyword' && $shop->autoReplies()->where('type', $validated['type'])->exists()) {
            return back()->withErrors(['type' => 'You already have one. Edit it instead.']);
        }

        $shop->autoReplies()->create($validated);

        return back()->with('success', 'Automated reply saved.');
    }

    public function updateAutoReply(Request $request, ShopAutoReply $reply): RedirectResponse
    {
        abort_unless($reply->shop_id === $this->shop($request)->id, 404);
        $reply->update($this->validatedReply($request, $reply));

        return back()->with('success', 'Automated reply updated.');
    }

    public function destroyAutoReply(Request $request, ShopAutoReply $reply): RedirectResponse
    {
        abort_unless($reply->shop_id === $this->shop($request)->id, 404);
        $reply->delete();

        return back()->with('success', 'Automated reply removed.');
    }

    public function reviews(Request $request): Response
    {
        $shop = $this->shop($request);
        $filter = $request->query('filter');

        $query = ProductReview::query()
            ->whereHas('product', fn ($products) => $products->withTrashed()->where('shop_id', $shop->id))
            ->with(['product' => fn ($products) => $products->withTrashed()->select('id', 'name', 'slug'), 'user:id,name'])
            ->when($filter === 'unanswered', fn ($inner) => $inner->whereNull('seller_reply'))
            ->when(in_array($filter, ['1', '2', '3', '4', '5'], true), fn ($inner) => $inner->where('rating', (int) $filter));

        return Inertia::render('Seller/CustomerService/Reviews', [
            'reviews' => $query->latest('id')->paginate(15)->withQueryString()->through(fn (ProductReview $review) => [
                'id' => $review->id,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'customer' => $review->user?->name,
                'product' => $review->product?->only(['id', 'name', 'slug']),
                'seller_reply' => $review->seller_reply,
                'replied_at' => $review->replied_at?->toIso8601String(),
                'created_at' => $review->created_at?->toIso8601String(),
            ]),
            'filter' => $filter,
            'summary' => [
                'average' => (float) $shop->rating_avg,
                'total' => ProductReview::query()->whereHas('product', fn ($products) => $products->withTrashed()->where('shop_id', $shop->id))->count(),
                'unanswered' => ProductReview::query()->whereHas('product', fn ($products) => $products->withTrashed()->where('shop_id', $shop->id))->whereNull('seller_reply')->count(),
            ],
        ]);
    }

    public function replyToReview(Request $request, ProductReview $review): RedirectResponse
    {
        $validated = $request->validate(['reply' => ['required', 'string', 'max:1000']]);
        $this->reviews->reply($review, $this->shop($request), $validated['reply']);

        return back()->with('success', 'Reply posted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedReply(Request $request, ?ShopAutoReply $reply = null): array
    {
        return $request->validate([
            'type' => [$reply ? 'prohibited' : 'required', Rule::in(['greeting', 'offline', 'keyword'])],
            'keyword' => [Rule::requiredIf(fn () => ($reply?->type ?? $request->input('type')) === 'keyword'), 'nullable', 'string', 'max:50'],
            'message' => ['required', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ], ['type.prohibited' => 'The reply type cannot be changed.']);
    }
}
