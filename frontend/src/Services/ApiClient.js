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
  const headers = { Accept: 'application/json' };
  if (body !== undefined) headers['Content-Type'] = 'application/json';
  if (token) headers.Authorization = `Bearer ${token}`;

  let response;
  try {
    response = await fetch(`${BASE_URL}${path}`, {
      method,
      headers,
      body: body === undefined ? undefined : JSON.stringify(body),
    });
  } catch {
    throw new ApiError({ status: 0, message: NETWORK_ERROR_MESSAGE });
  }

  const data = await response.json().catch(() => null);

  if (!response.ok) {
    const retryAfter = Number.parseInt(response.headers.get('Retry-After') ?? '', 10);
    throw new ApiError({
      status: response.status,
      message: data?.message ?? '',
      errors: data?.errors ?? {},
      retryAfter: Number.isNaN(retryAfter) ? null : retryAfter,
    });
  }

  return data;
}
