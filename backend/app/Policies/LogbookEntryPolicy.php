<?php

namespace App\Policies;

use App\Models\LogbookEntry;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Politique de sécurité (RBAC) pour les fiches de carnet de bord KLAP.
 * Conforme aux spécifications strictes de Mariette Travard :
 * 1. Protection contre les failles IDOR (un intervenant ne peut pas modifier la fiche d'un autre).
 * 2. Règle inviolable du verrouillage automatique J+10 (la fiche passe en lecture seule 10 jours après la séance).
 * 3. Seule l'administratrice peut déverrouiller une fiche suite à une demande motivée.
 * 4. L'administratrice et la super-utilisatrice disposent de droits étendus de coordination.
 */
class LogbookEntryPolicy
{
    /**
     * Détermine si l'utilisateur peut lister les fiches de séance.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Détermine si l'utilisateur peut consulter une fiche de séance donnée.
     */
    public function view(User $user, LogbookEntry $entry): bool
    {
        return $user->is_active;
    }

    /**
     * Détermine si l'utilisateur peut créer / initialiser une fiche de séance.
     */
    public function create(User $user): Response
    {
        if ($user->is_active) {
            return Response::allow();
        }

        return Response::deny("Votre compte utilisateur est inactif.");
    }

    /**
     * Détermine si l'utilisateur peut modifier ou enregistrer (brouillon ou final) une fiche.
     * Enforce le contrôle IDOR et la règle d'intégrité J+10.
     */
    public function update(User $user, LogbookEntry $entry): Response
    {
        if (! $user->is_active) {
            return Response::deny("Votre compte utilisateur est inactif.");
        }

        // L'administratrice a un accès complet en écriture pour corriger ou compléter
        if ($user->isAdmin()) {
            return Response::allow();
        }

        // Vérification de la règle métier du verrouillage automatique à J+10
        if ($entry->isLocked()) {
            return Response::deny(
                "Cette fiche est verrouillée (délai de 10 jours après la séance dépassé). " .
                "Veuillez formuler une demande de déverrouillage motivée auprès de Mariette Travard."
            );
        }

        // La super-utilisatrice peut modifier toutes les fiches pour soutenir la coordination
        if ($user->isSuperUser()) {
            return Response::allow();
        }

        // Pour un artiste / intervenant : vérifie qu'il est bien assigné à cette séance
        $isAuthor = ($entry->author_id === $user->id);
        $isAssigned = false;

        if ($entry->scheduledSession) {
            $isAssigned = ($entry->scheduledSession->referent_id === $user->id);
        }

        if ($isAuthor || $isAssigned) {
            return Response::allow();
        }

        return Response::deny("Vous n'êtes pas assigné à cet atelier et ne pouvez pas modifier cette fiche (protection IDOR).");
    }

    /**
     * Détermine si l'utilisateur peut formuler une demande de déverrouillage motivée.
     */
    public function requestUnlock(User $user, LogbookEntry $entry): Response
    {
        if (! $user->is_active) {
            return Response::deny("Votre compte utilisateur est inactif.");
        }

        if (! $entry->isLocked()) {
            return Response::deny("Cette fiche n'est pas verrouillée.");
        }

        return Response::allow();
    }

    /**
     * Détermine si l'utilisateur peut accorder un déverrouillage de fiche.
     * Réservé exclusivement à l'Administratrice.
     */
    public function unlock(User $user, LogbookEntry $entry): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return Response::deny("Seule l'administratrice peut valider le déverrouillage d'une fiche.");
    }

    /**
     * Détermine si l'utilisateur peut supprimer une fiche de séance.
     */
    public function delete(User $user, LogbookEntry $entry): Response
    {
        if ($user->isAdmin()) {
            return Response::allow();
        }

        return Response::deny("Seule l'administratrice peut supprimer une fiche de séance.");
    }
}
