<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\KlapNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cloche de notifications (#122), qu'on a reprise de la branche de Lucas (#121) : chacun ne
 * voit et ne lit que ses propres notifications.
 */
class NotificationController extends Controller
{
    /**
     * On liste les dernières notifications de la personne connectée, avec le nombre de non-lues.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $own = KlapNotification::query()->where('recipient_id', $request->user()->id);

        return NotificationResource::collection(
            (clone $own)->latest()->latest('id')->take(KlapNotification::MAX_LISTED)->get()
        )->additional(['meta' => ['unread_count' => (clone $own)->unread()->count()]]);
    }

    /**
     * On ne renvoie que le nombre de non-lues : la cloche le recharge toutes les 30 secondes (#227).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = KlapNotification::query()->where('recipient_id', $request->user()->id)->unread()->count();

        return response()->json(['data' => ['unread_count' => $count]]);
    }

    /**
     * On marque une notification comme lue. C'est idempotent : une notification déjà lue
     * garde sa date de lecture.
     *
     * @param  string  $notification  l'identifiant de la notification, tel qu'il arrive dans l'URL
     */
    public function read(Request $request, string $notification): NotificationResource
    {
        // Pour la notification d'un autre compte, on répond comme si elle n'existait pas.
        $found = ctype_digit($notification)
            ? KlapNotification::query()->where('recipient_id', $request->user()->id)->find($notification)
            : null;
        abort_if($found === null, 404);

        if ($found->read_at === null) {
            $found->read_at = now();
            $found->save();
        }

        return new NotificationResource($found);
    }

    /**
     * On marque toutes ses notifications comme lues.
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = KlapNotification::query()
            ->where('recipient_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['meta' => ['marked' => $count, 'unread_count' => 0]]);
    }
}
