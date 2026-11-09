import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

const session = (overrides = {}) => ({
  id: 7,
  session_date: '2026-11-09',
  start_time: '08:00',
  end_time: '10:00',
  session_type: 'practice_workshop',
  session_type_detail: null,
  status: 'planned',
  project: { id: 1, name: 'Arts Danse au Lycée Charles', status: 'active' },
  location: { id: 2, name: 'Grand studio' },
  referent: { id: 3, name: 'Léa Fournier' },
  sub_projects: [{ id: 11, name: 'Seconde 3' }],
  logbook_entry: null,
  ...overrides,
});

const dashboard = (overrides = {}) => ({
  to_fill: [{ ...session(), deadline: '2026-11-19' }],
  upcoming: [session({ id: 8, session_date: '2026-11-12', referent: { id: 4, name: 'Hugo Martin' } })],
  recent_entries: [
    {
      id: 31,
      status: 'submitted',
      submitted_at: '2026-11-06T18:00:00+00:00',
      author: { id: 3, name: 'Léa Fournier' },
      session: { id: 5, session_date: '2026-11-05', project: { id: 1, name: 'Arts Danse au Lycée Charles' }, sub_projects: [{ id: 11, name: 'Seconde 3' }] },
    },
  ],
  follow_up: null,
  ...overrides,
});

function renderHome(user = MOCK_USERS.contributor) {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={['/']}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

const notifications = () => jsonResponse({ data: [], unread_count: 0 });

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date(2026, 10, 9, 11, 0));
});

afterEach(() => {
  vi.useRealTimers();
});

describe('Tableau de bord', () => {
  it('montre à l’intervenante la fiche à remplir, avec son échéance', async () => {
    mockApi({ 'GET /dashboard': () => jsonResponse(dashboard()), 'GET /notifications': notifications });
    renderHome();

    const toFill = await screen.findByRole('region', { name: 'Fiches à remplir' });
    expect(within(toFill).getByText(/Aujourd’hui · 08:00 – 10:00/)).toBeInTheDocument();
    expect(within(toFill).getByText('À rendre au plus tard le jeudi 19 novembre')).toBeInTheDocument();
    expect(within(toFill).getByRole('link', { name: 'Remplir la fiche, Arts Danse au Lycée Charles, aujourd’hui' })).toHaveAttribute(
      'href',
      '/planning/seances/7/fiche',
    );
    expect(screen.getByText('Bonjour Léa')).toBeInTheDocument();
    expect(document.title).toBe('Tableau de bord · KLAP');
  });

  it('propose de reprendre un brouillon', async () => {
    mockApi({
      'GET /dashboard': () =>
        jsonResponse(dashboard({ to_fill: [{ ...session({ logbook_entry: { id: 40, status: 'draft' } }), deadline: '2026-11-19' }] })),
      'GET /notifications': notifications,
    });
    renderHome();

    expect(await screen.findByRole('link', { name: /^Reprendre la fiche/ })).toBeInTheDocument();
  });

  it('liste les prochaines séances et les dernières fiches, avec leurs liens', async () => {
    mockApi({ 'GET /dashboard': () => jsonResponse(dashboard()), 'GET /notifications': notifications });
    renderHome();

    const upcoming = await screen.findByRole('region', { name: 'Prochaines séances' });
    expect(within(upcoming).getByText(/jeudi 12 novembre/)).toBeInTheDocument();
    // L'intervenant·e d'une autre séance est nommé·e ; la sienne, non.
    expect(within(upcoming).getByText(/Hugo Martin/)).toBeInTheDocument();

    const recent = screen.getByRole('region', { name: 'Dernières fiches rendues' });
    expect(within(recent).getByRole('link', { name: /Arts Danse au Lycée Charles, fiche du jeudi 5 novembre/ })).toHaveAttribute(
      'href',
      '/fiches/31',
    );
    // Pas de suivi pour une intervenante.
    expect(screen.queryByRole('region', { name: 'Suivi' })).not.toBeInTheDocument();
  });

  it('affiche les listes vides sans erreur', async () => {
    mockApi({
      'GET /dashboard': () => jsonResponse(dashboard({ to_fill: [], upcoming: [], recent_entries: [] })),
      'GET /notifications': notifications,
    });
    renderHome();

    expect(await screen.findByText('Aucune fiche à remplir pour le moment.')).toBeInTheDocument();
    expect(screen.getByText('Aucune séance programmée à venir.')).toBeInTheDocument();
    expect(screen.getByText('Aucune fiche rendue pour le moment.')).toBeInTheDocument();
  });

  it('donne le suivi à l’administratrice', async () => {
    mockApi({
      'GET /dashboard': () =>
        jsonResponse(
          dashboard({
            to_fill: [],
            follow_up: { entries_missing: 3, entries_overdue: 1, entries_to_fill: 2, unlock_requests: 1 },
          }),
        ),
      'GET /notifications': notifications,
    });
    renderHome(MOCK_USERS.admin);

    const followUp = await screen.findByRole('region', { name: 'Suivi' });
    expect(within(followUp).getByText('Séances passées sans fiche rendue, dont 1 hors délai.')).toBeInTheDocument();
    expect(within(followUp).getByRole('link', { name: 'Voir les statistiques' })).toHaveAttribute('href', '/statistiques');
    expect(within(followUp).getByText('Demandes de réouverture')).toBeInTheDocument();
    // Elle n'a aucune fiche à remplir : la section est masquée.
    expect(screen.queryByRole('region', { name: 'Fiches à remplir' })).not.toBeInTheDocument();
  });

  it('ne montre pas les demandes de réouverture à la super-utilisatrice', async () => {
    mockApi({
      'GET /dashboard': () =>
        jsonResponse(dashboard({ follow_up: { entries_missing: 0, entries_overdue: 0, entries_to_fill: 0, unlock_requests: null } })),
      'GET /notifications': notifications,
    });
    renderHome(MOCK_USERS.super_user);

    const followUp = await screen.findByRole('region', { name: 'Suivi' });
    expect(within(followUp).getByText('Toutes les séances passées ont leur fiche.')).toBeInTheDocument();
    expect(within(followUp).queryByText('Demandes de réouverture')).not.toBeInTheDocument();
  });

  it('propose de réessayer après une erreur', async () => {
    const user = userEvent.setup();
    let calls = 0;
    mockApi({
      'GET /dashboard': () => (++calls === 1 ? jsonResponse({ message: 'Erreur' }, 500) : jsonResponse(dashboard())),
      'GET /notifications': notifications,
    });
    renderHome();

    expect(await screen.findByText('Le tableau de bord n’a pas pu être chargé. Réessayez dans un instant.')).toBeInTheDocument();
    await user.click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByRole('region', { name: 'Fiches à remplir' })).toBeInTheDocument();
  });
});
