import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Les listes qui alimentent les menus de nos formulaires. Elles proposent exactement les
// valeurs que l'API accepte à la création d'un projet (lieux actifs, intervenants actifs).

/** GET /locations : les lieux actifs. */
export async function listLocations() {
  return (await apiRequest('/locations', { token: getToken() })).data;
}

/** POST /locations (#134) : on crée un lieu. C'est ouvert à tous les rôles. */
export async function createLocation(payload) {
  return (await apiRequest('/locations', { method: 'POST', body: payload, token: getToken() })).data;
}

/**
 * GET /users?equipe=1 : les comptes actifs qu'on peut mettre dans une équipe. On y trouve
 * aussi l'administratrice et la super-utilisatrice, qui peuvent intervenir elles aussi (#272).
 */
export async function listContributors() {
  return (await apiRequest('/users?equipe=1', { token: getToken() })).data;
}

/** GET /funders : les financeurs, avec leur état et le nombre de projets qu'ils financent. */
export async function listFunders() {
  return (await apiRequest('/funders', { token: getToken() })).data;
}

// --- Gestion › Listes (#249), administratrice : financeurs et lieux, désactivés compris ---

/** POST /funders : on crée un financeur. */
export async function createFunder(payload) {
  return (await apiRequest('/funders', { method: 'POST', body: payload, token: getToken() })).data;
}

/** PATCH /funders/{id} : on renomme un financeur, on le désactive ou on le réactive. */
export async function updateFunder(id, changes) {
  return (await apiRequest(`/funders/${encodeURIComponent(id)}`, { method: 'PATCH', body: changes, token: getToken() })).data;
}

/** GET /locations?tous=1 : tous les lieux, désactivés compris. */
export async function listAllLocations() {
  return (await apiRequest('/locations?tous=1', { token: getToken() })).data;
}

/** PATCH /locations/{id} : on corrige un lieu, on le désactive ou on le réactive. */
export async function updateLocation(id, changes) {
  return (await apiRequest(`/locations/${encodeURIComponent(id)}`, { method: 'PATCH', body: changes, token: getToken() })).data;
}
