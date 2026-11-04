import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Listes qui alimentent les menus des formulaires. Elles proposent exactement les valeurs
// que l'API accepte à la création d'un projet (lieux actifs, intervenants actifs).

export async function listLocations() {
  return (await apiRequest('/locations', { token: getToken() })).data;
}

export async function listContributors() {
  return (await apiRequest('/users?role=contributor', { token: getToken() })).data;
}

export async function listFunders() {
  return (await apiRequest('/funders', { token: getToken() })).data;
}
