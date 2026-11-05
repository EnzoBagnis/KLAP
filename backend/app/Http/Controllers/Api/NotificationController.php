<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KlapNotification;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Contrôleur des notifications internes de KLAP.
 * Gère la cloche de notification, les alertes de déverrouillage et le compteur des non lues.
 */
class NotificationController extends Controller
{
    /**
     * Liste les notifications de l'utilisateur connecté avec le nombre de non lues.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $notifications = KlapNotification::where('recipient_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->take(30)
            ->get();

        $unreadCount = KlapNotification::where('recipient_id', $user->id)
            ->unread()
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'notifications' => $notifications,
        ]);
    }

    /**
     * Marque une notification spécifique comme lue.
     */
    public function markAsRead(int $id, Request $request): JsonResponse
    {
        $user = $request->user();

        $notification = KlapNotification::where('recipient_id', $user->id)
            ->findOrFail($id);

        $notification->markAsRead();

        return response()->json([
            'message' => 'Notification marquée comme lue.',
        ]);
    }

    /**
     * Marque toutes les notifications de l'utilisateur comme lues.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        KlapNotification::where('recipient_id', $user->id)
            ->unread()
            ->update(['read_at' => Carbon::now()]);

        return response()->json([
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ]);
    }
}
