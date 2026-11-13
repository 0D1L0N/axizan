<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class KycController extends Controller
{
    // Lister les livreurs en attente de KYC
    public function index()
    {
        $users = User::where('role', 'livreur')
            ->where('status', 'pending_kyc')
            ->get(['id','name','email','phone','kyc_document_url','status','kyc_verified']);

        return response()->json($users);
    }

    // Approuver KYC
    public function approve($id)
    {
        $user = User::find($id);
        if (! $user) return response()->json(['message' => 'not_found'], 404);

        $user->kyc_verified = true;
        $user->status = 'active';
        $user->save();

        // TODO: send notification to user

        return response()->json(['success' => true, 'data' => $user]);
    }

    // Rejeter KYC
    public function reject(Request $request, $id)
    {
        $user = User::find($id);
        if (! $user) return response()->json(['message' => 'not_found'], 404);

        $reason = $request->get('reason');
        $user->kyc_verified = false;
        $user->status = 'kyc_rejected';
        $user->save();

        // Optionally store rejection reason in audit or notes
        return response()->json(['success' => true]);
    }
}
