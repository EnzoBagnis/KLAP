// Valeurs des rôles, identiques à l'énumération App\Enums\UserRole du backend.
// Les libellés affichés ne sont pas redéfinis ici : l'API les fournit dans `role_label`.
export const ROLES = Object.freeze({
  ADMIN: 'admin',
  SUPER_USER: 'super_user',
  CONTRIBUTOR: 'contributor',
});
