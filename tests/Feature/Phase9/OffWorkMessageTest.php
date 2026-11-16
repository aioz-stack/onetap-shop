<?php

use App\Models\Conversation;
use App\Models\Shop;
use App\Models\ShopAutoReply;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SystemSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    $this->seed([RolesAndPermissionsSeeder::class, SystemSettingsSeeder::class]);
    $this->shop = Shop::factory()->create();
    $this->seller = $this->shop->sellerProfile->user;
    $this->customer = User::factory()->customer()->create();
});

/** Set the clock to a Philippine local time (the marketplace timezone). */
function atManila(string $localTime): void
{
    test()->travelTo(Carbon::parse($localTime, 'Asia/Manila')->utc());
}

function offWork(Shop $shop, array $overrides = []): ShopAutoReply
{
    return $shop->autoReplies()->create(array_merge([
        'type' => 'off_work',
        'message' => 'We are closed now. Back at 8 AM!',
        'schedule' => ['days' => [1, 2, 3, 4, 5, 6], 'start' => '08:00', 'end' => '17:00'],
        'is_active' => true,
    ], $overrides));
}

/** Customer sends a message; returns the shop's automated replies in that chat. */
function customerSays($test, string $body): array
{
    $conversation = Conversation::query()->where('shop_id', $test->shop->id)->first();

    if (! $conversation) {
        $test->actingAs($test->customer)->post("/account/chat/shops/{$test->shop->id}");
        $conversation = Conversation::query()->where('shop_id', $test->shop->id)->sole();
    }

    $test->actingAs($test->customer)->post("/account/chat/{$conversation->id}", ['body' => $body])->assertSessionHasNoErrors();

    return $conversation->messages()->where('is_auto_reply', true)->orderBy('id')->pluck('body')->all();
}

