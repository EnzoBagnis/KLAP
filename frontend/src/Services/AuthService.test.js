import { clearToken, fetchCurrentUser, getToken, login, logout, setToken } from './AuthService.js';
import { jsonResponse, mockApi } from '../testUtils.js';

const lea = { id: 3, name: 'Léa Fournier', role: 'contributor', role_label: 'Intervenant' };

describe('AuthService', () => {
  it('garde le jeton en sessionStorage par défaut', () => {
    setToken('t1');
    expect(window.sessionStorage.getItem('klap.token')).toBe('t1');
    expect(window.localStorage.getItem('klap.token')).toBeNull();
    expect(getToken()).toBe('t1');
    clearToken();
    expect(getToken()).toBeNull();
  });

  it('garde le jeton en localStorage pour « Rester connecté·e », un seul à la fois', () => {
    setToken('ancien');
    setToken('t2', true);
    expect(window.localStorage.getItem('klap.token')).toBe('t2');
    expect(window.sessionStorage.getItem('klap.token')).toBeNull();
    expect(getToken()).toBe('t2');

    // Nouvelle connexion sans la case : l'ancien jeton gardé ne doit pas survivre.
    setToken('t3');
    expect(window.localStorage.getItem('klap.token')).toBeNull();
    expect(getToken()).toBe('t3');
  });

  it('la déconnexion efface le jeton gardé', async () => {
    mockApi({ 'POST /logout': () => jsonResponse({ message: 'ok' }) });
    setToken('t4', true);
    await logout();
    expect(window.localStorage.getItem('klap.token')).toBeNull();
    expect(getToken()).toBeNull();
  });

  it('enregistre le jeton en localStorage quand la connexion le demande', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ token: 'jeton', user: lea }) });
    await login('lea@klap.demo', 'secret', { remember: true });
    expect(window.localStorage.getItem('klap.token')).toBe('jeton');
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
