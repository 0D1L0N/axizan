<?php

namespace App\Support;

/**
 * Montants en francs CFA.
 *
 * Le XOF n'a pas de subdivision en usage : il n'existe ni piece ni billet
 * inferieur au franc. Un calcul qui produit 127,5 FCFA produit un montant
 * qu'aucune caisse ne peut rendre, et l'ecart s'accumule silencieusement dans
 * la comptabilite.
 *
 * Toute somme d'argent passe donc par ici avant d'etre stockee ou affichee.
 */
final class Money
{
    /**
     * Arrondit au franc entier.
     *
     * Sens de l'arrondi : au plus proche, et a l'exact demi vers le haut
     * (comportement de PHP_ROUND_HALF_UP). Choix assume et unique pour tout
     * le systeme — l'ecart moyen est nul, et une regle unique vaut mieux
     * qu'une regle avantageuse appliquee de travers.
     */
    public static function round(float|int|string $amount): int
    {
        return (int) round((float) $amount, 0, PHP_ROUND_HALF_UP);
    }

    /**
     * Total d'une ligne : prix unitaire x quantite, arrondi.
     *
     * Les quantites peuvent etre fractionnaires (1,5 kg d'ignames), pas les
     * montants.
     */
    public static function lineTotal(float|int|string $unitPrice, float|int|string $quantity): int
    {
        return self::round((float) $unitPrice * (float) $quantity);
    }

    /**
     * Pourcentage d'un montant, arrondi.
     */
    public static function percentOf(float|int|string $amount, float $percent): int
    {
        return self::round((float) $amount * $percent / 100);
    }
}
