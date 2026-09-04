<?php

use App\Enums\NotificationType;

/*
|--------------------------------------------------------------------------
| Canaux par type de notification
|--------------------------------------------------------------------------
|
| Tout n'est pas bon a envoyer partout. Le SMS coute de l'argent a chaque
| message et interrompt la personne ; l'e-mail est lu plus tard ; le in-app
| est gratuit et consultable a tout moment.
|
| Regle retenue : le SMS est reserve aux trois moments ou l'inaction du client
| a une consequence — il doit payer, il peut recevoir, on arrive. Le reste du
| suivi vit dans l'application.
|
| 'in_app' est implicite et toujours actif : c'est la trace consultable.
|
*/

return [

    'channels' => [

        // Le client doit payer pour que ses courses partent : sans SMS, une
        // commande passee le soir peut dormir jusqu'au lendemain.
        NotificationType::ORDER_CREATED->value => ['mail', 'sms'],

        // Le paiement est valide : on achete. Le client attend ce signal.
        NotificationType::PAYMENT_CONFIRMED->value => ['mail', 'sms'],

        // Le livreur est en route : le client doit etre la. C'est le SMS le
        // plus utile de tout le parcours.
        NotificationType::ORDER_OUT_FOR_DELIVERY->value => ['sms'],

        // Recu de fin de course, avec la preuve de remise.
        NotificationType::ORDER_DELIVERED->value => ['mail'],

        // Une annulation touche a l'argent : elle merite une trace ecrite.
        NotificationType::ORDER_CANCELLED->value => ['mail', 'sms'],

        // Etapes de suivi : in-app seulement. Envoyer un SMS a chaque
        // changement de statut ferait desinstaller l'application.
        NotificationType::ORDER_ACCEPTED->value => [],
        NotificationType::ORDER_PREPARING->value => [],
        NotificationType::ORDER_READY->value => [],
        NotificationType::DELIVERY_ASSIGNED->value => [],

        // Panier depose au depot : envoye a TOUS les livreurs actifs, donc
        // in-app uniquement. Un SMS par panier et par livreur couterait le
        // prix d'une course, et le premier arrive prend de toute facon.
        NotificationType::BASKET_AT_DEPOT->value => [],
    ],

    // Types que le client ne peut pas desactiver : ils concernent son argent
    // ou une livraison imminente. Le reste respecte ses preferences.
    'transactional' => [
        NotificationType::ORDER_CREATED->value,
        NotificationType::PAYMENT_CONFIRMED->value,
        NotificationType::ORDER_CANCELLED->value,
    ],

];
