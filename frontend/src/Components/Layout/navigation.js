import { ROLES } from '../../Context/roles.js';
import DashboardPage from '../../Pages/Dashboard/DashboardPage.jsx';
import PlanningPage from '../../Pages/Planning/PlanningPage.jsx';
import ProjectListPage from '../../Pages/Projects/ProjectListPage.jsx';
import ProjectManagementPage from '../../Pages/Projects/ProjectManagementPage.jsx';
import StatisticsPage from '../../Pages/Reports/StatisticsPage.jsx';

const { ADMIN, SUPER_USER, CONTRIBUTOR } = ROLES;
const ALL_ROLES = [ADMIN, SUPER_USER, CONTRIBUTOR];

/*
 * SOURCE UNIQUE des entrées de navigation.
 * La sidebar (ordinateur) et le menu plein écran (téléphone) sont le même composant
 * et lisent tous deux cette liste : ne jamais dupliquer ces entrées ailleurs.
 * Les routes de AppRouter en sont aussi dérivées, rôles compris.
 *
 * Brancher un écran : ajouter `page: NomDeLaPage` (composant importé ici) à son entrée.
 *
 * Visibilité par rôle : choix par défaut de l'équipe, PAS ENCORE VALIDÉ par la cliente.
 * - Gestion (onglets Projets et Utilisateurs) : administratrice seulement, la maquette tranche.
 * - Statistiques et Pré-bilan : admin et super_user. Choix provisoire : les chiffres agrégés
 *   ne concernent pas l'intervenant, qui voit les projets où il est affecté (RG02).
 *   Question ouverte : issue #84.
 * - Le carnet n'a pas d'entrée propre : on y accède depuis un projet, par tous les rôles.
 *
 * Masquer une entrée n'est pas une protection : l'API doit refuser l'accès de son côté.
 */
export const NAV_ITEMS = Object.freeze([
  { path: '/', label: 'Tableau de bord', roles: ALL_ROLES, end: true, page: DashboardPage },
  { path: '/projets', label: 'Projets', roles: ALL_ROLES, page: ProjectListPage },
  { path: '/planning', label: 'Planning', roles: ALL_ROLES, page: PlanningPage },
  { path: '/statistiques', label: 'Statistiques', roles: [ADMIN, SUPER_USER], page: StatisticsPage },
  { path: '/pre-bilan', label: 'Pré-bilan', roles: [ADMIN, SUPER_USER] },
  { path: '/gestion', label: 'Gestion', roles: [ADMIN], page: ProjectManagementPage },
]);

export function navItemsForRole(role) {
  return NAV_ITEMS.filter((item) => item.roles.includes(role));
}
