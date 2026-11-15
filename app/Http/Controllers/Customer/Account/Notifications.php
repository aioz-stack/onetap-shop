<?php

namespace App\Http\Controllers\Customer\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class Notifications extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Customer/Account/Notifications', [
            'notifications' => $request->user()->notifications()
                ->paginate(15)
                ->through(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'title' => $notification->data['title'] ?? 'Update',
                    'body' => $notification->data['body'] ?? '',
                    'url' => $notification->data['url'] ?? null,
                    'read' => (bool) $notification->read_at,
                    'created_at' => $notification->created_at?->toIso8601String(),
                ]),
        ]);
    }

    /**
     * Mark one notification read and follow its link.
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? '/account/notifications';

        // Only follow links inside this application.
        return redirect(str_starts_with($url, '/') && ! str_starts_with($url, '//') ? $url : '/account/notifications');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
