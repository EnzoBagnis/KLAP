import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

/**
 * POST /invitations : on invite quelqu'un à créer son compte.
 *
 * @param {{full_name: string, email: string, role: string}} payload
 * @returns {Promise<object>} l'invitation, avec `replaced` si elle en remplace une autre
 */
export async function createInvitation(payload) {
  return (await apiRequest('/invitations', { method: 'POST', body: payload, token: getToken() })).data;
}

/**
 * GET /invitations/{jeton} : ce que contient l'invitation, pour l'afficher avant l'activation.
 *
 * @param {string} token  le jeton du lien reçu par mail
 * @returns {Promise<{full_name: string, email: string, role: string, expires_at: string}>}
 */
export async function getInvitation(token) {
  return (await apiRequest(`/invitations/${encodeURIComponent(token)}`)).data;
}

/**
 * POST /invitations/{jeton}/activate : on crée le compte en choisissant son mot de passe.
 *
 * @returns {Promise<{email: string, name: string}>}
 */
export async function activateInvitation(token, password, passwordConfirmation) {
  return (
    await apiRequest(`/invitations/${encodeURIComponent(token)}/activate`, {
      method: 'POST',
      body: { password, password_confirmation: passwordConfirmation },
    })
  ).data;
}
