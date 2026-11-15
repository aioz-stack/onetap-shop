<?php

namespace App\Http\Controllers\Customer\Account;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Shop;
use App\Services\Communication\ChatService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Customer side of shop chat. Messages refresh by polling (no websockets yet).
 */
class Chat extends Controller
{
    public function __construct(private ChatService $chat) {}

    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        $user = $request->user();

        if ($conversation) {
            $this->chat->assertParticipant($conversation, $user);
            $this->chat->markRead($conversation, $user);
        }

        $conversations = $user->conversations()
            ->with(['shop:id,name,slug,logo', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $item) => [
                'id' => $item->id,
                'shop' => $item->shop?->only(['id', 'name', 'slug']),
                'last_message' => $item->messages->first()?->body,
                'last_message_at' => $item->last_message_at?->toIso8601String(),
                'unread' => $item->messages->first() && $item->messages->first()->user_id !== $user->id
                    && (! $item->pivot->last_read_at || $item->messages->first()->created_at > $item->pivot->last_read_at),
            ]);

        return Inertia::render('Customer/Account/Chat', [
            'conversations' => $conversations,
            'active' => $conversation ? [
                'id' => $conversation->id,
                'shop' => $conversation->shop?->only(['id', 'name', 'slug']),
                'messages' => $conversation->messages()
                    ->with('sender:id,name')
                    ->oldest('id')
                    ->limit(200)
                    ->get()
                    ->map(fn ($message) => [
                        'id' => $message->id,
                        'body' => $message->body,
                        'mine' => $message->user_id === $user->id,
                        'sender' => $message->sender?->name,
                        'auto' => $message->is_auto_reply,
                        'created_at' => $message->created_at?->toIso8601String(),
                    ]),
            ] : null,
        ]);
    }

    /**
     * Open (or reuse) a conversation with a shop.
     */
    public function start(Request $request, Shop $shop): RedirectResponse
    {
        $conversation = $this->chat->conversationWith($request->user(), $shop->load('sellerProfile.user'));

        return redirect()->route('account.chat.show', $conversation);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse
    {
        $validated = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $this->chat->send($conversation, $request->user(), $validated['body']);

        return back();
    }
}
