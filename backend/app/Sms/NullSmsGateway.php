<?php

namespace App\Sms;

use App\Contracts\SmsGateway;

/**
 * Aucun envoi, aucune trace. Pour les tests et pour couper le canal SMS
 * sans toucher au code.
 */
class NullSmsGateway implements SmsGateway
{
    public function send(string $to, string $message): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'null';
    }
}
