import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

const notification = (overrides = {}) => ({
  id: 1,
  type: 'entry_submitted',
  message: 'Léa Fournier a rempli la fiche du 5 octobre (Arts Danse).',
  logbook_entry_id: 7,
  read_at: null,
  created_at: '2026-10-05T16:00:00+00:00',
  ...overrides,
});

const list = (notifications, unread = notifications.filter((n) => n.read_at === null).length) =>
  jsonResponse({ data: notifications, meta: { unread_count: unread } });

function Where() {
  const location = useLocation();
  return <output data-testid="where">{location.pathname}</output>;
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

const bell = () => within(screen.getByRole('banner')).getByRole('link', { name: /^Notifications/ });

afterEach(() => vi.unstubAllGlobals());

describe('Cloche', () => {
  it('annonce le nombre de non lues de l’API', async () => {
    // La cloche ne demande que le nombre de non-lues (#227), pas la liste.
    mockApi({
      'GET /notifications/unread-count': () => jsonResponse({ data: { unread_count: 2 } }),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/projets');
    await waitFor(() => expect(bell()).toHaveAccessibleName('Notifications, 2 non lues'));
  });

  it('reste neutre si le compteur ne se charge pas', async () => {
    mockApi({
      'GET /notifications/unread-count': () => jsonResponse({ message: 'Erreur' }, 500),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/projets');
    await screen.findByRole('heading', { name: 'Projets' });
    expect(bell()).toHaveAccessibleName('Notifications');
  });
});

describe('Rechargement du compteur', () => {
  it('relit les non lues à chaque changement de page', async () => {
    let unread = 1;
    const fetchMock = mockApi({
      'GET /notifications/unread-count': () => jsonResponse({ data: { unread_count: unread } }),
      'GET /projects': () => jsonResponse({ data: [] }),
      'GET /sessions': () => jsonResponse({ data: [] }),
    });
    const user = userEvent.setup();
    renderAt('/projets');
    await waitFor(() => expect(bell()).toHaveAccessibleName('Notifications, 1 non lue'));

    unread = 3;
    await user.click(screen.getAllByRole('link', { name: /Planning/ })[0]);
    await waitFor(() => expect(bell()).toHaveAccessibleName('Notifications, 3 non lues'));
    expect(fetchMock.mock.calls.filter(([url]) => url.includes('/notifications/unread-count')).length).toBeGreaterThanOrEqual(2);
    // La liste complète n'est jamais chargée hors de la page Notifications.
    expect(fetchMock.mock.calls.filter(([url]) => new URL(url).pathname.endsWith('/notifications'))).toHaveLength(0);
  });
});

describe('Page Notifications', () => {
  it('la liste se met à jour quand la cloche voit une nouvelle notification (#231)', async () => {
    let unread = 1;
    const first = notification();
    const second = notification({ id: 2, message: 'Hugo Martin a rempli la fiche du 6 octobre (BRAISES).' });
    const fetchMock = mockApi({
      'GET /notifications/unread-count': () => jsonResponse({ data: { unread_count: unread } }),
      'GET /notifications': () => list(unread === 1 ? [first] : [second, first], unread),
    });
    vi.spyOn(document, 'visibilityState', 'get').mockReturnValue('visible');
    renderAt('/notifications');
    expect(await screen.findByText(first.message)).toBeInTheDocument();
    const listCalls = () => fetchMock.mock.calls.filter(([url]) => new URL(url).pathname.endsWith('/notifications')).length;
    const before = listCalls();

    // Une fiche est rendue ailleurs ; la cloche se recharge (ici : retour sur l'onglet).
    unread = 2;
    document.dispatchEvent(new Event('visibilitychange'));

    expect(await screen.findByText(second.message)).toBeInTheDocument();
    expect(screen.getByText(first.message)).toBeInTheDocument(); // pas d'écran d'attente entre-temps
    expect(listCalls()).toBe(before + 1);
  });

  it('marquer comme lu ne recharge pas la liste', async () => {
    const fetchMock = mockApi({
      'GET /notifications/unread-count': () => jsonResponse({ data: { unread_count: 1 } }),
      'GET /notifications': () => list([notification({ logbook_entry_id: null })], 1),
      'POST /notifications/read-all': () => jsonResponse({ meta: { marked: 1, unread_count: 0 } }),
    });
    const user = userEvent.setup();
    renderAt('/notifications');
    await screen.findByText('1 non lue');
    const listCalls = () => fetchMock.mock.calls.filter(([url]) => new URL(url).pathname.endsWith('/notifications')).length;
    const before = listCalls();
    await user.click(screen.getByRole('button', { name: /Tout marquer comme lu/ }));
    await screen.findByText('Tout est lu');
    expect(listCalls()).toBe(before);
  });

  it('compte aussi les non lues au-delà des 30 affichées', async () => {
    mockApi({
      'GET /notifications': () => list([notification({ read_at: '2026-10-06T08:00:00+00:00' })], 2),
      'POST /notifications/read-all': () => jsonResponse({ meta: { marked: 2, unread_count: 0 } }),
    });
    const user = userEvent.setup();
    renderAt('/notifications');
    expect(await screen.findByText('2 non lues')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Tout marquer comme lu' }));
    expect(await screen.findByText('Tout est lu')).toBeInTheDocument();
  });

  it('n’envoie qu’une lecture sur un double clic', async () => {
    const fetchMock = mockApi({
      'GET /notifications': () => list([notification({ logbook_entry_id: null })]),
      'PATCH /notifications/1/read': () =>
        jsonResponse({ data: notification({ logbook_entry_id: null, read_at: '2026-10-06T08:00:00+00:00' }) }),
    });
    const user = userEvent.setup();
    renderAt('/notifications');
    const button = await screen.findByRole('button', { name: /Marquer comme lue/ });
    await user.dblClick(button);
    expect(await screen.findByText('Tout est lu')).toBeInTheDocument();
    expect(fetchMock.mock.calls.filter(([url]) => url.endsWith('/read'))).toHaveLength(1);
  });

  it('liste les notifications et distingue les non lues', async () => {
    mockApi({
      'GET /notifications': () =>
        list([notification(), notification({ id: 2, message: 'Rappel déjà lu', logbook_entry_id: null, read_at: '2026-10-06T08:00:00+00:00' })]),
    });
    renderAt('/notifications');
    expect(await screen.findByText('1 non lue')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Non lue.*Léa Fournier a rempli la fiche/ })).toBeInTheDocument();
    expect(screen.getByText('Rappel déjà lu')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Rappel déjà lu/ })).not.toBeInTheDocument();
  });

  it('marque comme lue puis ouvre la fiche', async () => {
    const fetchMock = mockApi({
      'GET /notifications': () => list([notification()]),
      'PATCH /notifications/1/read': () => jsonResponse({ data: notification({ read_at: '2026-10-06T08:00:00+00:00' }) }),
      'GET /logbook-entries/7': () => jsonResponse({ message: 'Not found' }, 404),
    });
    const user = userEvent.setup();
    renderAt('/notifications');
    await user.click(await screen.findByRole('button', { name: /Ouvrir la fiche/ }));
    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('/fiches/7'));
    expect(fetchMock.mock.calls.some(([url, options]) => url.endsWith('/notifications/1/read') && options.method === 'PATCH')).toBe(true);
  });

  it('ouvre la fiche même si la lecture échoue', async () => {
    mockApi({
      'GET /notifications': () => list([notification()]),
      'PATCH /notifications/1/read': () => jsonResponse({ message: 'Erreur' }, 500),
      'GET /logbook-entries/7': () => jsonResponse({ message: 'Not found' }, 404),
    });
    const user = userEvent.setup();
    renderAt('/notifications');
    await user.click(await screen.findByRole('button', { name: /Ouvrir la fiche/ }));
    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('/fiches/7'));
  });

  it('tout marquer comme lu remet la cloche à zéro', async () => {
    mockApi({
      'GET /notifications': () => list([notification(), notification({ id: 2 })]),
      'POST /notifications/read-all': () => jsonResponse({ meta: { marked: 2, unread_count: 0 } }),
    });
    const user = userEvent.setup();
    renderAt('/notifications');
    await user.click(await screen.findByRole('button', { name: 'Tout marquer comme lu' }));
    expect(await screen.findByText('Tout est lu')).toBeInTheDocument();
    expect(bell()).toHaveAccessibleName('Notifications');
    expect(screen.queryByRole('button', { name: 'Tout marquer comme lu' })).not.toBeInTheDocument();
  });

  it('signale un échec de « tout marquer comme lu »', async () => {
    mockApi({
      'GET /notifications': () => list([notification()]),
      'POST /notifications/read-all': () => jsonResponse({ message: 'Erreur' }, 500),
    });
    const user = userEvent.setup();
    renderAt('/notifications');
    await user.click(await screen.findByRole('button', { name: 'Tout marquer comme lu' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('n’ont pas pu être marquées');
    expect(screen.getByText('1 non lue')).toBeInTheDocument();
  });

  it('indique quand il n’y a rien', async () => {
    mockApi({ 'GET /notifications': () => list([]) });
    renderAt('/notifications', MOCK_USERS.contributor);
    expect(await screen.findByText('Aucune notification pour le moment.')).toBeInTheDocument();
  });

  it('propose de réessayer après une erreur réseau', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => {
      throw new TypeError('Failed to fetch');
    }));
    renderAt('/notifications');
    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de joindre le serveur');
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
  });

  it('déconnecte quand la session a expiré', async () => {
    mockApi({ 'GET /notifications': () => jsonResponse({ message: 'Unauthenticated.' }, 401) });
    renderAt('/notifications');
    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('/connexion'));
  });
});
