<?php

namespace App\Http\Controllers\Livreur;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;

class OnboardingController extends Controller
{
    // Submit KYC documents (multipart)
    public function submitKyc(Request $request)
    {
        $user = $request->user();
        if (! $user) return response()->json(['message' => 'unauthenticated'], 401);

        $validated = $request->validate([
            'document' => 'required|file|mimes:jpg,jpeg,png,pdf',
            'vehicle_info' => 'nullable|string|max:500',
        ]);

        $path = $request->file('document')->store('kyc', 'public');
        $user->kyc_document_url = '/storage/'.$path;
        $user->kyc_verified = false;
        $user->status = 'pending_kyc';
        $user->save();

        // Notify admin via audit log or notification (left for implementation)

        return response()->json(['success' => true, 'message' => 'kyc_submitted']);
    }
}
