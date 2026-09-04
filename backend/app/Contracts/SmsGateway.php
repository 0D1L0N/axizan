<?php

namespace App\Contracts;

/**
 * Passerelle d'envoi de SMS.
 *
 * Le jour ou un contrat est signe avec un agregateur beninois, il suffit
 * d'ecrire une classe de plus et de changer SMS_DRIVER. Rien d'autre ne bouge.
 */
interface SmsGateway
{
    /**
     * @param  string  $to  Numero au format E.164 (+229...)
     * @return bool  true si l'operateur a accepte le message
     */
    public function send(string $to, string $message): bool;

    /** Nom lisible, pour les journaux et le diagnostic. */
    public function name(): string;
}
