import { vi } from 'vitest';
import { ROLES } from './Context/roles.js';

/** Comptes de démonstration (seeders), à passer en `initialUser` à AuthProvider. */
export const MOCK_USERS = {
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

/** Réponse JSON simulée de l'API. */
export function jsonResponse(body, status = 200, headers = {}) {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json', ...headers },
  });
}

/**
 * Remplace fetch par une table de routes : { 'POST /login': () => jsonResponse(...) }.
 * Une route absente fait échouer le test, pour repérer un appel inattendu.
 */
export function mockApi(routes) {
  const fetchMock = vi.fn(async (url, options = {}) => {
    const method = options.method ?? 'GET';
    const path = new URL(url).pathname.replace(/^\/api/, '');
    const handler = routes[`${method} ${path}`];
    if (!handler) throw new Error(`Appel inattendu : ${method} ${path}`);
    return handler(options);
  });
  vi.stubGlobal('fetch', fetchMock);
  return fetchMock;
}
