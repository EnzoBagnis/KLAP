/**
 * Les valeurs des rôles, identiques à l'énumération App\Enums\UserRole du backend. On ne
 * redéfinit pas ici les libellés affichés : l'API les fournit dans `role_label`.
 */
export const ROLES = Object.freeze({
  ADMIN: 'admin',
  SUPER_USER: 'super_user',
  CONTRIBUTOR: 'contributor',
});