test('sellers set an off-work message with working hours below the greeting', function () {
    $this->actingAs($this->seller)->post('/seller/auto-replies', [
        'type' => 'off_work', 'message' => 'Closed now, we reply at 8 AM.', 'days' => [5, 1, 2, 3, 4], 'start' => '08:00', 'end' => '17:30', 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $reply = ShopAutoReply::query()->where('type', 'off_work')->sole();
    expect($reply->schedule)->toBe(['days' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '17:30']);

    $this->actingAs($this->seller)->get('/seller/auto-replies')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('Seller/CustomerService/AutoReplies')
        ->where('replies.0.type', 'off_work')
        ->where('replies.0.schedule.end', '17:30')
        ->where('timezone', 'Asia/Manila'));

    // Only one off-work message per shop; edits keep the type and update the hours.
    $this->actingAs($this->seller)->post('/seller/auto-replies', ['type' => 'off_work', 'message' => 'x', 'days' => [1], 'start' => '09:00', 'end' => '10:00'])->assertSessionHasErrors('type');
    $this->actingAs($this->seller)->put("/seller/auto-replies/{$reply->id}", ['message' => 'Closed!', 'days' => [6, 7], 'start' => '10:00', 'end' => '14:00', 'is_active' => false])->assertSessionHasNoErrors();
    expect($reply->fresh()->schedule['days'])->toBe([6, 7])->and($reply->fresh()->is_active)->toBeFalse();
});

test('working hours are validated', function () {
    $post = fn (array $data) => $this->actingAs($this->seller)->post('/seller/auto-replies', ['type' => 'off_work', 'message' => 'Closed'] + $data);

    $post(['days' => [], 'start' => '08:00', 'end' => '17:00'])->assertSessionHasErrors('days');
    $post(['days' => [1, 9], 'start' => '08:00', 'end' => '17:00'])->assertSessionHasErrors('days.1');
    $post(['days' => [1], 'start' => '8am', 'end' => '17:00'])->assertSessionHasErrors('start');
    $post(['days' => [1], 'start' => '08:00', 'end' => '08:00'])->assertSessionHasErrors('end');

    // Other reply types don't need hours.
    $this->actingAs($this->seller)->post('/seller/auto-replies', ['type' => 'keyword', 'keyword' => 'price', 'message' => 'See the listing'])->assertSessionHasNoErrors();
    expect(ShopAutoReply::query()->where('type', 'keyword')->sole()->schedule)->toBeNull();
});

test('outside working hours customers get the off-work message instead of the greeting, once', function () {
    $this->shop->autoReplies()->create(['type' => 'greeting', 'message' => 'Hi! How can we help?', 'is_active' => true]);
    offWork($this->shop);

    atManila('2026-11-16 21:30'); // Monday night, after closing.
    expect(customerSays($this, 'Is the drill available?'))->toBe(['We are closed now. Back at 8 AM!']);

    // A second message the same night doesn't repeat it.
    atManila('2026-11-16 22:00');
    expect(customerSays($this, 'Hello?'))->toBe(['We are closed now. Back at 8 AM!']);

    // Next night, more than 12 hours later, it is sent again.
    atManila('2026-11-17 20:00');
    expect(customerSays($this, 'Still there?'))->toHaveCount(2);
});

test('during working hours the greeting is sent as before', function () {
    $this->shop->autoReplies()->create(['type' => 'greeting', 'message' => 'Hi! How can we help?', 'is_active' => true]);
    offWork($this->shop);

    atManila('2026-11-16 10:15'); // Monday morning, open.
    expect(customerSays($this, 'Hello'))->toBe(['Hi! How can we help?']);
});

test('closed days, overnight hours, a switched-off reply and vacation mode are respected', function () {
    $reply = offWork($this->shop);

    atManila('2026-11-22 11:00'); // Sunday is not a working day.
    expect(customerSays($this, 'Open today?'))->toBe(['We are closed now. Back at 8 AM!']);

    // A night shop: 20:00–02:00, Mon–Sat. 01:00 on Tuesday belongs to Monday's shift, so it's open.
    Conversation::query()->delete();
    $reply->update(['schedule' => ['days' => [1, 2, 3, 4, 5, 6], 'start' => '20:00', 'end' => '02:00']]);
    atManila('2026-11-17 01:00');
    expect(customerSays($this, 'Late order?'))->toBe([]);
    atManila('2026-11-17 03:00');
    expect(customerSays($this, 'Anyone?'))->toBe(['We are closed now. Back at 8 AM!']);

    // Switched off: nothing is sent outside hours.
    Conversation::query()->delete();
    $reply->update(['is_active' => false]);
    atManila('2026-11-17 05:00');
    expect(customerSays($this, 'Hi'))->toBe([]);

    // Vacation mode's away message wins over the off-work message.
    Conversation::query()->delete();
    $reply->update(['is_active' => true]);
    $this->shop->update(['vacation_mode' => true]);
    $this->shop->autoReplies()->create(['type' => 'offline', 'message' => 'On vacation until Monday.', 'is_active' => true]);
    expect(customerSays($this, 'Hi'))->toBe(['On vacation until Monday.']);
});

test('the on/off switches flip greeting and off-work replies without touching anything else', function () {
    $greeting = $this->shop->autoReplies()->create(['type' => 'greeting', 'message' => 'Hi!', 'is_active' => true]);
    $reply = offWork($this->shop);

    $this->actingAs($this->seller)->patch("/seller/auto-replies/{$greeting->id}/active", ['is_active' => false])->assertSessionHas('success', 'Greeting turned off.');
    $this->actingAs($this->seller)->patch("/seller/auto-replies/{$reply->id}/active", ['is_active' => false])->assertSessionHas('success', 'Off-work message turned off.');
    expect($greeting->fresh()->is_active)->toBeFalse()->and($greeting->fresh()->message)->toBe('Hi!')
        ->and($reply->fresh()->is_active)->toBeFalse()->and($reply->fresh()->schedule['start'])->toBe('08:00');

    $this->actingAs($this->seller)->patch("/seller/auto-replies/{$reply->id}/active", ['is_active' => true])->assertSessionHas('success', 'Off-work message turned on.');
    expect($reply->fresh()->is_active)->toBeTrue();

    // Saving new hours (as the form does, without is_active) keeps the switch where it is.
    $this->actingAs($this->seller)->patch("/seller/auto-replies/{$reply->id}/active", ['is_active' => false]);
    $this->actingAs($this->seller)->put("/seller/auto-replies/{$reply->id}", ['message' => 'Closed!', 'days' => [1, 2], 'start' => '09:00', 'end' => '18:00'])->assertSessionHasNoErrors();
    expect($reply->fresh()->is_active)->toBeFalse()->and($reply->fresh()->schedule['start'])->toBe('09:00');

    // Validation, and other shops' replies are out of reach.
    $this->actingAs($this->seller)->patch("/seller/auto-replies/{$reply->id}/active", [])->assertSessionHasErrors('is_active');
    $other = Shop::factory()->create();
    $foreign = $other->autoReplies()->create(['type' => 'greeting', 'message' => 'Theirs', 'is_active' => true]);
    $this->actingAs($this->seller)->patch("/seller/auto-replies/{$foreign->id}/active", ['is_active' => false])->assertNotFound();
    expect($foreign->fresh()->is_active)->toBeTrue();
    $this->actingAs($this->customer)->patch("/seller/auto-replies/{$reply->id}/active", ['is_active' => true])->assertForbidden();
});

test('the working-hours check handles the edges', function () {
    $reply = new ShopAutoReply(['schedule' => ['days' => [1, 2, 3, 4, 5], 'start' => '08:00', 'end' => '17:00']]);
    $at = fn (string $time) => Carbon::parse($time, 'Asia/Manila');

    expect($reply->isWithinWorkingHours($at('2026-11-16 08:00')))->toBeTrue()   // opening minute
        ->and($reply->isWithinWorkingHours($at('2026-11-16 16:59')))->toBeTrue()
        ->and($reply->isWithinWorkingHours($at('2026-11-16 17:00')))->toBeFalse() // closing minute
        ->and($reply->isWithinWorkingHours($at('2026-11-16 07:59')))->toBeFalse()
        ->and($reply->isWithinWorkingHours($at('2026-11-21 10:00')))->toBeFalse(); // Saturday

    // No hours set: treated as always open, so nothing is sent.
    expect((new ShopAutoReply(['schedule' => null]))->isWithinWorkingHours($at('2026-11-16 03:00')))->toBeTrue();
});
