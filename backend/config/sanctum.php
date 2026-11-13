<?php

use Laravel\Sanctum\Sanctum;

/*
|--------------------------------------------------------------------------
| Configuration Sanctum
|--------------------------------------------------------------------------
|
| Sans ce fichier, 'expiration' vaut null : un jeton emis reste valable
| indefiniment. Sur un telephone perdu, vole ou revendu — courant — l'acces
| au compte resterait ouvert pour toujours.
|
| Les jetons expirent donc au bout de 30 jours. La commande
| `sanctum:prune-expired` (voir routes/console.php) nettoie ensuite la table.
|
*/

return [

    'stateful' => explode(',', (string) env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
    ))),

    'guard' => ['web'],

    // En minutes. 30 jours : assez long pour ne pas reconnecter un client a
    // chaque commande, assez court pour qu'un jeton perdu finisse par mourir.
    'expiration' => (int) env('SANCTUM_TOKEN_EXPIRATION', 60 * 24 * 30),

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];
