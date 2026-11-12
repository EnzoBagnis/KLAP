import { apiDownload, apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * On écrit les filtres dans l'adresse.
 *
 * @param {{projectId?: string|number, from?: string, to?: string}} [filters]  dates AAAA-MM-JJ, bornes incluses
 * @returns {string} « ?project_id=…&from=… », ou une chaîne vide sans filtre
 */
function queryOf({ projectId, from, to } = {}) {
  const query = new URLSearchParams();
  if (projectId) query.set('project_id', projectId);
  if (from) query.set('from', from);
  if (to) query.set('to', to);
  return query.toString() ? `?${query}` : '';
}

/**
 * GET /statistics (#113) : les totaux et le détail par projet, calculés sur les fiches rendues.
 *
 * @param {{projectId?: string|number, from?: string, to?: string}} [filters]
 * @returns {Promise<object>}
 */
export async function getStatistics(filters = {}) {
  return (await apiRequest(`/statistics${queryOf(filters)}`, { token: getToken() })).data;
}

/**
 * GET /statistics/export (#219) : les mêmes chiffres, en classeur Excel.
 *
 * @returns {Promise<{blob: Blob, filename: string|null}>}
 */
export async function exportStatistics(filters = {}) {
  return apiDownload(`/statistics/export${queryOf(filters)}`, { token: getToken() });
}
