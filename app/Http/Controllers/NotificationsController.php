<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The in-app inbox shared by every panel (the header bell) and the mobile API.
 */
class NotificationsController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    public static function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'type' => $notification->data['type'] ?? null,
            'title' => $notification->data['title'] ?? 'Update',
            'body' => $notification->data['body'] ?? '',
            'url' => $notification->data['url'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }

    public function feed(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => $user->notifications()->limit(10)->get()->map(fn ($notification) => self::present($notification)),
            'unread' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification read and follow its link (only links inside this app).
     */
    public function open(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();
        $url = $notification->data['url'] ?? null;

        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return redirect($url);
        }

        return redirect()->route($request->user()->homeRouteName());
    }

    public function readAll(Request $request): RedirectResponse|JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson() ? response()->json(['unread' => 0]) : back()->with('success', 'All notifications marked as read.');
    }

    // ------------------------------------------------------------ API (/api/v1/notifications)

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate(['unread' => ['nullable', 'boolean']]);
        $page = $request->user()->notifications()
            ->when($validated['unread'] ?? false, fn ($query) => $query->whereNull('read_at'))
            ->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn ($notification) => self::present($notification))->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'unread' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json(['data' => self::present($notification)]);
    }
}
