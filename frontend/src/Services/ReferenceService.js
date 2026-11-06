import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Listes qui alimentent les menus des formulaires. Elles proposent exactement les valeurs
// que l'API accepte à la création d'un projet (lieux actifs, intervenants actifs).

export async function listLocations() {
  return (await apiRequest('/locations', { token: getToken() })).data;
}

/** POST /locations (#134) : nouveau lieu, ouvert à tous les rôles. */
export async function createLocation(payload) {
  return (await apiRequest('/locations', { method: 'POST', body: payload, token: getToken() })).data;
}

export async function listContributors() {
  return (await apiRequest('/users?role=contributor', { token: getToken() })).data;
}

export async function listFunders() {
  return (await apiRequest('/funders', { token: getToken() })).data;
}
