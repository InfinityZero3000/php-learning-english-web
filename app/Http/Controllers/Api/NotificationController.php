<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate($request->per_page ?? 20);

        $unread = Notification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
            'unread_count' => $unread,
        ]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $request->validate([
            'notification_id' => 'nullable|exists:notifications,id',
            'all' => 'nullable|boolean',
        ]);

        $query = Notification::where('user_id', $request->user()->id);

        if ($request->has('notification_id')) {
            $query->where('id', $request->notification_id);
        }

        $query->where('is_read', false)->update(['is_read' => true, 'read' => true]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function destroy(Notification $notification): JsonResponse
    {
        if ($notification->user_id !== request()->user()->id) {
            abort(403);
        }
        $notification->delete();
        return response()->json(['message' => 'Deleted']);
    }

    public function settings(Request $request): JsonResponse
    {
        $settings = NotificationSetting::firstOrCreate(
            ['user_id' => $request->user()->id],
            [
                'review_reminders_enabled' => true,
                'preferred_reminder_time' => '09:00',
                'evening_reminder_enabled' => true,
                'evening_reminder_time' => '20:00',
                'comeback_reminder_enabled' => true,
                'comeback_reminder_interval_days' => 3,
                'evening_remaining_threshold' => 5,
            ]
        );

        return response()->json(['data' => $settings]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $request->validate([
            'review_reminders_enabled' => 'boolean',
            'preferred_reminder_time' => 'date_format:H:i',
            'evening_reminder_enabled' => 'boolean',
            'evening_reminder_time' => 'date_format:H:i',
            'comeback_reminder_enabled' => 'boolean',
            'comeback_reminder_interval_days' => 'integer|min:1|max:30',
            'evening_remaining_threshold' => 'integer|min:1|max:100',
        ]);

        $settings = NotificationSetting::updateOrCreate(
            ['user_id' => $request->user()->id],
            $request->only([
                'review_reminders_enabled', 'preferred_reminder_time',
                'evening_reminder_enabled', 'evening_reminder_time',
                'comeback_reminder_enabled', 'comeback_reminder_interval_days',
                'evening_remaining_threshold',
            ])
        );

        return response()->json(['data' => $settings]);
    }
}