import { apiRequest } from './ApiClient.js';

// Mot de passe oublié (#81) : routes publiques, sans jeton de session.

/** POST /forgot-password : même réponse que l'adresse existe ou non. */
export async function requestPasswordReset(email) {
  return (await apiRequest('/forgot-password', { method: 'POST', body: { email } })).message;
}

/** POST /reset-password : jeton et adresse du lien reçu par mail. */
export async function resetPassword({ token, email, password, passwordConfirmation }) {
  return (
    await apiRequest('/reset-password', {
      method: 'POST',
      body: { token, email, password, password_confirmation: passwordConfirmation },
    })
  ).message;
}
