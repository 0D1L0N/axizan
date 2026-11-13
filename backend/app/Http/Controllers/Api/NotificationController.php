<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;

/**
 * Boite de reception in-app.
 *
 * Le NotificationService ecrivait deja dans cette table depuis le debut, mais
 * rien ne permettait de la lire : les clients recevaient des notifications que
 * personne ne pouvait afficher.
 */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'unread' => 'nullable|boolean',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $query = $request->user()->notifications()->latest();

        if ($request->boolean('unread')) {
            $query->where('is_read', false);
        }

        $notifications = $query->paginate($this->perPage($request, 20));

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'unread_count' => $this->unreadCountFor($request),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'total' => $notifications->total(),
            ],
        ]);
    }

    /** Compteur seul : appele souvent (la pastille de l'en-tete). */
    public function unreadCount(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['unread_count' => $this->unreadCountFor($request)],
        ]);
    }

    public function markAsRead(Request $request, Notification $notification)
    {
        // Une notification appartient a une seule personne : 404 plutot que 403,
        // pour ne pas confirmer l'existence de l'identifiant.
        abort_unless($notification->user_id === $request->user()->id, 404);

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'data' => $notification->fresh(),
            'meta' => ['unread_count' => $this->unreadCountFor($request)],
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()->notifications()
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json([
            'success' => true,
            'meta' => ['unread_count' => 0],
        ]);
    }

    private function unreadCountFor(Request $request): int
    {
        return $request->user()->notifications()->where('is_read', false)->count();
    }
}
