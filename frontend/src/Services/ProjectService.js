import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Lecture des projets. L'API filtre selon le rôle (RG02) : un intervenant ne reçoit que
// ses projets, et une 404 pour les autres. Les erreurs remontent en ApiError.

/** GET /projects : projets non archivés visibles par l'utilisateur. */
export async function listProjects() {
  const response = await apiRequest('/projects', { token: getToken() });
  return response.data;
}

/** GET /projects/{id} : détail, avec sous-projets, équipe et financeurs. */
export async function getProject(id) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}`, { token: getToken() });
  return response.data;
}
