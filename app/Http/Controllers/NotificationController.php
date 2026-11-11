<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $notifications = $user->notifications()->latest()->paginate(20);
        $unreadCount = $user->unreadNotifications()->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(Request $request, string $id)
    {
        $notification = auth()->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return back();
    }

    public function markAllRead(Request $request)
    {
        $user = auth()->user();
        $user->unreadNotifications->markAsRead();

        return back()->with('status', 'All notifications marked as read.');
    }
}
