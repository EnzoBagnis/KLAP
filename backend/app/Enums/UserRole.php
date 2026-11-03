<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';               // Mariette : tout
    case SuperUser = 'super_user';      // Emilie : consultation et exports
    case Contributor = 'contributor';   // intervenants : leurs fiches

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administratrice',
            self::SuperUser => 'Super-utilisatrice',
            self::Contributor => 'Intervenant',
        };
    }
}
