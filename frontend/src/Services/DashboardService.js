import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * GET /dashboard (#146) : fiches à remplir, prochaines séances, dernières fiches rendues
 * et, pour l'administration, le suivi. Les droits sont appliqués par l'API.
 */
export async function getDashboard() {
  return apiRequest('/dashboard', { token: getToken() });
}
