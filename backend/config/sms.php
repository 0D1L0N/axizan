<?php

/*
|--------------------------------------------------------------------------
| SMS
|--------------------------------------------------------------------------
|
| Aucun agregateur n'est encore contractualise. Le pilote par defaut est
| « log » : il ecrit le message dans les journaux au lieu de l'envoyer, ce qui
| permet de verifier tout le parcours — declenchement, destinataire, contenu,
| longueur — sans rien facturer ni rien simuler de faux.
|
| Pour brancher un vrai agregateur : ecrire une classe implementant
| App\Contracts\SmsGateway, l'ajouter a `gateways`, et changer SMS_DRIVER.
|
| ATTENTION AU COUT : un SMS est facture au segment de 160 caracteres. Le
| routage par canal (config/notifications.php) reserve donc le SMS aux
| moments qui le meritent.
|
*/

return [

    'driver' => env('SMS_DRIVER', 'log'),

    'gateways' => [
        'log' => App\Sms\LogSmsGateway::class,
        'null' => App\Sms\NullSmsGateway::class,
    ],

    'log_channel' => env('SMS_LOG_CHANNEL', 'stack'),

    // Indicatif applique aux numeros locaux saisis sans prefixe.
    'default_country_code' => env('SMS_COUNTRY_CODE', '229'),

    // Nom affiche comme expediteur, quand l'agregateur le permet.
    'from' => env('SMS_FROM', 'AXIZAN'),

    // Au-dela, on n'envoie pas : un message tronque par l'operateur est pire
    // qu'un message absent, et trois segments coutent trois fois le prix.
    'max_length' => env('SMS_MAX_LENGTH', 320),

];
