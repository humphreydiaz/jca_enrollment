<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class SidebarNotificationController extends Controller
{
    private const NOTIFICATION_ROLES = [
        'enrollment_review' => ['admin', 'registrar'],
        'profile_updates' => ['admin', 'registrar'],
        'family_links' => ['admin', 'registrar'],
        'section_assignment' => ['admin', 'registrar'],
        'restricted_support' => ['admin', 'registrar'],
        'grade_approval' => ['admin', 'registrar'],
        'billing_queue' => ['admin', 'registrar', 'accounting'],
        'teacher_grades' => ['teacher'],
    ];

    public function markSeen(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_key' => [
                'required',
                'string',
                Rule::in(array_keys(self::NOTIFICATION_ROLES)),
            ],
        ]);

        $key = $validated['notification_key'];
        $user = $request->user();

        abort_unless(
            $user && $user->hasAnyRole(self::NOTIFICATION_ROLES[$key]),
            403,
            'You are not authorized to access this notification.'
        );

        $now = now();

        DB::table('sidebar_notification_reads')->updateOrInsert(
            [
                'user_id' => $user->id,
                'notification_key' => $key,
            ],
            [
                'last_seen_at' => $now,
                'updated_at' => $now,
            ]
        );

        return response()->json([
            'success' => true,
            'notification_key' => $key,
        ]);
    }
}
