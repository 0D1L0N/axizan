<?php

namespace App\Sms;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

/**
 * Passerelle de developpement : ecrit le SMS dans les journaux.
 *
 * Volontairement honnete — elle n'envoie rien et ne pretend pas le contraire.
 * Elle permet de verifier tout le parcours (declenchement, destinataire,
 * contenu, longueur) avant qu'un agregateur ne soit branche.
 */
class LogSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        Log::channel(config('sms.log_channel'))->info('SMS (non envoyé — passerelle de test)', [
            'to' => $to,
            'length' => mb_strlen($message),
            // Un SMS au-dela de 160 caracteres est facture double : on veut le
            // voir en developpement, pas sur la facture.
            'segments' => (int) ceil(mb_strlen($message) / 160),
            'message' => $message,
        ]);

        return true;
    }

    public function name(): string
    {
        return 'log';
    }
}
