<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\KlapNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Cloche de notifications (#122), reprise de la branche de Lucas (#121) : chacun ne voit
 * et ne lit que ses propres notifications.
 */
class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $own = KlapNotification::query()->where('recipient_id', $request->user()->id);

        return NotificationResource::collection(
            (clone $own)->latest()->latest('id')->take(KlapNotification::MAX_LISTED)->get()
        )->additional(['meta' => ['unread_count' => (clone $own)->unread()->count()]]);
    }

    /** Idempotent : une notification déjà lue garde sa date de lecture. */
    public function read(Request $request, string $notification): NotificationResource
    {
        // Celle d'un autre compte répond comme une notification inexistante.
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

    public function readAll(Request $request): JsonResponse
    {
        $count = KlapNotification::query()
            ->where('recipient_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now(), 'updated_at' => now()]);

        return response()->json(['meta' => ['marked' => $count, 'unread_count' => 0]]);
    }
}
