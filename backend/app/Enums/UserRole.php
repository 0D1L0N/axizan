<?php

namespace App\Enums;

enum UserRole: string
{
    case CLIENT = 'client';
    case PREPARATEUR = 'preparateur';
    case LIVREUR = 'livreur';
    case ADMIN = 'admin';

    public static function getLabel(UserRole $role): string
    {
        return match ($role) {
            self::CLIENT => 'Client',
            self::PREPARATEUR => 'Préparateur',
            self::LIVREUR => 'Livreur',
            self::ADMIN => 'Administrateur',
        };
    }

    public static function getAvatar(UserRole $role): string
    {
        return match ($role) {
            self::CLIENT => '🛒',
            self::PREPARATEUR => '👨‍🍳',
            self::LIVREUR => '🚚',
            self::ADMIN => '👨‍💼',
        };
    }
}
