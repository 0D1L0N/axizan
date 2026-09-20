<?php

namespace App\Exceptions;

/**
 * Le creneau demande ne peut plus recevoir cette commande.
 *
 * Distincte d'une erreur de creation ordinaire parce que la reponse HTTP ne
 * doit pas l'etre : « ce creneau est complet » se corrige en choisissant un
 * autre creneau, et le client a besoin qu'on le lui dise dans ces termes.
 */
class SlotUnavailableException extends \RuntimeException
{
}
