// Client HTTP de l'API KLAP. Authentification par jeton Bearer (Sanctum), sans cookie.
const BASE_URL = (import.meta.env.VITE_API_URL ?? 'http://localhost:8080/api').replace(/\/$/, '');

export const NETWORK_ERROR_MESSAGE =
  'Impossible de joindre le serveur. Vérifiez votre connexion et réessayez.';

export class ApiError extends Error {
  constructor({ status, message = '', errors = {}, retryAfter = null }) {
    super(message);
    this.name = 'ApiError';
    this.status = status; // 0 quand le serveur n'a pas répondu
    this.errors = errors; // erreurs de validation (422), par champ
    this.retryAfter = retryAfter; // secondes à attendre (429)
  }
}

/**
 * Appelle l'API et renvoie le JSON de la réponse.
 * `Accept: application/json` est indispensable : sans lui, Laravel répond à une erreur
 * de validation par une redirection, et à un 401 par une erreur 500.
 */
export async function apiRequest(path, { method = 'GET', body, token } = {}) {
  const response = await send(path, { method, body, token });
  const data = await response.json().catch(() => null);
  if (!response.ok) throw errorFrom(response, data);
  return data;
}

/**
 * Télécharge un fichier (pré-bilan Word, #144) : renvoie le contenu et le nom donné par
 * l'en-tête Content-Disposition (null s'il est absent). Les erreurs restent en JSON.
 */
export async function apiDownload(path, { token } = {}) {
  const response = await send(path, { method: 'GET', token });
  if (!response.ok) throw errorFrom(response, await response.json().catch(() => null));
  return { blob: await response.blob(), filename: filenameFrom(response.headers.get('Content-Disposition')) };
}

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

function errorFrom(response, data) {
  const retryAfter = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);
  return new ApiError({
    status: response.status,
    message: data?.message ?? '',
    errors: data?.errors ?? {},
    retryAfter: Number.isNaN(retryAfter) ? null : retryAfter,
  });
}

/** `attachment; filename=pre-bilan.docx` ou `filename*=UTF-8''...` : le nom, sans chemin. */
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
  // Jamais de dossier dans le nom proposé à l'enregistrement.
  return name ? name.split(/[\\/]/).pop().trim() || null : null;
}
