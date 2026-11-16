<?php

namespace App\Services\Communication;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Shop;
use App\Models\User;
use App\Services\Administration\SettingsService;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Messages from the other side that this user hasn't seen yet. Automated shop replies are sent
     * under the seller's account, so they never hide an unread customer message from the seller.
     */
    public function unreadIn(Conversation $conversation, User $user): int
    {
        $readAt = $conversation->participants()->whereKey($user->id)->first()?->pivot?->last_read_at;

        return $conversation->messages()
            ->where('user_id', '!=', $user->id)
            ->when($readAt, fn ($query) => $query->where('created_at', '>', $readAt))
            ->count();
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
     * the away message while on vacation, otherwise the off-work message outside working hours
     * (at most once per 12 hours), otherwise the greeting on a new conversation; plus the first
     * keyword reply whose keyword appears in the message.
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
        $offWork = $replies->firstWhere('type', 'off_work');
        $closedNow = $offWork && ! $offWork->isWithinWorkingHours(now()->setTimezone($this->shopTimezone()));

        if ($shop->vacation_mode && ($away = $replies->firstWhere('type', 'offline'))) {
            $send($away->message);
        } elseif ($closedNow) {
            // Outside working hours: tell the customer once per 12 hours, not on every message.
            $recentlySent = $conversation->messages()
                ->where('is_auto_reply', true)
                ->where('body', $offWork->message)
                ->where('created_at', '>=', now()->subHours(12))
                ->exists();

            if (! $recentlySent) {
                $send($offWork->message);
            }
        } elseif ($isFirstMessage && ($greeting = $replies->firstWhere('type', 'greeting'))) {
            $send($greeting->message);
        }

        $keyword = $replies->where('type', 'keyword')->first(fn ($reply) => filled($reply->keyword) && str_contains($text, mb_strtolower($reply->keyword)));

        if ($keyword) {
            $send($keyword->message);
        }
    }

    /**
     * Shops' working hours are local time: the marketplace timezone setting (Asia/Manila by default).
     */
    private function shopTimezone(): string
    {
        $timezone = (string) app(SettingsService::class)->get('general', 'timezone', 'Asia/Manila');

        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Asia/Manila';
    }

    /**
     * Conversations for a shop inbox, newest first, with the latest message.
     *
     * @return Collection<int, Conversation>
     */
    public function shopInbox(Shop $shop)
    {
        return Conversation::query()
            ->where('shop_id', $shop->id)
            ->with(['participants:id,name', 'messages' => fn ($query) => $query->latest('id')->limit(1)])
            ->orderByDesc('last_message_at')
            ->get();
    }
}
