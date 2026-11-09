import { apiDownload } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * GET /projects/{id}/pre-report (#143) : pré-bilan Word d'un projet pour un financeur,
 * sur une période facultative (AAAA-MM-JJ, bornes incluses). Renvoie { blob, filename }.
 */
export async function downloadPreReport(projectId, { funderId, from, to }) {
  const query = new URLSearchParams({ funder_id: funderId });
  if (from) query.set('from', from);
  if (to) query.set('to', to);
  return apiDownload(`/projects/${encodeURIComponent(projectId)}/pre-report?${query}`, { token: getToken() });
}

/** Propose le fichier à l'enregistrement, par un lien temporaire. */
export function saveFile(blob, filename) {
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename;
  document.body.append(link);
  link.click();
  link.remove();
  // Laisser au navigateur le temps de lancer le téléchargement avant de libérer le lien.
  setTimeout(() => URL.revokeObjectURL(url), 1000);
}
