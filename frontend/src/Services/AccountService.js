import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Gestion des comptes par l'administratrice (#159). On ne supprime jamais un compte.

/**
 * GET /accounts : tous les comptes, les actifs d'abord.
 *
 * @returns {Promise<object[]>}
 */
export async function listAccounts() {
  return (await apiRequest('/accounts', { token: getToken() })).data;
}

/**
 * PATCH /accounts/{id} : on désactive ou on réactive un compte.
 *
 * @param {number} id
 * @param {boolean} isActive
 * @returns {Promise<object>} le compte à jour
 */
export async function setAccountActive(id, isActive) {
  const response = await apiRequest(`/accounts/${encodeURIComponent(id)}`, {
    method: 'PATCH',
    body: { is_active: isActive },
    token: getToken(),
  });
  return response.data;
}
