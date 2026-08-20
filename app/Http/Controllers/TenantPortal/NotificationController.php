<?php

namespace App\Http\Controllers\TenantPortal;

use App\Support\DateTimeFormatter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends TenantPortalController
{
    public function index(Request $request): Response
    {
        $tenant = $this->tenant($request);

        return Inertia::render('tenant-portal/notifications/index', [
            'notifications' => $tenant->notifications()
                ->latest()
                ->paginate(20)
                ->through(fn (DatabaseNotification $notification) => [
                    'id' => $notification->id,
                    'type' => $notification->data['type'] ?? $notification->type,
                    'title' => $notification->data['title'] ?? '',
                    'message' => $notification->data['message'] ?? '',
                    'url' => $notification->data['url'] ?? null,
                    'created_at' => DateTimeFormatter::iso($notification->created_at),
                    'read_at' => DateTimeFormatter::nullableIso($notification->read_at),
                ]),
            'unreadCount' => $tenant->unreadNotifications()->count(),
        ]);
    }

    public function markAsRead(Request $request, string $notification): RedirectResponse
    {
        $this->tenant($request)->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $this->tenant($request)->unreadNotifications()->update(['read_at' => now()]);

        return back();
    }
}
