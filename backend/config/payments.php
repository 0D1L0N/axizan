<?php

/*
|--------------------------------------------------------------------------
| Moyens de paiement
|--------------------------------------------------------------------------
|
| Etape intermediaire, assumee : aucune API operateur n'est branchee. Le
| client choisit son operateur, envoie l'argent au numero marchand, puis
| declare la reference de sa transaction. Un administrateur verifie sur le
| compte et valide.
|
| C'est exactement la facon dont un commercant beninois opere aujourd'hui —
| et c'est honnete : rien ici ne pretend traiter un paiement en ligne.
|
| `enabled` a false = affiche comme « bientot », non selectionnable. Quand les
| contrats MTN/Moov/Celtiis seront signes, ce fichier laisse la place a une
| integration reelle sans toucher au reste.
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | FedaPay
    |--------------------------------------------------------------------------
    |
    | Agregateur beninois : MTN MoMo, Moov Money, cartes. Quand il est actif,
    | le paiement est confirme par l'OPERATEUR, pas par un administrateur.
    |
    | Regle absolue : le retour du widget cote navigateur n'est jamais une
    | preuve. Il donne un identifiant de transaction, que le serveur va
    | verifier aupres de FedaPay — sinon n'importe qui pourrait declarer avoir
    | paye en appelant notre API directement.
    |
    */
    'fedapay' => [
        'enabled' => env('FEDAPAY_ENABLED', false),
        // Seule la cle publique sort de l'API : elle est faite pour le
        // navigateur. La cle secrete ne quitte JAMAIS le serveur.
        'public_key' => env('FEDAPAY_PUBLIC_KEY'),
        'secret_key' => env('FEDAPAY_SECRET_KEY'),
        // Le secret du WEBHOOK est distinct de la cle API : c'est la cause
        // numero un des erreurs de signature.
        'webhook_secret' => env('FEDAPAY_WEBHOOK_SECRET'),
        // Le bac a sable utilise des numeros de test : aucun argent ne bouge.
        'sandbox' => env('FEDAPAY_SANDBOX', true),
    ],

    // Delai indicatif affiche au client entre son envoi et la validation.
    // Ne concerne que le repli manuel : avec FedaPay, c'est immediat.
    'validation_delay' => env('PAYMENT_VALIDATION_DELAY', 'moins de 30 minutes en journée'),

    'methods' => [
        [
            'code' => 'mtn',
            'label' => 'MTN MoMo',
            'color' => '#FFCC00',
            'merchant_number' => env('PAY_MTN_NUMBER', '+229 00 00 00 00'),
            'merchant_name' => env('PAY_MERCHANT_NAME', 'Axizán'),
            'ussd' => '*880#',
            'enabled' => env('PAY_MTN_ENABLED', true),
        ],
        [
            'code' => 'moov',
            'label' => 'Moov Money',
            'color' => '#0066B3',
            'merchant_number' => env('PAY_MOOV_NUMBER', '+229 00 00 00 00'),
            'merchant_name' => env('PAY_MERCHANT_NAME', 'Axizán'),
            'ussd' => '*855#',
            'enabled' => env('PAY_MOOV_ENABLED', true),
        ],
        [
            'code' => 'celtiis',
            'label' => 'Celtiis Cash',
            'color' => '#E30613',
            'merchant_number' => env('PAY_CELTIIS_NUMBER', '+229 00 00 00 00'),
            'merchant_name' => env('PAY_MERCHANT_NAME', 'Axizán'),
            'ussd' => '*800#',
            'enabled' => env('PAY_CELTIIS_ENABLED', false),
        ],
    ],

];
