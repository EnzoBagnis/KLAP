import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

const TOKEN = 'a'.repeat(64);

function Where() {
  const location = useLocation();
  return <div data-testid="where">{location.pathname}</div>;
}

function renderAt(path, user = MOCK_USERS.admin) {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
        <Where />
      </MemoryRouter>
    </AuthProvider>,
  );
}

const bodyOf = (fetchMock, path) => {
  const call = fetchMock.mock.calls.find(([url, options]) => url.endsWith(path) && options?.method === 'POST');
  return call && JSON.parse(call[1].body);
};

const notifications = { 'GET /notifications': () => jsonResponse({ data: [], meta: { unread_count: 0 } }) };

afterEach(() => vi.unstubAllGlobals());

describe('Gestion, onglet Utilisateurs', () => {
  it('passe d’un onglet à l’autre', async () => {
    mockApi({ ...notifications, 'GET /projects': () => jsonResponse({ data: [] }) });
    const user = userEvent.setup();
    renderAt('/gestion');
    const tabs = await screen.findByRole('navigation', { name: 'Gestion' });
    expect(within(tabs).getByRole('link', { name: 'Projets' })).toHaveAttribute('aria-current', 'page');
    await user.click(within(tabs).getByRole('link', { name: 'Utilisateurs' }));
    expect(await screen.findByRole('heading', { name: 'Inviter une personne' })).toBeInTheDocument();
  });

  it('envoie l’invitation et confirme', async () => {
    const fetchMock = mockApi({
      ...notifications,
      'POST /invitations': () =>
        jsonResponse(
          { data: { full_name: 'Paul Martin', email: 'paul@example.org', role: 'contributor', expires_at: '2026-11-13T10:00:00+00:00' } },
          201,
        ),
    });
    const user = userEvent.setup();
    renderAt('/gestion/utilisateurs');
    await user.type(await screen.findByLabelText(/Nom et prénom/), ' Paul Martin ');
    await user.type(screen.getByLabelText(/Adresse mail/), 'paul@example.org');
    await user.selectOptions(screen.getByLabelText(/Rôle/), 'super_user');
    await user.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));

    expect(await screen.findByRole('status')).toHaveTextContent(
      'Invitation envoyée à Paul Martin (paul@example.org). Le lien est valable jusqu’au 13/11/2026.',
    );
    expect(bodyOf(fetchMock, '/invitations')).toEqual({ full_name: 'Paul Martin', email: 'paul@example.org', role: 'super_user' });
    expect(screen.getByLabelText(/Nom et prénom/)).toHaveValue('');
  });

  it('signale qu’une invitation en remplace une autre', async () => {
    mockApi({
      ...notifications,
      'POST /invitations': () =>
        jsonResponse(
          { data: { full_name: 'Paul Martin', email: 'paul@example.org', role: 'contributor', expires_at: '2026-11-13T10:00:00+00:00', replaced: true } },
          201,
        ),
    });
    const user = userEvent.setup();
    renderAt('/gestion/utilisateurs');
    await user.type(await screen.findByLabelText(/Nom et prénom/), 'Paul Martin');
    await user.type(screen.getByLabelText(/Adresse mail/), 'paul@example.org');
    await user.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));
    expect(await screen.findByRole('status')).toHaveTextContent('Elle remplace l’invitation précédente');
  });

  it('vérifie les champs avant l’envoi', async () => {
    const fetchMock = mockApi(notifications);
    const user = userEvent.setup();
    renderAt('/gestion/utilisateurs');
    await user.type(await screen.findByLabelText(/Adresse mail/), 'paul');
    await user.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));
    expect(screen.getByText('Indiquez le nom de la personne invitée.')).toBeInTheDocument();
    expect(screen.getByText('L’adresse mail n’est pas valide.')).toBeInTheDocument();
    expect(bodyOf(fetchMock, '/invitations')).toBeUndefined();
  });

  it('affiche les refus de l’API sous le bon champ', async () => {
    mockApi({
      ...notifications,
      'POST /invitations': () => jsonResponse({ message: 'Invalid', errors: { email: ['Un compte existe déjà avec cette adresse.'] } }, 422),
    });
    const user = userEvent.setup();
    renderAt('/gestion/utilisateurs');
    await user.type(await screen.findByLabelText(/Nom et prénom/), 'Paul Martin');
    await user.type(screen.getByLabelText(/Adresse mail/), 'paul@example.org');
    await user.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));
    expect(await screen.findByText('Un compte existe déjà avec cette adresse.')).toBeInTheDocument();
    expect(screen.getByLabelText(/Adresse mail/)).toHaveAttribute('aria-invalid', 'true');
  });

  it('dit quand le mail n’est pas parti', async () => {
    mockApi({
      ...notifications,
      'POST /invitations': () => jsonResponse({ message: 'Le mail d’invitation n’a pas pu être envoyé. Réessayez dans un instant.' }, 503),
    });
    const user = userEvent.setup();
    renderAt('/gestion/utilisateurs');
    await user.type(await screen.findByLabelText(/Nom et prénom/), 'Paul Martin');
    await user.type(screen.getByLabelText(/Adresse mail/), 'paul@example.org');
    await user.click(screen.getByRole('button', { name: 'Envoyer l’invitation' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('n’a pas pu être envoyé');
  });

  it('n’est pas accessible à la super-utilisatrice', async () => {
    mockApi(notifications);
    renderAt('/gestion/utilisateurs', MOCK_USERS.super_user);
    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent(/^\/$/));
  });
});

