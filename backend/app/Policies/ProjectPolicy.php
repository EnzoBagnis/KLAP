<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Politique de sécurité (RBAC) pour les projets de danse KLAP.
 * Conforme aux spécifications de Mariette Travard :
 * - Consultation ouverte à tous les membres authentifiés.
 * - Création et modification réservées aux rôles administratifs (admin, super-user).
 * - Suppression strictement réservée à l'Administratrice (avec confirmation obligatoire du nom).
 */
class ProjectPolicy
{
    /**
     * Détermine si l'utilisateur peut lister les projets.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Détermine si l'utilisateur peut consulter un projet donné.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->is_active;
    }

    /**
     * Détermine si l'utilisateur peut créer un projet.
     */
    public function create(User $user): Response
    {
        if ($user->isAdmin() || $user->isSuperUser()) {
            return Response::allow();
        }

        return Response::deny("Seule l'administration peut créer un nouveau projet.");
    }

    /**
     * Détermine si l'utilisateur peut modifier un projet.
     */
    public function update(User $user, Project $project): Response
    {
        if ($user->isAdmin() || $user->isSuperUser()) {
            return Response::allow();
        }

        return Response::deny("Vous n'avez pas les droits pour modifier ce projet.");
    }

    /**
     * Détermine si l'utilisateur peut supprimer un projet.
     * Règle absolue du client : SEULE l'administratrice (Mariette Travard) peut supprimer un projet.
     */
    public function delete(User $user, Project $project): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return Response::deny("Seule l'administratrice est habilitée à supprimer un projet.");
    }
}
