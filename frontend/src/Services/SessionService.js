import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Planning (#102). C'est l'API qui filtre selon le rôle : on n'a rien à refaire côté client.

/**
 * GET /sessions : les séances entre deux dates, bornes incluses.
 *
 * @param {string} from  AAAA-MM-JJ
 * @param {string} to  AAAA-MM-JJ
 * @returns {Promise<object[]>}
 */
export async function listSessions(from, to) {
  const query = new URLSearchParams({ from, to });
  return (await apiRequest(`/sessions?${query}`, { token: getToken() })).data;
}

/** POST /projects/{id}/sessions : on programme une séance (administratrice). */
export async function createSession(projectId, payload) {
  return (
    await apiRequest(`/projects/${encodeURIComponent(projectId)}/sessions`, {
      method: 'POST',
      body: payload,
      token: getToken(),
    })
  ).data;
}

/** PATCH /sessions/{id} : on modifie une séance sans fiche (#180, administratrice). */
export async function updateSession(sessionId, payload) {
  return (await apiRequest(`/sessions/${encodeURIComponent(sessionId)}`, { method: 'PATCH', body: payload, token: getToken() })).data;
}

/** POST /sessions/{id}/cancel : on annule une séance sans fiche (#180). */
export async function cancelSession(sessionId) {
  return (await apiRequest(`/sessions/${encodeURIComponent(sessionId)}/cancel`, { method: 'POST', token: getToken() })).data;
}

/** POST /sessions/{id}/restore : on rétablit une séance annulée (#180). */
export async function restoreSession(sessionId) {
  return (await apiRequest(`/sessions/${encodeURIComponent(sessionId)}/restore`, { method: 'POST', token: getToken() })).data;
}
