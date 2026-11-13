<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class KycController extends Controller
{
    /**
     * Etat de la verification d'identite du livreur connecte.
     */
    public function show(Request $request)
    {
        $user = $request->user();

        if (!$user->isLivreur()) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'kyc_verified' => $user->kyc_verified,
                'document_submitted' => !empty($user->kyc_document_url),
            ],
        ]);
    }

    /**
     * Depot de la piece d'identite par le livreur.
     *
     * Le document va sur le disque prive : une piece d'identite n'a rien a
     * faire derriere une URL publique. Le compte reste actif pendant la
     * verification, sinon le livreur ne pourrait plus se connecter pour
     * suivre son dossier.
     */
    public function submit(Request $request)
    {
        $user = $request->user();

        if (!$user->isLivreur()) {
            return response()->json(['success' => false, 'error' => 'forbidden'], 403);
        }

        $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        $previous = $user->kyc_document_url;
        $path = $request->file('document')->store('kyc/'.$user->id, 'local');

        $user->forceFill([
            'kyc_document_url' => $path,
            'kyc_verified' => false,
        ])->save();

        if ($previous && $previous !== $path) {
            Storage::disk('local')->delete($previous);
        }

        return response()->json([
            'success' => true,
            'message' => 'Document envoyé. Un administrateur va le vérifier.',
        ], 201);
    }
}
