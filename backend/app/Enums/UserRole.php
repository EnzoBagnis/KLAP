<?php

namespace App\Enums;

/**
 * Les trois rôles du cahier des charges. Chaque rôle a les droits de celui du dessous :
 * on s'appuie là-dessus dans les contrôles d'accès (middleware `role`, scopes des modèles).
 */
enum UserRole: string
{
    /** Mariette : elle peut tout faire. */
    case Admin = 'admin';

    /** Emilie : elle consulte tout, exporte, et peut corriger une fiche. */
    case SuperUser = 'super_user';

    /** Les intervenant·e·s : ils remplissent leurs fiches. */
    case Contributor = 'contributor';

    /**
     * On donne le nom du rôle tel qu'on l'affiche dans l'interface et dans les mails.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administratrice',
            self::SuperUser => 'Super-utilisatrice',
            self::Contributor => 'Intervenant',
        };
    }
}
