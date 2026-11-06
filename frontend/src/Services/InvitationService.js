import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Invitations (#126). Création par l'administratrice ; lecture et activation publiques,
// par le jeton reçu par mail (pas de jeton de session).

export async function createInvitation(payload) {
  return (await apiRequest('/invitations', { method: 'POST', body: payload, token: getToken() })).data;
}

export async function getInvitation(token) {
  return (await apiRequest(`/invitations/${encodeURIComponent(token)}`)).data;
}

export async function activateInvitation(token, password, passwordConfirmation) {
  return (
    await apiRequest(`/invitations/${encodeURIComponent(token)}/activate`, {
      method: 'POST',
      body: { password, password_confirmation: passwordConfirmation },
    })
  ).data;
}
