import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { jsonResponse, mockApi } from '../../testUtils.js';

const SENT = 'Si un compte actif correspond à cette adresse, un mail vient de lui être envoyé. Pensez à regarder dans les indésirables.';

function renderApp(path) {
  return render(
    <AuthProvider>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

const bodyOf = (fetchMock, path) => {
  const call = fetchMock.mock.calls.find(([url]) => new URL(url).pathname === `/api${path}`);
  return call ? JSON.parse(call[1].body) : undefined;
};

describe('Mot de passe oublié', () => {
  it('se rejoint depuis la page de connexion', async () => {
    const user = userEvent.setup();
    renderApp('/connexion');

    await user.click(screen.getByRole('link', { name: 'Mot de passe oublié ?' }));

    expect(screen.getByRole('heading', { name: 'Mot de passe oublié' })).toBeInTheDocument();
    expect(document.title).toBe('Mot de passe oublié · KLAP');
  });

  it('demande un lien et affiche la même réponse que l’adresse existe ou non', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({ 'POST /forgot-password': () => jsonResponse({ message: SENT }) });
    renderApp('/mot-de-passe-oublie');

    await user.type(screen.getByLabelText(/Adresse e-mail/), ' lea@klap.demo ');
    await user.click(screen.getByRole('button', { name: 'Recevoir un lien' }));

    expect(await screen.findByRole('status')).toHaveTextContent(SENT);
    expect(bodyOf(fetchMock, '/forgot-password')).toEqual({ email: 'lea@klap.demo' });
    expect(screen.queryByRole('button', { name: 'Recevoir un lien' })).not.toBeInTheDocument();
  });

  it('vérifie l’adresse avant l’envoi', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({});
    renderApp('/mot-de-passe-oublie');

    await user.click(screen.getByRole('button', { name: 'Recevoir un lien' }));
    expect(screen.getByText('Indiquez votre adresse mail.')).toBeInTheDocument();
    await user.type(screen.getByLabelText(/Adresse e-mail/), 'pas-une-adresse');
    await user.click(screen.getByRole('button', { name: 'Recevoir un lien' }));
    expect(screen.getByText('L’adresse mail n’est pas valide.')).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('signale une limite de demandes atteinte', async () => {
    const user = userEvent.setup();
    mockApi({ 'POST /forgot-password': () => jsonResponse({ message: 'Too Many Attempts.' }, 429, { 'Retry-After': '42' }) });
    renderApp('/mot-de-passe-oublie');

    await user.type(screen.getByLabelText(/Adresse e-mail/), 'lea@klap.demo');
    await user.click(screen.getByRole('button', { name: 'Recevoir un lien' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Trop de demandes. Réessayez dans 42 secondes.');
  });
});

describe('Nouveau mot de passe', () => {
  const path = '/reinitialisation/jeton123?email=lea%40klap.demo';

  it('change le mot de passe puis renvoie à la connexion avec l’adresse préremplie', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({ 'POST /reset-password': () => jsonResponse({ message: 'Votre mot de passe est changé.' }) });
    renderApp(path);

    expect(screen.getByText('Pour le compte lea@klap.demo.')).toBeInTheDocument();
    await user.type(screen.getByLabelText(/^Mot de passe/), 'Danse#2026');
    await user.type(screen.getByLabelText(/Confirmer/), 'Danse#2026');
    await user.click(screen.getByRole('button', { name: 'Changer mon mot de passe' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Votre mot de passe est changé. Connectez-vous avec le nouveau.');
    expect(screen.getByLabelText(/Adresse e-mail/)).toHaveValue('lea@klap.demo');
    expect(bodyOf(fetchMock, '/reset-password')).toEqual({
      token: 'jeton123', email: 'lea@klap.demo', password: 'Danse#2026', password_confirmation: 'Danse#2026',
    });
  });

  it('applique les règles du mot de passe avant l’envoi', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({});
    renderApp(path);

    await user.type(screen.getByLabelText(/^Mot de passe/), 'faible');
    await user.click(screen.getByRole('button', { name: 'Changer mon mot de passe' }));
    expect(screen.getByText('Le mot de passe ne respecte pas toutes les règles.')).toBeInTheDocument();
    expect(screen.getByRole('list', { name: 'Le mot de passe doit contenir' })).toHaveTextContent('au moins 8 caractères : manquant');
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('propose un nouveau lien quand celui-ci n’est plus valable', async () => {
    const user = userEvent.setup();
    mockApi({
      'POST /reset-password': () =>
        jsonResponse({ message: 'Invalide', errors: { token: ['Ce lien n’est plus valable : demandez-en un nouveau.'] } }, 422),
    });
    renderApp(path);

    await user.type(screen.getByLabelText(/^Mot de passe/), 'Danse#2026');
    await user.type(screen.getByLabelText(/Confirmer/), 'Danse#2026');
    await user.click(screen.getByRole('button', { name: 'Changer mon mot de passe' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Ce lien n’est plus valable : demandez-en un nouveau.');
    expect(screen.getByRole('link', { name: 'Demander un nouveau lien' })).toHaveAttribute('href', '/mot-de-passe-oublie');
  });
});
