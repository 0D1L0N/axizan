<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {}

    /**
     * Livreurs ayant depose un document non encore verifie.
     */
    public function index()
    {
        $livreurs = User::where('role', UserRole::LIVREUR->value)
            ->whereNotNull('kyc_document_url')
            ->where('kyc_verified', false)
            ->orderBy('updated_at')
            ->get(['id', 'name', 'email', 'phone', 'updated_at']);

        return response()->json(['success' => true, 'data' => $livreurs]);
    }

    /**
     * Le document n'est servi qu'ici, a un administrateur authentifie.
     */
    public function document($id)
    {
        $livreur = $this->findLivreur($id);

        if (!$livreur || !$livreur->kyc_document_url || !Storage::disk('local')->exists($livreur->kyc_document_url)) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        return Storage::disk('local')->response($livreur->kyc_document_url);
    }

    public function approve($id)
    {
        $livreur = $this->findLivreur($id);

        if (!$livreur || !$livreur->kyc_document_url) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        $livreur->forceFill(['kyc_verified' => true])->save();

        $this->notificationService->notify(
            $livreur,
            NotificationType::SYSTEM_NOTIFICATION,
            'Identité vérifiée',
            'Votre pièce d’identité a été validée.'
        );

        return response()->json(['success' => true, 'data' => $livreur]);
    }

    /**
     * Le motif est obligatoire : le livreur doit savoir quoi corriger.
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate(['reason' => 'required|string|max:500']);

        $livreur = $this->findLivreur($id);

        if (!$livreur || !$livreur->kyc_document_url) {
            return response()->json(['success' => false, 'error' => 'not_found'], 404);
        }

        Storage::disk('local')->delete($livreur->kyc_document_url);
        $livreur->forceFill(['kyc_document_url' => null, 'kyc_verified' => false])->save();

        $this->notificationService->notify(
            $livreur,
            NotificationType::SYSTEM_NOTIFICATION,
            'Document refusé',
            'Votre pièce d’identité a été refusée : '.$validated['reason'].'. Merci d’en envoyer une nouvelle.'
        );

        return response()->json(['success' => true]);
    }

    private function findLivreur($id): ?User
    {
        return User::where('role', UserRole::LIVREUR->value)->find($id);
    }
}
