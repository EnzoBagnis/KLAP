import { createContext, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import * as AuthService from '../Services/AuthService.js';

/** Le contexte de la session. On le lit avec le hook useAuth. */
export const AuthContext = createContext(null);

/**
 * La session de l'utilisateur, qu'on branche sur l'API (POST /login, GET /me, POST /logout).
 *
 * `status` vaut 'loading' le temps qu'on vérifie un jeton déjà enregistré, puis
 * 'authenticated' ou 'guest'. `user` a la forme de GET /api/me :
 *   { id, name, email, role, role_label }
 *
 * @param {object} props
 * @param {React.ReactNode} props.children
 * @param {object|null} [props.initialUser]  pour nos tests (voir MOCK_USERS dans testUtils.js) :
 *   on fixe l'utilisateur sans aucun appel à l'API ; null simule un visiteur non connecté
 */
export function AuthProvider({ children, initialUser }) {
  const controlled = initialUser !== undefined;

  const [user, setUser] = useState(controlled ? initialUser : null);
  const [status, setStatus] = useState(() => {
    if (controlled) return initialUser ? 'authenticated' : 'guest';
    return AuthService.getToken() ? 'loading' : 'guest';
  });

  // On l'incrémente à chaque connexion ou déconnexion : une vérification lancée avant
  // ne doit plus toucher à la session.
  const sessionVersion = useRef(0);

  // Un jeton est déjà enregistré (page rechargée) : on vérifie qu'il est encore valide.
  useEffect(() => {
    if (status !== 'loading') return undefined;
    let cancelled = false;
    const version = sessionVersion.current;
    const checkedToken = AuthService.getToken();
    const outdated = () => cancelled || version !== sessionVersion.current;

    AuthService.fetchCurrentUser()
      .then((currentUser) => {
        if (outdated()) return;
        setUser(currentUser);
        setStatus('authenticated');
      })
      .catch((error) => {
        if (outdated()) return;
        // Si le jeton est révoqué, on l'oublie, sauf s'il a été remplacé entre-temps.
        // Si le serveur est injoignable, on le garde pour la prochaine fois.
        if (error.status === 401 && AuthService.getToken() === checkedToken) {
          AuthService.clearToken();
        }
        setStatus('guest');
      });

    return () => {
      cancelled = true;
    };
    // On ne vérifie qu'au montage.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const login = useCallback(async (email, password, options) => {
    const loggedUser = await AuthService.login(email, password, options);
    sessionVersion.current += 1;
    setUser(loggedUser);
    setStatus('authenticated');
    return loggedUser;
  }, []);

  const logout = useCallback(async () => {
    sessionVersion.current += 1;
    setUser(null);
    setStatus('guest');
    if (!controlled) await AuthService.logout();
  }, [controlled]);

  const value = useMemo(() => ({ user, status, login, logout }), [user, status, login, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}
