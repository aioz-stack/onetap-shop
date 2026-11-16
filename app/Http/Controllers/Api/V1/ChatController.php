<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Shop;
use App\Models\User;
use App\Services\Communication\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Customer ↔ shop chat for both apps.
 *
 * - Customer app: `scope=customer` (default) lists the shops you're talking to; start a chat with POST /chats.
 * - Seller app: `scope=shop` lists your shop's inbox (needs an approved shop).
 *
 * Only participants can read or write a conversation (404 otherwise). There's no websocket yet:
 * poll GET /chats/{id}?after_id=<last id> every few seconds while the chat is open.
 */
class ChatController extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['scope' => ['nullable', Rule::in(['customer', 'shop'])]]);
        $user = $request->user();
        $shop = ($validated['scope'] ?? 'customer') === 'shop' ? $this->ownShop($user) : null;

        $conversations = $user->conversations()
            ->with(['shop.sellerProfile:id,user_id', 'participants:id,name,profile_picture', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->when($shop, fn ($query) => $query->where('shop_id', $shop->id), fn ($query) => $query->whereHas('shop', fn ($shops) => $shops->whereDoesntHave('sellerProfile', fn ($profiles) => $profiles->where('user_id', $user->id))))
            ->orderByDesc('last_message_at')
            ->paginate(30);

        return response()->json([
            'data' => $conversations->getCollection()->map(fn (Conversation $conversation) => $this->summary($conversation, $user))->values(),
            'meta' => ['current_page' => $conversations->currentPage(), 'last_page' => $conversations->lastPage(), 'total' => $conversations->total(), 'unread_messages' => $this->chat->unreadCount($user)],
        ]);
    }

    /**
     * Start (or reopen) a chat with a shop from the customer app.
     */
    public function start(Request $request): JsonResponse
    {
        $validated = $request->validate(['shop_id' => ['required', 'integer', 'exists:shops,id']]);
        $conversation = $this->chat->conversationWith($request->user(), Shop::query()->with('sellerProfile.user')->findOrFail($validated['shop_id']));

        return response()->json(['data' => $this->summary($conversation->load(['shop.sellerProfile:id,user_id', 'participants', 'messages' => fn ($query) => $query->latest('id')->limit(1)]), $request->user())], $conversation->wasRecentlyCreated ? 201 : 200);
    }

    /**
     * Messages, oldest first. `after_id` returns only newer messages (polling); `before_id` loads older ones.
     * Opening a conversation marks it read.
     */
    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $user = $request->user();
        $this->chat->assertParticipant($conversation, $user);
        $validated = $request->validate(['after_id' => ['nullable', 'integer'], 'before_id' => ['nullable', 'integer']]);

        $messages = $conversation->messages()
            ->with('sender:id,name')
            ->when($validated['after_id'] ?? null, fn ($query, $after) => $query->where('id', '>', $after)->oldest('id'))
            ->when($validated['before_id'] ?? null, fn ($query, $before) => $query->where('id', '<', $before)->latest('id'))
            ->when(! isset($validated['after_id']) && ! isset($validated['before_id']), fn ($query) => $query->latest('id'))
            ->limit(50)
            ->get()
            ->sortBy('id')
            ->values();

        $this->chat->markRead($conversation, $user);

        return response()->json([
            'data' => $this->summary($conversation->load(['shop.sellerProfile:id,user_id', 'participants', 'messages' => fn ($query) => $query->latest('id')->limit(1)]), $user) + [
                'messages' => $messages->map(fn (Message $message) => $this->message($message, $user)),
            ],
        ]);
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $user = $request->user();
        $message = $this->chat->send($conversation, $user, $validated['body']);

        // Include any automated shop replies triggered by this message.
        $replies = $conversation->messages()->where('id', '>', $message->id)->where('is_auto_reply', true)->oldest('id')->get();

        return response()->json(['data' => [
            'message' => $this->message($message->load('sender:id,name'), $user),
            'auto_replies' => $replies->map(fn (Message $reply) => $this->message($reply, $user))->values(),
        ]], 201);
    }

    public function markRead(Request $request, Conversation $conversation): JsonResponse
    {
        $this->chat->assertParticipant($conversation, $request->user());
        $this->chat->markRead($conversation, $request->user());

        return response()->json(['message' => 'Marked as read.']);
    }

    private function ownShop(User $user): Shop
    {
        $profile = $user->sellerProfile()->with('shop')->first();
        abort_unless($profile?->shop && in_array('seller', $user->mobileApps(), true), 403, 'Your seller account is not active.');

        return $profile->shop;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Conversation $conversation, User $user): array
    {
        $last = $conversation->messages->first();
        $other = $conversation->participants->firstWhere('id', '!=', $user->id);
        $isShopSide = $conversation->shop?->sellerProfile?->user_id === $user->id;
        $unread = $this->chat->unreadIn($conversation, $user);

        return [
            'id' => $conversation->id,
            'role' => $isShopSide ? 'shop' : 'customer',
            'shop' => $conversation->shop ? ['id' => $conversation->shop->id, 'name' => $conversation->shop->name, 'slug' => $conversation->shop->slug, 'logo_url' => $conversation->shop->logo ? asset('storage/'.$conversation->shop->logo) : null] : null,
            // Seller app shows the customer; customer app shows the shop.
            'customer' => $isShopSide && $other ? ['id' => $other->id, 'name' => $other->name, 'profile_picture_url' => $other->profile_picture ? asset('storage/'.$other->profile_picture) : null] : null,
            'last_message' => $last ? ['id' => $last->id, 'body' => $last->body, 'mine' => $last->user_id === $user->id, 'created_at' => $last->created_at?->toIso8601String()] : null,
            'last_message_at' => $conversation->last_message_at?->toIso8601String(),
            'unread' => $unread > 0,
            'unread_count' => $unread,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function message(Message $message, User $user): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'mine' => $message->user_id === $user->id,
            'sender' => $message->relationLoaded('sender') ? $message->sender?->name : null,
            'auto_reply' => $message->is_auto_reply,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }
}
