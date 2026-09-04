<?php

/*
|--------------------------------------------------------------------------
| Partage de ressources entre origines (CORS)
|--------------------------------------------------------------------------
|
| Sans ce fichier, Laravel applique son defaut : allowed_origins => ['*'].
| N'importe quel site peut alors appeler l'API depuis le navigateur d'un
| client. On restreint aux domaines Axizan, listes par variable
| d'environnement pour que developpement et production divergent sans
| toucher au code.
|
| CORS_ALLOWED_ORIGINS accepte plusieurs domaines separes par des virgules.
|
*/

$origins = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('CORS_ALLOWED_ORIGINS', env('FRONTEND_URL', 'http://localhost:3000')))
)));

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    'allowed_origins' => $origins,

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['Accept', 'Authorization', 'Content-Type', 'X-Requested-With', 'Idempotency-Key'],

    // Les reponses n'exposent aucun en-tete particulier au navigateur.
    'exposed_headers' => [],

    'max_age' => 3600,

    // L'authentification passe par un jeton Bearer, pas par un cookie de
    // session : aucune raison d'autoriser les requetes avec identifiants.
    'supports_credentials' => false,

];
