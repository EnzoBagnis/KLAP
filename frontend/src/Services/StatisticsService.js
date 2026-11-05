import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * GET /statistics (#113) : totaux et détail par projet, sur les fiches rendues.
 * Filtres facultatifs : { projectId, from, to } (dates AAAA-MM-JJ, bornes incluses).
 */
export async function getStatistics({ projectId, from, to } = {}) {
  const query = new URLSearchParams();
  if (projectId) query.set('project_id', projectId);
  if (from) query.set('from', from);
  if (to) query.set('to', to);
  const suffix = query.toString() ? `?${query}` : '';
  return (await apiRequest(`/statistics${suffix}`, { token: getToken() })).data;
}
