<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Support\Money;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    /**
     * Apercu d'un code promo au checkout.
     *
     * Le montant renvoye n'est qu'indicatif : la reduction reelle est
     * recalculee a la creation de la commande, sur le sous-total serveur.
     */
    public function check(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
            'subtotal' => 'required|numeric|min:0',
        ]);

        $promo = Promotion::byCode(trim($validated['code']))->first();

        if (!$promo) {
            return response()->json(['success' => false, 'message' => 'Code promo inconnu.'], 422);
        }

        $subtotal = (float) $validated['subtotal'];

        if ($reason = $promo->rejectionReasonFor($request->user(), $subtotal)) {
            return response()->json(['success' => false, 'message' => $reason], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'code' => $promo->code,
                'description' => $promo->description,
                'discount' => Money::round($promo->calculateDiscount($subtotal)),
            ],
        ]);
    }
}
