import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

const admin = MOCK_USERS.admin;
const ACCOUNTS = [
  { id: admin.id, name: admin.name, email: admin.email, role: 'admin', role_label: 'Administratrice', is_active: true, last_login_at: '2026-11-08T09:00:00+00:00' },
  { id: 41, name: 'Léa Fournier', email: 'lea@klap.demo', role: 'contributor', role_label: 'Intervenant', is_active: true, last_login_at: null },
  { id: 42, name: 'Paul Martin', email: 'paul@klap.demo', role: 'contributor', role_label: 'Intervenant', is_active: false, last_login_at: null },
];

function renderPage() {
  return render(
    <AuthProvider initialUser={admin}>
      <MemoryRouter initialEntries={['/gestion/utilisateurs']}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

// Nom cherché dans la liste des comptes : celui de l'administratrice figure aussi dans l'en-tête.
const rowOf = (name) => within(screen.getByRole('region', { name: 'Comptes' })).getByText(name).closest('li');

describe('Gestion, liste des comptes', () => {
  afterEach(() => vi.restoreAllMocks());

  it('liste les comptes, sans bouton sur le sien', async () => {
    mockApi({ 'GET /accounts': () => jsonResponse({ data: ACCOUNTS }) });
    renderPage();

    expect(await screen.findByText('Léa Fournier')).toBeInTheDocument();
    expect(within(rowOf('Léa Fournier')).getByRole('button', { name: 'Désactiver' })).toBeInTheDocument();
    expect(within(rowOf('Paul Martin')).getByText('Désactivé')).toBeInTheDocument();
    expect(within(rowOf('Paul Martin')).getByRole('button', { name: 'Réactiver' })).toBeInTheDocument();
    expect(within(rowOf(admin.name)).queryByRole('button')).not.toBeInTheDocument();
    expect(within(rowOf('Léa Fournier')).getByText(/jamais connecté·e/)).toBeInTheDocument();
  });

  it('désactive un compte après confirmation et met la ligne à jour', async () => {
    const user = userEvent.setup();
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
    const fetchMock = mockApi({
      'GET /accounts': () => jsonResponse({ data: ACCOUNTS }),
      'PATCH /accounts/41': () => jsonResponse({ data: { ...ACCOUNTS[1], is_active: false } }),
    });
    renderPage();

    await user.click(within(await screen.findByText('Léa Fournier').then((node) => node.closest('li'))).getByRole('button', { name: 'Désactiver' }));

    expect(confirm).toHaveBeenCalledWith(expect.stringContaining('Ses fiches sont conservées.'));
    expect(await screen.findByRole('status')).toHaveTextContent('Compte de Léa Fournier désactivé.');
    expect(within(rowOf('Léa Fournier')).getByRole('button', { name: 'Réactiver' })).toBeInTheDocument();
    const patch = fetchMock.mock.calls.find(([, options]) => options?.method === 'PATCH');
    expect(JSON.parse(patch[1].body)).toEqual({ is_active: false });
  });

  it('ne fait rien si la confirmation est refusée', async () => {
    const user = userEvent.setup();
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    const fetchMock = mockApi({ 'GET /accounts': () => jsonResponse({ data: ACCOUNTS }) });
    renderPage();

    await user.click(within(await screen.findByText('Paul Martin').then((node) => node.closest('li'))).getByRole('button', { name: 'Réactiver' }));

    expect(fetchMock.mock.calls.some(([, options]) => options?.method === 'PATCH')).toBe(false);
  });

  it('affiche le refus de l’API', async () => {
    const user = userEvent.setup();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    mockApi({
      'GET /accounts': () => jsonResponse({ data: ACCOUNTS }),
      'PATCH /accounts/41': () => jsonResponse({ message: 'Invalide', errors: { is_active: ['Vous ne pouvez pas désactiver votre propre compte.'] } }, 422),
    });
    renderPage();

    await user.click(within(await screen.findByText('Léa Fournier').then((node) => node.closest('li'))).getByRole('button', { name: 'Désactiver' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Vous ne pouvez pas désactiver votre propre compte.');
  });

  it('propose de réessayer si la liste ne charge pas', async () => {
    mockApi({ 'GET /accounts': () => jsonResponse({ message: 'Erreur' }, 500) });
    renderPage();

    expect(await screen.findByText('La liste des comptes n’a pas pu être chargée.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Réessayer' })).toBeInTheDocument();
  });
});
