import { apiRequest } from './ApiClient.js';

/*
 * Jeton de connexion, à deux endroits possibles :
 * - sessionStorage par défaut : fermer le navigateur ferme la session. C'est le bon choix
 *   sur un poste partagé (lycée, collège) ;
 * - localStorage si la personne coche « Rester connecté·e » : sur son téléphone, une
 *   intervenante n'a pas à se reconnecter à chaque fiche. Le jeton expire de toute façon
 *   au bout de 30 jours, et il est refusé dès que le compte est désactivé.
 * Un seul des deux est rempli à la fois ; la déconnexion vide les deux.
 */
const TOKEN_KEY = 'klap.token';

function storages() {
  // L'accès lui-même peut lever une erreur (navigation privée stricte, stockage bloqué).
  const read = (name) => {
    try {
      return window[name];
    } catch {
      return null;
    }
  };
  return { session: read('sessionStorage'), local: read('localStorage') };
}

export function getToken() {
  const { session, local } = storages();
  try {
    return session?.getItem(TOKEN_KEY) ?? local?.getItem(TOKEN_KEY) ?? null;
  } catch {
    return null;
  }
}

/** Enregistre le jeton ; `remember` le garde après la fermeture du navigateur. */
export function setToken(token, remember = false) {
  const { session, local } = storages();
  clearToken();
  try {
    (remember ? local : session)?.setItem(TOKEN_KEY, token);
  } catch {
    // Stockage indisponible (navigation privée stricte) : la session vivra le temps de la page.
  }
}

export function clearToken() {
  const { session, local } = storages();
  for (const storage of [session, local]) {
    try {
      storage?.removeItem(TOKEN_KEY);
    } catch {
      // Rien à effacer.
    }
  }
}

/**
 * POST /login : enregistre le jeton et renvoie l'utilisateur ({ id, name, email, role, role_label }).
 * `remember` : rester connecté·e après la fermeture du navigateur.
 */
export async function login(email, password, { remember = false } = {}) {
  const data = await apiRequest('/login', {
    method: 'POST',
    body: { email, password, device_name: 'navigateur' },
  });
  setToken(data.token, remember);
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
