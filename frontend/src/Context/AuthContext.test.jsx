import { render, screen, waitFor } from '@testing-library/react';
import { AuthProvider } from './AuthContext.jsx';
import useAuth from '../Hooks/useAuth.js';
import { getToken, setToken } from '../Services/AuthService.js';
import { MOCK_USERS, jsonResponse, mockApi } from '../testUtils.js';
import { vi } from 'vitest';

function Probe() {
  const { status, user, login } = useAuth();
  return (
    <>
      <p>
        {status}:{user?.name ?? 'personne'}
      </p>
      <button type="button" onClick={() => login('lea@klap.demo', 'secret')}>
        connexion
      </button>
    </>
  );
}

const renderProvider = (props = {}) =>
  render(
    <AuthProvider {...props}>
      <Probe />
    </AuthProvider>,
  );

describe('AuthProvider', () => {
  it('sans jeton : visiteur, sans aucun appel', () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);

    renderProvider();

    expect(screen.getByText('guest:personne')).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('avec un jeton valide : charge l’utilisateur', async () => {
    setToken('jeton');
    mockApi({ 'GET /me': () => jsonResponse({ user: MOCK_USERS.contributor }) });

    renderProvider();

    expect(screen.getByText('loading:personne')).toBeInTheDocument();
    expect(await screen.findByText('authenticated:Léa Fournier')).toBeInTheDocument();
  });

  it('avec un jeton révoqué : l’oublie et repasse en visiteur', async () => {
    setToken('vieux');
    mockApi({ 'GET /me': () => jsonResponse({ message: 'Unauthenticated.' }, 401) });

    renderProvider();

    expect(await screen.findByText('guest:personne')).toBeInTheDocument();
    expect(getToken()).toBeNull();
  });

  it('serveur injoignable : visiteur, mais le jeton est gardé', async () => {
    setToken('jeton');
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));

    renderProvider();

    await waitFor(() => expect(screen.getByText('guest:personne')).toBeInTheDocument());
    expect(getToken()).toBe('jeton');
  });

  it('une vérification tardive n’efface pas une connexion faite entre-temps', async () => {
    setToken('revoque');
    let releaseMe;
    mockApi({
      'GET /me': () => new Promise((resolve) => (releaseMe = resolve)),
      'POST /login': () => jsonResponse({ token: 'neuf', user: MOCK_USERS.contributor }),
    });
    renderProvider();

    screen.getByRole('button', { name: 'connexion' }).click();
    expect(await screen.findByText('authenticated:Léa Fournier')).toBeInTheDocument();

    releaseMe(jsonResponse({ message: 'Unauthenticated.' }, 401));
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(screen.getByText('authenticated:Léa Fournier')).toBeInTheDocument();
    expect(getToken()).toBe('neuf');
  });

  it('initialUser fixe l’utilisateur sans appeler l’API', () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);

    renderProvider({ initialUser: MOCK_USERS.admin });

    expect(screen.getByText('authenticated:Mariette Travard')).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
