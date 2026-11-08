import { apiRequest } from './ApiClient.js';
import { getToken } from './AuthService.js';

// Gestion des comptes par l'administratrice (#159). Un compte n'est jamais supprimé.

/** GET /accounts : tous les comptes, les actifs d'abord. */
export async function listAccounts() {
  return (await apiRequest('/accounts', { token: getToken() })).data;
}

/** PATCH /accounts/{id} : désactive ou réactive un compte, renvoie le compte à jour. */
export async function setAccountActive(id, isActive) {
  const response = await apiRequest(`/accounts/${encodeURIComponent(id)}`, {
    method: 'PATCH',
    body: { is_active: isActive },
    token: getToken(),
  });
  return response.data;
}
