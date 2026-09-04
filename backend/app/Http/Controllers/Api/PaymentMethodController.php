<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

/**
 * Moyens de paiement proposes.
 *
 * Public : le client doit pouvoir savoir comment il paiera AVANT de composer
 * son panier, pas le decouvrir a la derniere etape.
 */
class PaymentMethodController extends Controller
{
    public function index()
    {
        $methods = collect(config('payments.methods'))
            ->map(fn (array $method) => [
                'code' => $method['code'],
                'label' => $method['label'],
                'color' => $method['color'],
                'ussd' => $method['ussd'],
                'enabled' => (bool) $method['enabled'],
                // Le numero marchand n'est communique que pour les operateurs
                // ouverts : afficher celui d'un service « bientot » ferait
                // envoyer de l'argent sur un compte qu'on ne releve pas.
                'merchant_number' => $method['enabled'] ? $method['merchant_number'] : null,
                'merchant_name' => $method['enabled'] ? $method['merchant_name'] : null,
            ])
            ->values();

        $online = app(\App\Services\FedapayService::class)->isEnabled();

        return response()->json([
            'success' => true,
            'data' => $methods,
            'meta' => [
                'validation_delay' => config('payments.validation_delay'),
                /*
                 * Deux régimes, et le client doit savoir lequel s'applique :
                 *
                 *  - « online »      : Fedapay confirme le paiement, la
                 *                      commande part sans intervention humaine.
                 *  - « declarative » : repli sans API — le client envoie
                 *                      l'argent, déclare sa référence, un
                 *                      administrateur vérifie et valide.
                 */
                'flow' => $online ? 'online' : 'declarative',
                'fedapay' => $online ? [
                    'public_key' => config('payments.fedapay.public_key'),
                    'sandbox' => (bool) config('payments.fedapay.sandbox'),
                ] : null,
            ],
        ]);
    }
}
