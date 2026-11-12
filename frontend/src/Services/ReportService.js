import { apiDownload } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * GET /projects/{id}/pre-report (#143) : le pré-bilan Word d'un projet pour un financeur,
 * sur une période facultative.
 *
 * @param {number|string} projectId
 * @param {{funderId: number|string, from?: string, to?: string}} filters  dates AAAA-MM-JJ, bornes incluses
 * @returns {Promise<{blob: Blob, filename: string|null}>}
 */
export async function downloadPreReport(projectId, { funderId, from, to }) {
  const query = new URLSearchParams({ funder_id: funderId });
  if (from) query.set('from', from);
  if (to) query.set('to', to);
  return apiDownload(`/projects/${encodeURIComponent(projectId)}/pre-report?${query}`, { token: getToken() });
}

/**
 * On propose le fichier à l'enregistrement, par un lien temporaire.
 *
 * @param {Blob} blob
 * @param {string} filename
 */
export function saveFile(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.append(link);
  link.click();
  link.remove();
  // On laisse au navigateur le temps de lancer le téléchargement avant de libérer le lien.
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
