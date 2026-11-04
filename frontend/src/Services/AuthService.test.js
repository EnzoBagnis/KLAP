import { clearToken, fetchCurrentUser, getToken, login, logout, setToken } from './AuthService.js';
import { jsonResponse, mockApi } from '../testUtils.js';

const lea = { id: 3, name: 'Léa Fournier', role: 'contributor', role_label: 'Intervenant' };

describe('AuthService', () => {
  it('garde le jeton en sessionStorage', () => {
    setToken('t1');
    expect(window.sessionStorage.getItem('klap.token')).toBe('t1');
    expect(getToken()).toBe('t1');
    clearToken();
    expect(getToken()).toBeNull();
  });

  it('enregistre le jeton reçu à la connexion', async () => {
    const fetchMock = mockApi({ 'POST /login': () => jsonResponse({ token: 'jeton', user: lea }) });

    await expect(login('lea@klap.demo', 'secret')).resolves.toEqual(lea);

    expect(getToken()).toBe('jeton');
    expect(JSON.parse(fetchMock.mock.calls[0][1].body)).toEqual({
      email: 'lea@klap.demo',
      password: 'secret',
      device_name: 'navigateur',
    });
  });

  it('ne garde aucun jeton si la connexion échoue', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ errors: { email: ['Faux.'] } }, 422) });

    await expect(login('lea@klap.demo', 'faux')).rejects.toMatchObject({ status: 422 });
    expect(getToken()).toBeNull();
  });

  it('lit l’utilisateur du jeton', async () => {
    setToken('jeton');
    const fetchMock = mockApi({ 'GET /me': () => jsonResponse({ user: lea }) });

    await expect(fetchCurrentUser()).resolves.toEqual(lea);
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe('Bearer jeton');
  });

  it('une déconnexion lente n’efface pas le jeton d’une nouvelle connexion', async () => {
    setToken('ancien');
    let release;
    const fetchMock = mockApi({ 'POST /logout': () => new Promise((resolve) => (release = resolve)) });

    const pending = logout();
    expect(getToken()).toBeNull();
    setToken('nouveau'); // reconnexion pendant que /logout est en cours
    release(jsonResponse({ message: 'Déconnecté.' }));
    await pending;

    expect(getToken()).toBe('nouveau');
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBe('Bearer ancien');
  });

  it('efface le jeton à la déconnexion, même si l’API refuse', async () => {
    setToken('jeton');
    mockApi({ 'POST /logout': () => jsonResponse({ message: 'Unauthenticated.' }, 401) });

    await logout();

    expect(getToken()).toBeNull();
  });
});
