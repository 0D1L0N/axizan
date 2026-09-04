<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cle d'idempotence : « cette requete, je l'ai deja traitee ».
 *
 * Le cas reel n'est pas theorique. Sur un reseau mobile instable, la commande
 * part, le serveur la cree, la reponse se perd, le client retouche le bouton.
 * Sans cette table, il se retrouve avec deux commandes, deux paiements a
 * valider et deux paniers a acheter au marche.
 */
class IdempotencyKey extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'endpoint',
        'payload_hash',
        'resource_id',
    ];

    /**
     * Empreinte stable d'une charge utile.
     *
     * Les cles sont triees en profondeur : deux requetes identiques dont les
     * champs arrivent dans un ordre different doivent produire la meme
     * empreinte, sinon la protection ne sert a rien.
     */
    public static function hashPayload(array $payload): string
    {
        $normalize = function (array $data) use (&$normalize) {
            ksort($data);

            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = $normalize($value);
                }
            }

            return $data;
        };

        return hash('sha256', json_encode($normalize($payload)));
    }
}
