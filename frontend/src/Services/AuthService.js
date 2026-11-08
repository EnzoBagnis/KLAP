import { apiRequest } from './ApiClient.js';

/*
 * Jeton de session : sessionStorage plutôt que localStorage.
 * Les jetons Sanctum n'expirent pas, et les intervenants se connectent parfois sur des
 * postes partagés (lycée, collège) : fermer le navigateur doit fermer la session.
 * Pour changer de stockage, seules ces trois fonctions sont à modifier.
 */
const TOKEN_KEY = 'klap.token';

export function getToken() {
  try {
    return window.sessionStorage.getItem(TOKEN_KEY);
  } catch {
    return null;
  }
}

export function setToken(token) {
  try {
    window.sessionStorage.setItem(TOKEN_KEY, token);
  } catch {
    // Stockage indisponible (navigation privée stricte) : la session vivra le temps de la page.
  }
}

export function clearToken() {
  try {
    window.sessionStorage.removeItem(TOKEN_KEY);
  } catch {
    // Rien à effacer.
  }
}

/** POST /login : enregistre le jeton et renvoie l'utilisateur ({ id, name, email, role, role_label }). */
export async function login(email, password) {
  const data = await apiRequest('/login', {
    method: 'POST',
    body: { email, password, device_name: 'navigateur' },
  });
  setToken(data.token);
  return data.user;
}

/** GET /me : l'utilisateur du jeton enregistré. */
export async function fetchCurrentUser() {
  const data = await apiRequest('/me', { token: getToken() });
  return data.user;
}

/** POST /logout : révoque le jeton côté API, et l'efface localement dans tous les cas. */
export async function logout() {
  // Effacé avant l'appel : si l'API tarde et que quelqu'un se reconnecte entre-temps,
  // le nouveau jeton ne doit pas être effacé à la fin de cette requête.
  const token = getToken();
  clearToken();
  try {
    await apiRequest('/logout', { method: 'POST', token });
  } catch {
    // Jeton déjà révoqué ou serveur injoignable : la déconnexion locale suffit.
  }
}

/** PUT /me/password : change son mot de passe (#71). Les autres appareils sont déconnectés. */
export async function changePassword(currentPassword, password, passwordConfirmation) {
  const response = await apiRequest('/me/password', {
    method: 'PUT',
    body: { current_password: currentPassword, password, password_confirmation: passwordConfirmation },
    token: getToken(),
  });
  return response.message;
}
