import { apiRequest } from './ApiClient.js';

/*
 * Le jeton de connexion. On le range à l'un de ces deux endroits :
 * - sessionStorage par défaut : fermer le navigateur ferme la session. C'est le bon choix
 *   sur un poste partagé (lycée, collège) ;
 * - localStorage si la personne coche « Rester connecté·e » : sur son téléphone, une
 *   intervenante n'a pas à se reconnecter à chaque fiche. Le jeton expire de toute façon
 *   au bout de 30 jours, et l'API le refuse dès que le compte est désactivé.
 * On n'en remplit qu'un à la fois ; la déconnexion vide les deux.
 */

/** La clé sous laquelle on range le jeton. */
const TOKEN_KEY = 'klap.token';

/**
 * Les deux stockages du navigateur, ou null pour celui qui est bloqué.
 *
 * @returns {{session: Storage|null, local: Storage|null}}
 */
function storages() {
  // Le simple fait d'y accéder peut lever une erreur (navigation privée stricte, stockage bloqué).
  const read = (name) => {
    try {
      return window[name];
    } catch {
      return null;
    }
  };
  return { session: read('sessionStorage'), local: read('localStorage') };
}

/**
 * Le jeton enregistré, où qu'il soit.
 *
 * @returns {string|null}
 */
export function getToken() {
  const { session, local } = storages();
  try {
    return session?.getItem(TOKEN_KEY) ?? local?.getItem(TOKEN_KEY) ?? null;
  } catch {
    return null;
  }
}

/**
 * On enregistre le jeton.
 *
 * @param {string} token
 * @param {boolean} [remember=false]  le garder après la fermeture du navigateur
 */
export function setToken(token, remember = false) {
  const { session, local } = storages();
  clearToken();
  try {
    (remember ? local : session)?.setItem(TOKEN_KEY, token);
  } catch {
    // Le stockage est indisponible (navigation privée stricte) : la session vivra le temps de la page.
  }
}

/** On efface le jeton des deux stockages. */
export function clearToken() {
  const { session, local } = storages();
  for (const storage of [session, local]) {
    try {
      storage?.removeItem(TOKEN_KEY);
    } catch {
      // Le stockage est bloqué : il n'y a rien à effacer.
    }
  }
}

/**
 * POST /login : on se connecte, on enregistre le jeton et on renvoie le compte.
 *
 * @param {string} email
 * @param {string} password
 * @param {{remember?: boolean}} [options]  rester connecté·e après la fermeture du navigateur
 * @returns {Promise<{id: number, name: string, email: string, role: string, role_label: string}>}
 */
export async function login(email, password, { remember = false } = {}) {
  const data = await apiRequest('/login', {
    method: 'POST',
    body: { email, password, device_name: 'navigateur' },
  });
  setToken(data.token, remember);
  return data.user;
}

/**
 * GET /me : le compte du jeton enregistré.
 *
 * @returns {Promise<object>}
 */
export async function fetchCurrentUser() {
  const data = await apiRequest('/me', { token: getToken() });
  return data.user;
}

/**
 * POST /logout : on révoque le jeton côté API, et on l'efface localement dans tous les cas.
 */
export async function logout() {
  // On l'efface avant l'appel : si l'API tarde et que quelqu'un se reconnecte entre-temps,
  // on ne doit pas effacer le nouveau jeton à la fin de cette requête.
  const token = getToken();
  clearToken();
  try {
    await apiRequest('/logout', { method: 'POST', token });
  } catch {
    // Le jeton est déjà révoqué ou le serveur est injoignable : la déconnexion locale suffit.
  }
}

/**
 * PUT /me/password : on change son mot de passe (#71). L'API déconnecte les autres appareils.
 *
 * @returns {Promise<string>} le message de confirmation
 */
export async function changePassword(currentPassword, password, passwordConfirmation) {
  const response = await apiRequest('/me/password', {
    method: 'PUT',
    body: { current_password: currentPassword, password, password_confirmation: passwordConfirmation },
    token: getToken(),
  });
  return response.message;
}
