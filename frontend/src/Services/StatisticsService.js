import { apiDownload, apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * GET /statistics (#113) : totaux et détail par projet, sur les fiches rendues.
 * Filtres facultatifs : { projectId, from, to } (dates AAAA-MM-JJ, bornes incluses).
 */
function queryOf({ projectId, from, to } = {}) {
  const query = new URLSearchParams();
  if (projectId) query.set('project_id', projectId);
  if (from) query.set('from', from);
  if (to) query.set('to', to);
  return query.toString() ? `?${query}` : '';
}

export async function getStatistics(filters = {}) {
  return (await apiRequest(`/statistics${queryOf(filters)}`, { token: getToken() })).data;
}

/** GET /statistics/export (#219) : les mêmes chiffres en classeur Excel. Renvoie { blob, filename }. */
export async function exportStatistics(filters = {}) {
  return apiDownload(`/statistics/export${queryOf(filters)}`, { token: getToken() });
}
