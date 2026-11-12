import { apiRequest } from './ApiClient.js';

// Mot de passe oublié (#81) : des routes publiques, qu'on appelle sans jeton de session.

/**
 * POST /forgot-password : on demande un lien. L'API répond la même chose, que l'adresse existe ou non.
 *
 * @returns {Promise<string>} le message à afficher
 */
export async function requestPasswordReset(email) {
  return (await apiRequest('/forgot-password', { method: 'POST', body: { email } })).message;
}

/**
 * POST /reset-password : on choisit un nouveau mot de passe, avec le jeton et l'adresse du lien reçu par mail.
 *
 * @returns {Promise<string>} le message de confirmation
 */
export async function resetPassword({ token, email, password, passwordConfirmation }) {
  return (
    await apiRequest('/reset-password', {
      method: 'POST',
      body: { token, email, password, password_confirmation: passwordConfirmation },
    })
  ).message;
}
