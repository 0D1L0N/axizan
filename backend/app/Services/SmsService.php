<?php

namespace App\Services;

use App\Contracts\SmsGateway;
use Illuminate\Support\Facades\Log;

class SmsService
{
    private SmsGateway $gateway;

    public function __construct()
    {
        $driver = config('sms.driver');
        $class = config("sms.gateways.{$driver}");

        if (! $class || ! class_exists($class)) {
            // Un pilote mal configure ne doit jamais faire echouer une
            // commande : on retombe sur « aucun envoi » et on le signale.
            Log::warning("Pilote SMS inconnu : {$driver}. Aucun SMS ne sera envoyé.");
            $class = \App\Sms\NullSmsGateway::class;
        }

        $this->gateway = app($class);
    }

    /**
     * Normalise un numero beninois vers E.164.
     *
     * Les clients saisissent « 97 00 00 00 », « 097000000 », « +229 97... ».
     * Sans normalisation, l'agregateur rejette la moitie des envois.
     */
    public function normalize(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);
        $country = (string) config('sms.default_country_code');

        if ($digits === '') {
            return null;
        }

        // Deja prefixe par l'indicatif pays.
        if (str_starts_with($digits, $country)) {
            return '+'.$digits;
        }

        // Un zero de tete est une convention nationale, l'international ne le
        // garde pas.
        $digits = ltrim($digits, '0');

        return '+'.$country.$digits;
    }

    /**
     * @return bool  true si le message est parti (ou a ete accepte)
     */
    public function send(?string $phone, string $message): bool
    {
        $to = $this->normalize($phone);

        if (! $to) {
            return false;
        }

        $max = (int) config('sms.max_length');

        if (mb_strlen($message) > $max) {
            // Tronquer produirait un message coupe au milieu d'un numero ou
            // d'un montant. Mieux vaut ne pas envoyer et le signaler.
            Log::warning('SMS non envoyé : message trop long', [
                'to' => $to,
                'length' => mb_strlen($message),
                'max' => $max,
            ]);

            return false;
        }

        return $this->gateway->send($to, $message);
    }

    public function driverName(): string
    {
        return $this->gateway->name();
    }
}
