import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { getToken, setToken } from '../../Services/AuthService.js';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

function renderApp(path = '/connexion') {
  return render(
    <AuthProvider>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

async function submit(email, password) {
  const user = userEvent.setup();
  if (email) await user.type(screen.getByLabelText(/Adresse e-mail/), email);
  if (password) await user.type(screen.getByLabelText(/Mot de passe/), password);
  await user.click(screen.getByRole('button', { name: 'Se connecter' }));
  return user;
}

describe('Page de connexion', () => {
  it('connecte et ouvre le tableau de bord avec le menu du rôle', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ token: 'jeton', user: MOCK_USERS.contributor }) });
    renderApp();

    await submit('lea.fournier@klap.demo', 'Klap2026!');

    expect(await screen.findByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
    expect(screen.getByText('Léa Fournier')).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: /Gestion/ })).not.toBeInTheDocument();
    expect(getToken()).toBe('jeton');
    // Case décochée par défaut : rien ne survit à la fermeture du navigateur.
    expect(window.localStorage.getItem('klap.token')).toBeNull();
  });

  it('ferme la session avec le navigateur, sauf si « Rester connecté·e » est coché', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ token: 'jeton', user: MOCK_USERS.contributor }) });
    renderApp();

    const remember = screen.getByRole('checkbox', { name: 'Rester connecté·e sur cet appareil' });
    expect(remember).not.toBeChecked();
    expect(remember).toHaveAccessibleDescription(/ordinateur partagé/);
    const user = userEvent.setup();
    await user.click(remember);
    await submit('lea.fournier@klap.demo', 'Klap2026!');

    expect(await screen.findByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
    expect(window.localStorage.getItem('klap.token')).toBe('jeton');
    expect(window.sessionStorage.getItem('klap.token')).toBeNull();
  });

  it('affiche le message de l’API pour des identifiants faux et vide le mot de passe', async () => {
    mockApi({
      'POST /login': () =>
        jsonResponse({ errors: { email: ['L’adresse e-mail ou le mot de passe est incorrect.'] } }, 422),
    });
    renderApp();

    await submit('lea.fournier@klap.demo', 'mauvais');

    expect(await screen.findByRole('alert')).toHaveTextContent('L’adresse e-mail ou le mot de passe est incorrect.');
    expect(screen.getByLabelText(/Mot de passe/)).toHaveValue('');
    expect(screen.getByLabelText(/Adresse e-mail/)).toHaveValue('lea.fournier@klap.demo');
  });

  it('affiche le message de l’API pour un compte désactivé', async () => {
    mockApi({
      'POST /login': () =>
        jsonResponse({ errors: { email: ['Ce compte a été désactivé. Contactez l’administratrice.'] } }, 422),
    });
    renderApp();

    await submit('ancien@klap.demo', 'Klap2026!');

    expect(await screen.findByRole('alert')).toHaveTextContent('Ce compte a été désactivé.');
  });

  it('annonce le délai après trop de tentatives', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ message: 'Too Many Attempts.' }, 429, { 'Retry-After': '37' }) });
    renderApp();

    await submit('lea.fournier@klap.demo', 'x');

    expect(await screen.findByRole('alert')).toHaveTextContent('Trop de tentatives. Réessayez dans 37 secondes.');
  });

  it('prévient quand le serveur est injoignable', async () => {
    vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new TypeError('Failed to fetch')));
    renderApp();

    await submit('lea.fournier@klap.demo', 'x');

    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de joindre le serveur.');
  });

  it('affiche un message générique sur une erreur serveur', async () => {
    mockApi({ 'POST /login': () => jsonResponse({ message: 'Server Error' }, 500) });
    renderApp();

    await submit('lea.fournier@klap.demo', 'x');

    expect(await screen.findByRole('alert')).toHaveTextContent('Une erreur est survenue. Réessayez plus tard.');
  });

  it('contrôle les champs avant tout appel', async () => {
    const fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    renderApp();

    await submit('', '');
    expect(screen.getByText('Saisissez votre adresse e-mail.')).toBeInTheDocument();
    expect(screen.getByText('Saisissez votre mot de passe.')).toBeInTheDocument();

    expect(screen.getByLabelText(/Adresse e-mail/)).toHaveFocus();
    expect(screen.getByLabelText(/Adresse e-mail/)).toHaveAttribute('aria-invalid', 'true');

    await submit('pas-une-adresse', 'x');
    expect(screen.getByText('Cette adresse e-mail n’est pas valide.')).toBeInTheDocument();

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('désactive le bouton pendant l’envoi', async () => {
    let release;
    mockApi({ 'POST /login': () => new Promise((resolve) => (release = resolve)) });
    renderApp();

    await submit('lea.fournier@klap.demo', 'Klap2026!');

    expect(screen.getByRole('button', { name: 'Connexion…' })).toBeDisabled();
    release(jsonResponse({ token: 'jeton', user: MOCK_USERS.contributor }));
    expect(await screen.findByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
  });

  it('renvoie à l’accueil une personne déjà connectée', async () => {
    setToken('jeton');
    mockApi({ 'GET /me': () => jsonResponse({ user: MOCK_USERS.admin }) });
    renderApp('/connexion');

    expect(await screen.findByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
  });

  it('garde la session au rechargement d’une page protégée', async () => {
    setToken('jeton');
    mockApi({ 'GET /me': () => jsonResponse({ user: MOCK_USERS.admin }) });
    renderApp('/planning');

    expect(screen.getByRole('status')).toHaveTextContent('Vérification de la session');
    expect(await screen.findByRole('heading', { name: 'Planning' })).toBeInTheDocument();
  });

  it('déconnecte : révoque le jeton et revient à la connexion', async () => {
    setToken('jeton');
    const fetchMock = mockApi({
      'GET /me': () => jsonResponse({ user: MOCK_USERS.admin }),
      'POST /logout': () => jsonResponse({ message: 'Déconnecté.' }),
    });
    renderApp('/');
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Se déconnecter' }));

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument();
    expect(getToken()).toBeNull();
    expect(fetchMock.mock.calls.map(([url, o]) => `${o?.method ?? 'GET'} ${new URL(url).pathname}`)).toContain(
      'POST /api/logout',
    );
  });
});
