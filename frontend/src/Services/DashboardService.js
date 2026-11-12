import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * GET /dashboard (#146) : les fiches à remplir, les prochaines séances, les dernières fiches
 * rendues et, pour l'administration, le suivi. C'est l'API qui applique les droits.
 *
 * @returns {Promise<object>}
 */
export async function getDashboard() {
  return apiRequest('/dashboard', { token: getToken() });
}
