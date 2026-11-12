import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Projets, groupes et équipes. C'est l'API qui filtre selon le rôle (RG02) : un intervenant
// ne reçoit que ses projets, et une 404 pour les autres. Les erreurs remontent en ApiError.

/**
 * GET /projects : les projets que l'utilisateur peut voir. On masque les archivés, sauf demande.
 *
 * @param {{includeArchived?: boolean}} [options]
 * @returns {Promise<object[]>}
 */
export async function listProjects({ includeArchived = false } = {}) {
  const response = await apiRequest(includeArchived ? '/projects?include_archived=1' : '/projects', { token: getToken() });
  return response.data;
}

/** GET /projects/{id} : le détail, avec les groupes, l'équipe et les financeurs. */
export async function getProject(id) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}`, { token: getToken() });
  return response.data;
}

/** POST /projects : on crée un projet (administratrice) ; l'API renvoie le projet créé. */
export async function createProject(payload) {
  const response = await apiRequest('/projects', { method: 'POST', body: payload, token: getToken() });
  return response.data;
}

/** GET /project-types : les types de projet, les actifs d'abord. */
export async function listProjectTypes() {
  const response = await apiRequest('/project-types', { token: getToken() });
  return response.data;
}

/**
 * PATCH /projects/{id} : on modifie les champs envoyés (administratrice) ; l'API renvoie le projet.
 */
export async function updateProject(id, changes) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}`, { method: 'PATCH', body: changes, token: getToken() });
  return response.data;
}

/**
 * POST /projects/{id}/archive : on archive, après confirmation par le nom exact. On n'efface rien.
 */
export async function archiveProject(id, confirmName) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}/archive`, {
    method: 'POST',
    body: { confirm_name: confirmName },
    token: getToken(),
  });
  return response.data;
}

/** POST /projects/{id}/restore : on remet un projet archivé en cours. */
export async function restoreProject(id) {
  const response = await apiRequest(`/projects/${encodeURIComponent(id)}/restore`, { method: 'POST', token: getToken() });
  return response.data;
}

/** POST /project-types (#249) : on crée un type de projet. */
export async function createProjectType(payload) {
  const response = await apiRequest('/project-types', { method: 'POST', body: payload, token: getToken() });
  return response.data;
}

/**
 * PATCH /project-types/{id} (#249) : on renomme un type, on le désactive ou on le réactive.
 */
export async function updateProjectType(id, changes) {
  const response = await apiRequest(`/project-types/${encodeURIComponent(id)}`, { method: 'PATCH', body: changes, token: getToken() });
  return response.data;
}

/** POST /projects/{id}/sub-projects (#250) : on ajoute un groupe à un projet. */
export async function createSubProject(projectId, payload) {
  const response = await apiRequest(`/projects/${encodeURIComponent(projectId)}/sub-projects`, { method: 'POST', body: payload, token: getToken() });
  return response.data;
}

/**
 * PATCH /sub-projects/{id} (#250) : on corrige un groupe, on le retire (is_active: false) ou on le remet.
 */
export async function updateSubProject(id, changes) {
  const response = await apiRequest(`/sub-projects/${encodeURIComponent(id)}`, { method: 'PATCH', body: changes, token: getToken() });
  return response.data;
}

/**
 * POST /projects/{id}/members (#251) : on ajoute un·e intervenant·e ponctuel·le ; l'API renvoie le projet.
 */
export async function addProjectMember(projectId, userId) {
  const response = await apiRequest(`/projects/${encodeURIComponent(projectId)}/members`, { method: 'POST', body: { user_id: Number(userId) }, token: getToken() });
  return response.data;
}

/**
 * DELETE /projects/{id}/members/{user} (#251) : on retire un·e ponctuel·le ; l'API renvoie le projet.
 */
export async function removeProjectMember(projectId, userId) {
  const response = await apiRequest(`/projects/${encodeURIComponent(projectId)}/members/${encodeURIComponent(userId)}`, { method: 'DELETE', token: getToken() });
  return response.data;
}
