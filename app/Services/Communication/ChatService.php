<?php

namespace App\Services\Communication;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer ↔ shop conversations. The shop side is the shop owner's account.
 */
class ChatService
{
    public function conversationWith(User $customer, Shop $shop): Conversation
    {
        $owner = $shop->sellerProfile?->user;

        if (! $owner) {
            throw ValidationException::withMessages(['shop' => 'This shop cannot receive messages yet.']);
        }

        if ($owner->is($customer)) {
            throw ValidationException::withMessages(['shop' => 'You cannot message your own shop.']);
        }

        return DB::transaction(function () use ($customer, $shop, $owner) {
            $existing = Conversation::query()
                ->where('shop_id', $shop->id)
                ->where('type', 'customer_shop')
                ->whereHas('participants', fn ($users) => $users->whereKey($customer->id))
                ->first();

            if ($existing) {
                return $existing;
            }

            $conversation = Conversation::query()->create(['shop_id' => $shop->id, 'type' => 'customer_shop']);
            $conversation->participants()->attach([$customer->id, $owner->id]);

            return $conversation;
        });
    }

    public function send(Conversation $conversation, User $sender, string $body): Message
    {
        $this->assertParticipant($conversation, $sender);

        return DB::transaction(function () use ($conversation, $sender, $body) {
            $isFirstMessage = ! $conversation->messages()->exists();
            $message = $conversation->messages()->create(['user_id' => $sender->id, 'body' => trim($body)]);
            $conversation->forceFill(['last_message_at' => now()])->save();
            $this->markRead($conversation, $sender);

            $this->autoReply($conversation, $message, $isFirstMessage);

            return $message;
        });
    }

    public function markRead(Conversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['last_read_at' => now()]);
    }

    public function assertParticipant(Conversation $conversation, User $user): void
    {
        abort_unless($conversation->participants()->whereKey($user->id)->exists(), 404);
    }

    public function unreadCount(User $user): int
    {
        return Message::query()
            ->where('user_id', '!=', $user->id)
            ->whereHas('conversation.participants', fn ($users) => $users->whereKey($user->id))
            ->whereRaw('messages.created_at > coalesce((select last_read_at from conversation_participants where conversation_participants.conversation_id = messages.conversation_id and conversation_participants.user_id = ?), ?)', [$user->id, '1970-01-01 00:00:00'])
            ->count();
    }

    /**
     * Automated shop replies to customer messages:
     * the greeting on a new conversation, the away message while on vacation,
     * and the first keyword reply whose keyword appears in the message.
     */
    private function autoReply(Conversation $conversation, Message $message, bool $isFirstMessage): void
    {
        $shop = $conversation->shop;
        $ownerId = $shop?->sellerProfile?->user_id;

        if (! $shop || ! $ownerId || $message->user_id === $ownerId) {
            return;
        }

        $replies = $shop->autoReplies()->where('is_active', true)->get();
        $text = mb_strtolower((string) $message->body);
        $send = fn (string $body) => $conversation->messages()->create(['user_id' => $ownerId, 'body' => $body, 'is_auto_reply' => true]);

        if ($shop->vacation_mode && ($away = $replies->firstWhere('type', 'offline'))) {
            $send($away->message);
        } elseif ($isFirstMessage && ($greeting = $replies->firstWhere('type', 'greeting'))) {
            $send($greeting->message);
        }

        $keyword = $replies->where('type', 'keyword')->first(fn ($reply) => filled($reply->keyword) && str_contains($text, mb_strtolower($reply->keyword)));

        if ($keyword) {
            $send($keyword->message);
        }
    }

    /**
     * Conversations for a shop inbox, newest first, with the latest message.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Conversation>
     */
    public function shopInbox(\App\Models\Shop $shop)
    {
        return Conversation::query()
            ->where('shop_id', $shop->id)
            ->with(['participants:id,name', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
    }
}
