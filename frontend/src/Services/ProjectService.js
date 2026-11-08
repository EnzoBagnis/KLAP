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

/** POST /projects : crée un projet (administratrice) et renvoie le projet créé. */
export async function createProject(payload) {
  const response = await apiRequest('/projects', { method: 'POST', body: payload, token: getToken() });
  return response.data;
}

/** GET /project-types : types de projet, actifs d'abord. */
export async function listProjectTypes() {
  const response = await apiRequest('/project-types', { token: getToken() });
  return response.data;
}

/** PATCH /projects/{id} : modifie les champs envoyés (administratrice), renvoie le projet. */
export async function updateProject(id, changes) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}`, { method: 'PATCH', body: changes, token: getToken() });
  return response.data;
}

/** POST /projects/{id}/archive : archive après confirmation par le nom exact. Rien n'est effacé. */
export async function archiveProject(id, confirmName) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}/archive`, {
    method: 'POST',
    body: { confirm_name: confirmName },
    token: getToken(),
  });
  return response.data;
}

/** POST /projects/{id}/restore : remet un projet archivé en cours. */
export async function restoreProject(id) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}/restore`, { method: 'POST', token: getToken() });
  return response.data;
}
