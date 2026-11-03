import { createContext, useCallback, useMemo, useState } from 'react';
import { ROLES } from './roles.js';

export const AuthContext = createContext(null);

/*
 * AuthContext SIMULÉ, en attendant la fusion de la PR #65 (GET /api/me, POST /api/logout).
 *
 * `user` a exactement la forme de la réponse de GET /api/me :
 *   { id, name, email, role, role_label }
 * Les comptes sont ceux des seeders de démonstration, et `role_label` reprend
 * UserRole::label() côté backend. Quand l'API sera branchée, seul ce fichier change.
 *
 * Rôle de démonstration : variable VITE_MOCK_ROLE (admin, super_user, contributor),
 * admin par défaut pour voir toutes les entrées du menu.
 */
const MOCK_USERS = {
  [ROLES.ADMIN]: {
    id: 1,
    name: 'Mariette Travard',
    email: 'mariette.travard@klap.demo',
    role: ROLES.ADMIN,
    role_label: 'Administratrice',
  },
  [ROLES.SUPER_USER]: {
    id: 2,
    name: 'Emilie Garetier',
    email: 'emilie.garetier@klap.demo',
    role: ROLES.SUPER_USER,
    role_label: 'Super-utilisatrice',
  },
  [ROLES.CONTRIBUTOR]: {
    id: 3,
    name: 'Léa Fournier',
    email: 'lea.fournier@klap.demo',
    role: ROLES.CONTRIBUTOR,
    role_label: 'Intervenant',
  },
};

function defaultMockUser() {
  const role = import.meta.env.VITE_MOCK_ROLE;
  return MOCK_USERS[role] ?? MOCK_USERS[ROLES.ADMIN];
}

// `initialUser` sert aux tests ; passer null simule un visiteur non connecté.
export function AuthProvider({ children, initialUser }) {
  const [user, setUser] = useState(() =>
    initialUser === undefined ? defaultMockUser() : initialUser,
  );

  const logout = useCallback(() => {
    // À remplacer par POST /api/logout et la suppression du token.
    setUser(null);
  }, []);

  const value = useMemo(() => ({ user, logout }), [user, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export { MOCK_USERS };
