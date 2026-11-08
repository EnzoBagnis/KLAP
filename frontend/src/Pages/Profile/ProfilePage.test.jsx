import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

const lea = { ...MOCK_USERS.contributor, phone: null, last_login_at: '2026-11-08T09:00:00+00:00' };

function renderProfile(user = lea) {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={['/profil']}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

async function fill(user, { current = 'Klap2026!', password = 'Danse#2026', confirmation = password } = {}) {
  if (current) await user.type(screen.getByLabelText(/Mot de passe actuel/), current);
  await user.type(screen.getByLabelText(/^Mot de passe\s*\*?$/), password);
  await user.type(screen.getByLabelText(/Confirmer/), confirmation);
  await user.click(screen.getByRole('button', { name: 'Changer mon mot de passe' }));
}

describe('Mon profil', () => {
  it('affiche les informations du compte, sans les modifier', () => {
    mockApi({});
    renderProfile();

    expect(screen.getByRole('heading', { name: 'Mon profil' })).toBeInTheDocument();
    expect(document.title).toBe('Mon profil · KLAP');
    expect(screen.getByText(lea.email)).toBeInTheDocument();
    expect(screen.getByText('Non renseigné')).toBeInTheDocument();
    expect(screen.getByText('08/11/2026')).toBeInTheDocument();
    expect(screen.getByText(/voyez avec l’administratrice/)).toBeInTheDocument();
  });

  it('change le mot de passe et vide le formulaire', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({
      'PUT /me/password': () => jsonResponse({ message: 'Mot de passe changé. Vos autres appareils sont déconnectés.' }),
    });
    renderProfile();

    await fill(user);

    expect(await screen.findByRole('status')).toHaveTextContent('Mot de passe changé. Vos autres appareils sont déconnectés.');
    expect(screen.getByLabelText(/Mot de passe actuel/)).toHaveValue('');
    const call = fetchMock.mock.calls.find(([, options]) => options?.method === 'PUT');
    expect(JSON.parse(call[1].body)).toEqual({ current_password: 'Klap2026!', password: 'Danse#2026', password_confirmation: 'Danse#2026' });
  });

  it('vérifie le formulaire avant l’envoi', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({});
    renderProfile();

    await fill(user, { current: '' });
    expect(screen.getByText('Saisissez votre mot de passe actuel.')).toBeInTheDocument();
    await user.type(screen.getByLabelText(/Mot de passe actuel/), 'Klap2026!');
    await user.clear(screen.getByLabelText(/Confirmer/));
    await user.type(screen.getByLabelText(/Confirmer/), 'Autre#2026');
    await user.click(screen.getByRole('button', { name: 'Changer mon mot de passe' }));
    expect(screen.getByText('La confirmation ne correspond pas au mot de passe.')).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([, options]) => options?.method === 'PUT')).toBe(false);
  });

  it('affiche le refus de l’API sous le bon champ', async () => {
    const user = userEvent.setup();
    mockApi({
      'PUT /me/password': () => jsonResponse({ message: 'Invalide', errors: { current_password: ['Le mot de passe actuel est incorrect.'] } }, 422),
    });
    renderProfile();

    await fill(user, { current: 'Mauvais#1' });

    expect(await screen.findByText('Le mot de passe actuel est incorrect.')).toBeInTheDocument();
  });

  it('ne propose pas de voir l’administratrice à l’administratrice', () => {
    mockApi({});
    renderProfile(MOCK_USERS.admin);

    expect(screen.queryByText(/voyez avec l’administratrice/)).not.toBeInTheDocument();
  });
});
