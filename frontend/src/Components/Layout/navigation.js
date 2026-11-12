import { ROLES } from '../../Context/roles.js';
import DashboardPage from '../../Pages/Dashboard/DashboardPage.jsx';
import PlanningPage from '../../Pages/Planning/PlanningPage.jsx';
import ProjectListPage from '../../Pages/Projects/ProjectListPage.jsx';
import ProjectManagementPage from '../../Pages/Projects/ProjectManagementPage.jsx';
import PreAssessmentPage from '../../Pages/Reports/PreAssessmentPage.jsx';
import StatisticsPage from '../../Pages/Reports/StatisticsPage.jsx';

const { ADMIN, SUPER_USER, CONTRIBUTOR } = ROLES;
/** Les trois rôles, pour les entrées ouvertes à tout le monde. */
const ALL_ROLES = [ADMIN, SUPER_USER, CONTRIBUTOR];

/**
 * LA SOURCE UNIQUE des entrées de navigation.
 * La barre latérale (ordinateur) et le menu plein écran (téléphone) sont le même composant
 * et lisent tous deux cette liste : on ne duplique jamais ces entrées ailleurs. On en dérive
 * aussi les routes de AppRouter, rôles compris.
 *
 * Pour ajouter un écran au menu : on ajoute une entrée, avec `page: NomDeLaPage`.
 *
 * La visibilité par rôle est notre choix par défaut, PAS ENCORE VALIDÉ par la cliente.
 * - Gestion (onglets Projets, Utilisateurs et Listes) : administratrice seulement, la maquette tranche.
 * - Statistiques et Pré-bilan : admin et super_user. C'est un choix provisoire : les chiffres
 *   agrégés ne concernent pas l'intervenant, qui voit les projets où il est affecté (RG02).
 *   La question reste ouverte : issue #84.
 * - Le carnet n'a pas d'entrée propre : on y accède depuis le planning ou le tableau de bord.
 *
 * Masquer une entrée n'est pas une protection : l'API refuse l'accès de son côté.
 */
export const NAV_ITEMS = Object.freeze([
  { path: '/', label: 'Tableau de bord', roles: ALL_ROLES, end: true, page: DashboardPage },
  { path: '/projets', label: 'Projets', roles: ALL_ROLES, page: ProjectListPage },
  { path: '/planning', label: 'Planning', roles: ALL_ROLES, page: PlanningPage },
  { path: '/statistiques', label: 'Statistiques', roles: [ADMIN, SUPER_USER], page: StatisticsPage },
  { path: '/pre-bilan', label: 'Pré-bilan', roles: [ADMIN, SUPER_USER], page: PreAssessmentPage },
  { path: '/gestion', label: 'Gestion', roles: [ADMIN], page: ProjectManagementPage },
]);

/**
 * Les entrées du menu qu'un rôle a le droit de voir.
 *
 * @param {string} role  une valeur de ROLES
 * @returns {Array<object>}
 */
export function navItemsForRole(role) {
  return NAV_ITEMS.filter((item) => item.roles.includes(role));
}
