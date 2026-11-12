/*
 * Client HTTP de l'API KLAP. Tous nos services passent par lui. On s'authentifie par jeton
 * Bearer (Sanctum), sans cookie.
 */

/** L'adresse de l'API, sans barre finale. On la fixe au build avec VITE_API_URL. */
const BASE_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api').replace(/\/$/, '');

/** Le message qu'on affiche quand le serveur ne répond pas du tout. */
export const NETWORK_ERROR_MESSAGE =
  'Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.';

/**
 * Une erreur renvoyée par l'API, ou l'absence de réponse. Nos pages s'appuient sur `status`
 * pour choisir quoi afficher.
 *
 * @property {number} status  le code HTTP, ou 0 quand le serveur n'a pas répondu
 * @property {Object<string, string[]>} errors  les erreurs de validation (422), par champ
 * @property {number|null} retryAfter  le nombre de secondes à attendre (429)
 */
export class ApiError extends Error {
  constructor({ status, message = '', errors = {}, retryAfter = null }) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.errors = errors;
    this.retryAfter = retryAfter;
  }
}

/**
 * On appelle l'API et on renvoie le JSON de la réponse.
 *
 * @param {string} path  le chemin, à partir de /api (« /projects »)
 * @param {{method?: string, body?: object, token?: string|null}} [options]
 * @returns {Promise<any>}
 * @throws {ApiError} si l'API refuse la requête ou ne répond pas
 */
export async function apiRequest(path, { method = 'GET', body, token } = {}) {
  const response = await send(path, { method, body, token });
  const data = await response.json().catch(() => null);
  if (!response.ok) throw errorFrom(response, data);
  return data;
}

/**
 * On télécharge un fichier (pré-bilan Word, #144 ; export Excel, #219). Les erreurs, elles,
 * restent en JSON.
 *
 * @param {string} path
 * @param {{token?: string|null}} [options]
 * @returns {Promise<{blob: Blob, filename: string|null}>} le contenu, et le nom donné par l'en-tête Content-Disposition
 * @throws {ApiError}
 */
export async function apiDownload(path, { token } = {}) {
  const response = await send(path, { method: 'GET', token });
  if (!response.ok) throw errorFrom(response, await response.json().catch(() => null));
  return { blob: await response.blob(), filename: filenameFrom(response.headers.get('Content-Disposition')) };
}

/**
 * On envoie la requête. `Accept: application/json` est indispensable : sans lui, Laravel
 * répond à une erreur de validation par une redirection, et à un 401 par une erreur 500.
 *
 * @returns {Promise<Response>}
 * @throws {ApiError} avec le statut 0 si le serveur est injoignable
 */
async function send(path, { method, body, token }) {
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  try {
    return await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError({ status: 0, message: NETWORK_ERROR_MESSAGE });
  }
}

/**
 * On construit l'ApiError d'une réponse en erreur.
 *
 * @param {Response} response
 * @param {object|null} data  le JSON de la réponse, s'il y en a un
 * @returns {ApiError}
 */
function errorFrom(response, data) {
  const retryAfter = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);
  return new ApiError({
    status: response.status,
    message: data?.message ?? '',
    errors: data?.errors ?? {},
    retryAfter: Number.isNaN(retryAfter) ? null : retryAfter,
  });
}

/**
 * On extrait le nom du fichier de l'en-tête Content-Disposition, sous ses deux formes :
 * `attachment; filename=pre-bilan.docx` ou `filename*=UTF-8''...`.
 *
 * @param {string|null} disposition
 * @returns {string|null} le nom, sans chemin, ou null
 */
export function filenameFrom(disposition) {
  if (!disposition) return null;
  const encoded = /filename\*=UTF-8''([^;]+)/i.exec(disposition);
  let name = null;
  if (encoded) {
    try {
      name = decodeURIComponent(encoded[1]);
    } catch {
      name = null;
    }
  }
  name ??= /filename="?([^";]+)"?/i.exec(disposition)?.[1] ?? null;
  // On ne garde jamais de dossier dans le nom qu'on propose à l'enregistrement.
  return name ? name.split(/[\\/]/).pop().trim() || null : null;
}
