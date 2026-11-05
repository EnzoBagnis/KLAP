import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Planning (#102). L'API filtre selon le rôle : rien à refaire côté client.

/** GET /sessions : séances entre deux dates (AAAA-MM-JJ), bornes incluses. */
export async function listSessions(from, to) {
  const query = new URLSearchParams({ from, to });
  return (await apiRequest(`/sessions?${query}`, { token: getToken() })).data;
}

/** POST /projects/{id}/sessions : programme une séance (administratrice). */
export async function createSession(projectId, payload) {
  return (
    await apiRequest(`/projects/${encodeURIComponent(projectId)}/sessions`, {
      method: 'POST',
      body: payload,
      token: getToken(),
    })
  ).data;
}