describe('Activation du compte', () => {
  const invitation = { full_name: 'Paul Martin', email: 'paul@example.org', role: 'contributor', expires_at: '2026-11-13T10:00:00+00:00' };

  function renderActivation() {
    return render(
      <AuthProvider initialUser={null}>
        <MemoryRouter initialEntries={[`/activation/${TOKEN}`]}>
          <AppRouter />
          <Where />
        </MemoryRouter>
      </AuthProvider>,
    );
  }

  it('affiche le nom et l’adresse de l’invitation, sans connexion', async () => {
    const fetchMock = mockApi({ [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }) });
    renderActivation();
    expect(await screen.findByText('Bienvenue Paul Martin. Choisissez votre mot de passe.')).toBeInTheDocument();
    expect(screen.getByText('paul@example.org')).toBeInTheDocument();
    expect(fetchMock.mock.calls[0][1].headers.Authorization).toBeUndefined();
  });

  it('suit les règles du mot de passe en direct', async () => {
    mockApi({ [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }) });
    const user = userEvent.setup();
    renderActivation();
    const rules = await screen.findByRole('list', { name: 'Le mot de passe doit contenir' });
    expect(within(rules).getByText(/un chiffre/)).toHaveTextContent('manquant');
    await user.type(screen.getByLabelText(/^Mot de passe/), 'Danse2026!');
    expect(within(rules).getAllByText(/respecté/)).toHaveLength(4);
  });

  it('refuse une confirmation différente sans appeler l’API', async () => {
    const fetchMock = mockApi({ [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }) });
    const user = userEvent.setup();
    renderActivation();
    await user.type(await screen.findByLabelText(/^Mot de passe/), 'Danse2026!');
    await user.type(screen.getByLabelText(/Confirmer/), 'Danse2026?');
    await user.click(screen.getByRole('button', { name: 'Activer mon compte' }));
    expect(screen.getByText('La confirmation ne correspond pas au mot de passe.')).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('active puis renvoie vers la connexion avec l’adresse remplie', async () => {
    const fetchMock = mockApi({
      [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }),
      [`POST /invitations/${TOKEN}/activate`]: () => jsonResponse({ data: { email: 'paul@example.org', name: 'Paul Martin' } }, 201),
    });
    const user = userEvent.setup();
    renderActivation();
    await user.type(await screen.findByLabelText(/^Mot de passe/), 'Danse2026!');
    await user.type(screen.getByLabelText(/Confirmer/), 'Danse2026!');
    await user.click(screen.getByRole('button', { name: 'Activer mon compte' }));

    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('/connexion'));
    expect(screen.getByRole('status')).toHaveTextContent('Votre compte est activé.');
    expect(screen.getByLabelText(/adresse e-mail/i)).toHaveValue('paul@example.org');
    expect(bodyOf(fetchMock, '/activate')).toEqual({ password: 'Danse2026!', password_confirmation: 'Danse2026!' });
  });

  it.each([
    [410, 'Ce lien a expiré. Demandez une nouvelle invitation à l’administratrice.'],
    [404, 'Ce lien d’invitation n’existe pas. Vérifiez le lien reçu par mail.'],
  ])('explique un lien inutilisable (%i) sans formulaire', async (status, message) => {
    mockApi({ [`GET /invitations/${TOKEN}`]: () => jsonResponse({ message }, status) });
    renderActivation();
    expect(await screen.findByRole('alert')).toHaveTextContent(message);
    expect(screen.queryByLabelText(/^Mot de passe/)).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Aller à la connexion' })).toBeInTheDocument();
  });

  it('respecte le délai annoncé après trop de tentatives', async () => {
    mockApi({ [`GET /invitations/${TOKEN}`]: () => jsonResponse({ message: 'Too Many Attempts.' }, 429, { 'Retry-After': '42' }) });
    renderActivation();
    expect(await screen.findByRole('alert')).toHaveTextContent('Réessayez dans 42 secondes.');
  });

  it.each([
    [409, 'Un compte existe déjà pour cette invitation. Connectez-vous avec votre adresse.'],
    [410, 'Ce lien a déjà servi : votre compte est activé, connectez-vous.'],
  ])('affiche le refus de l’activation (%i)', async (status, message) => {
    mockApi({
      [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }),
      [`POST /invitations/${TOKEN}/activate`]: () => jsonResponse({ message }, status),
    });
    const user = userEvent.setup();
    renderActivation();
    await user.type(await screen.findByLabelText(/^Mot de passe/), 'Danse2026!');
    await user.type(screen.getByLabelText(/Confirmer/), 'Danse2026!');
    await user.click(screen.getByRole('button', { name: 'Activer mon compte' }));
    expect(await screen.findByRole('alert')).toHaveTextContent(message);
  });

  it('compte une lettre accentuée comme une lettre, pas comme un caractère spécial', async () => {
    mockApi({ [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }) });
    const user = userEvent.setup();
    renderActivation();
    await user.type(await screen.findByLabelText(/^Mot de passe/), 'Danseé2026');
    const rules = screen.getByRole('list', { name: 'Le mot de passe doit contenir' });
    expect(within(rules).getByText(/caractère spécial/)).toHaveTextContent('manquant');
  });

  it('relie le champ à ses règles et à son erreur', async () => {
    mockApi({ [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }) });
    const user = userEvent.setup();
    renderActivation();
    const field = await screen.findByLabelText(/^Mot de passe/);
    expect(field).toHaveAccessibleDescription(/au moins 8 caractères/);
    await user.type(field, 'court');
    await user.click(screen.getByRole('button', { name: 'Activer mon compte' }));
    expect(field).toHaveAccessibleDescription(/ne respecte pas toutes les règles.*au moins 8 caractères/);
  });

  it('affiche le refus de l’API sur le mot de passe', async () => {
    mockApi({
      [`GET /invitations/${TOKEN}`]: () => jsonResponse({ data: invitation }),
      [`POST /invitations/${TOKEN}/activate`]: () =>
        jsonResponse({ message: 'Invalid', errors: { password: ['Ce mot de passe est trop courant.'] } }, 422),
    });
    const user = userEvent.setup();
    renderActivation();
    await user.type(await screen.findByLabelText(/^Mot de passe/), 'Danse2026!');
    await user.type(screen.getByLabelText(/Confirmer/), 'Danse2026!');
    await user.click(screen.getByRole('button', { name: 'Activer mon compte' }));
    expect(await screen.findByText('Ce mot de passe est trop courant.')).toBeInTheDocument();
  });
});
