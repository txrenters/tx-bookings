<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    /**
     * The number of notifications the panel loads at once.
     */
    protected int $limit = 15;

    /**
     * List the most recent notifications for the panel.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'notifications' => $user->notifications()
                ->latest()
                ->limit($this->limit)
                ->get()
                ->map(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'readAt' => $notification->read_at?->toIso8601String(),
                    'createdAt' => $notification->created_at->toIso8601String(),
                    ...$notification->data,
                ]),
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /**
     * Mark one notification as read.
     */
    public function update(Request $request, string $notification): RedirectResponse
    {
        $request->user()
            ->unreadNotifications()
            ->whereKey($notification)
            ->update(['read_at' => now()]);

        return back();
    }

    /**
     * Mark every notification as read.
     */
    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * Remove one notification.
     */
    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->whereKey($notification)->delete();

        return back();
    }
}
