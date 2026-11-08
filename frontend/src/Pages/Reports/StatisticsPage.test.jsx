import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation, useNavigate } from 'react-router-dom';
import { afterEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { getStatistics } from '../../Services/StatisticsService.js';
import { MOCK_USERS, jsonResponse, mockApi, screenCalls } from '../../testUtils.js';

const averages = (engagement, expression, autonomy) => ({
  avg_engagement: engagement,
  avg_expression: expression,
  avg_autonomy: autonomy,
});

const group = (overrides) => ({
  age_range: '15-16 ans',
  city: 'Marseille',
  postal_code: '13003',
  expected_headcount: null,
  average_attendees: null,
  attendance_rate: null,
  ...overrides,
});

const statistics = (overrides = {}) => ({
  filters: { project_id: null, from: null, to: null },
  totals: {
    participants: 74,
    sessions_documented: 9,
    entries_missing: 3,
    entries_overdue: 2,
    entries_to_fill: 1,
    evaluations: 8,
    ...averages(3.5, 4, 2.75),
  },
  by_city: [
    { city: 'Marseille', postal_code: '13003', participants: 59, groups: 2 },
    { city: null, postal_code: null, participants: 15, groups: 1 },
  ],
  by_age_range: [{ age_range: '15-16 ans', participants: 59, groups: 2 }],
  projects: [
    {
      id: 1,
      name: 'Arts Danse au Lycée Charles',
      participants: 59,
      sessions_documented: 6,
      entries_missing: 3,
      entries_overdue: 2,
      entries_to_fill: 1,
      evaluations: 6,
      ...averages(3.5, 4, 2.75),
      sub_projects: [
        group({ id: 11, name: 'Seconde 3', participants: 24, sessions_documented: 4, average_attendees: 21.5, attendance_rate: 86, expected_headcount: 25 }),
        group({ id: 12, name: 'Première 1', participants: 35, sessions_documented: 3, average_attendees: 30, expected_headcount: null }),
      ],
    },
    {
      id: 2,
      name: 'Atelier Belle de Mai',
      participants: 15,
      sessions_documented: 3,
      entries_missing: 0,
      entries_overdue: 0,
      entries_to_fill: 0,
      evaluations: 0,
      ...averages(null, null, null),
      sub_projects: [],
    },
  ],
  ...overrides,
});

const projects = [
  { id: 1, name: 'Arts Danse au Lycée Charles' },
  { id: 2, name: 'Atelier Belle de Mai' },
];

function Where() {
  const location = useLocation();
  const navigate = useNavigate();
  return (
    <>
      <output data-testid="where">{location.pathname + location.search}</output>
      <button type="button" onClick={() => navigate(-1)}>
        Page précédente
      </button>
    </>
  );
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

const queryOf = (fetchMock) => {
  const call = fetchMock.mock.calls.filter(([url]) => url.includes('/statistics')).at(-1);
  return Object.fromEntries(new URL(call[0]).searchParams);
};

afterEach(() => {
  vi.unstubAllGlobals();
});

describe('Service des statistiques', () => {
  it('n’envoie que les filtres renseignés', async () => {
    const fetchMock = mockApi({ 'GET /statistics': () => jsonResponse({ data: statistics() }) });
    await getStatistics({ projectId: '', from: '2026-09-01', to: '' });
    expect(queryOf(fetchMock)).toEqual({ from: '2026-09-01' });
    await getStatistics();
    expect(fetchMock.mock.calls.at(-1)[0]).toMatch(/\/statistics$/);
  });
});

describe('Page Statistiques', () => {
  it('affiche les totaux, les moyennes et le détail par projet et groupe', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');

    const totals = await screen.findByRole('list', { name: 'Totaux' });
    expect(within(totals).getByText('Participants').nextSibling).toHaveTextContent('74');
    expect(within(totals).getByText('Fiches en retard').nextSibling).toHaveTextContent('3');
    expect(within(totals).getByText(/dont 2 hors délai/)).toBeInTheDocument();
    expect(within(totals).queryByText('Fiches notées')).not.toBeInTheDocument();
    expect(screen.getAllByText('3,5 / 5').length).toBeGreaterThan(0);

    const table = screen.getByRole('table', { name: 'Par projet' });
    const charles = within(table).getByRole('row', { name: /Arts Danse au Lycée Charles/ });
    expect(charles).toHaveTextContent('59');
    expect(within(table).getByRole('row', { name: /Seconde 3/ })).toHaveTextContent('24');
  });

  it('affiche la provenance, l’âge et l’assiduité des groupes', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');

    const origin = await screen.findByRole('table', { name: 'Provenance' });
    expect(within(origin).getByRole('row', { name: /Marseille/ })).toHaveTextContent('13003');
    expect(within(origin).getByRole('row', { name: /Non renseignée/ })).toHaveTextContent('15');
    expect(within(screen.getByRole('table', { name: 'Âge' })).getByRole('row', { name: /15-16 ans/ })).toHaveTextContent('59');

    const projectsTable = screen.getByRole('table', { name: 'Par projet' });
    const seconde = within(projectsTable).getByRole('row', { name: /Seconde 3/ });
    expect(seconde).toHaveTextContent('15-16 ans · Marseille (13003)');
    expect(seconde).toHaveTextContent('21,5 présents en moyenne, 86 % des 25 prévus');
    expect(within(projectsTable).getByRole('row', { name: /Première 1/ })).toHaveTextContent(/30 présents en moyenne$/);

    // Chaque tableau défilant a son propre nom de repère, distinct de celui de sa section.
    expect(screen.getByRole('region', { name: 'Tableau : Provenance' })).toHaveAttribute('tabindex', '0');
    expect(screen.getByRole('region', { name: 'Tableau : Âge' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Tableau : par projet' })).toBeInTheDocument();
  });

  it('ne plante pas sur un groupe sans données d’assiduité', async () => {
    const data = statistics();
    data.projects[0].sub_projects = [{ id: 13, name: 'Ancien groupe', participants: 0, sessions_documented: 0 }];
    mockApi({
      'GET /statistics': () => jsonResponse({ data }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');
    const row = await screen.findByRole('row', { name: /Ancien groupe/ });
    expect(row).toHaveTextContent('Non renseignée');
  });

  it('indique quand aucun participant n’est compté sur la période', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics({ by_city: [], by_age_range: [] }) }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');
    expect(await screen.findAllByText('Aucun participant compté sur la période.')).toHaveLength(2);
  });

  it('écrit « Non renseigné » pour une moyenne sans note, jamais 0', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');
    const row = await screen.findByRole('row', { name: /Atelier Belle de Mai/ });
    expect(within(row).getAllByText('Non renseigné')).toHaveLength(3);
    expect(row).not.toHaveTextContent('0 / 5');
  });

  it('applique les filtres et les garde dans l’adresse', async () => {
    const fetchMock = mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    const user = userEvent.setup();
    renderAt('/statistiques');
    await screen.findByRole('table', { name: 'Par projet' });

    await user.selectOptions(screen.getByLabelText('Projet'), '2');
    await user.type(screen.getByLabelText('Du'), '2026-09-01');
    await user.type(screen.getByLabelText('Au'), '2026-12-31');
    await user.click(screen.getByRole('button', { name: 'Appliquer' }));
    await screen.findByRole('table', { name: 'Par projet' });
    expect(queryOf(fetchMock)).toEqual({ project_id: '2', from: '2026-09-01', to: '2026-12-31' });
    expect(screen.getByTestId('where')).toHaveTextContent('/statistiques?projet=2&du=2026-09-01&au=2026-12-31');
  });

  it('remet le formulaire à l’état de l’adresse après un retour en arrière', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    const user = userEvent.setup();
    renderAt('/statistiques');
    await screen.findByRole('table', { name: 'Par projet' });
    await user.selectOptions(screen.getByLabelText('Projet'), '2');
    await user.click(screen.getByRole('button', { name: 'Appliquer' }));
    await screen.findByRole('table', { name: 'Par projet' });

    await user.click(screen.getByRole('button', { name: 'Page précédente' }));
    await screen.findByRole('table', { name: 'Par projet' });
    expect(screen.getByTestId('where')).toHaveTextContent(/^\/statistiques$/);
    expect(screen.getByLabelText('Projet')).toHaveValue('');
  });

  it('« Tout afficher » vide le formulaire et l’adresse', async () => {
    const fetchMock = mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    const user = userEvent.setup();
    renderAt('/statistiques?projet=1&du=2026-09-01');
    await screen.findByRole('table', { name: 'Par projet' });
    await user.click(screen.getByRole('button', { name: 'Tout afficher' }));
    await screen.findByRole('table', { name: 'Par projet' });
    expect(screen.getByTestId('where')).toHaveTextContent(/^\/statistiques$/);
    expect(screen.getByLabelText('Du')).toHaveValue('');
    expect(queryOf(fetchMock)).toEqual({});
  });

  it('ignore les filtres mal formés de l’adresse', async () => {
    const fetchMock = mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques?du=abc&projet=x');
    await screen.findByRole('table', { name: 'Par projet' });
    expect(queryOf(fetchMock)).toEqual({});
  });

  it('garde affiché le projet filtré même absent de la liste des projets (archivé)', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics({ projects: [{ ...statistics().projects[1], id: 7, name: 'Projet archivé' }] }) }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques?projet=7');
    await screen.findByRole('table', { name: 'Par projet' });
    expect(screen.getByLabelText('Projet')).toHaveDisplayValue('Projet archivé');
  });

  it('reste utilisable quand la liste des projets ne charge pas', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ message: 'Erreur' }, 500),
    });
    renderAt('/statistiques');
    expect(await screen.findByRole('table', { name: 'Par projet' })).toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Atelier Belle de Mai' })).toBeInTheDocument();
  });

  it('déconnecte quand la session a expiré', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ message: 'Unauthenticated.' }, 401),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');
    await screen.findByText(/\/connexion/);
  });

  it('affiche le message d’erreur réseau', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => {
      throw new TypeError('Failed to fetch');
    }));
    renderAt('/statistiques');
    expect(await screen.findByRole('alert')).toHaveTextContent(/connexion|réseau|serveur/i);
  });

  it('est accessible à la super-utilisatrice', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques', MOCK_USERS.super_user);
    expect(await screen.findByRole('table', { name: 'Par projet' })).toBeInTheDocument();
  });

  it('lit les filtres de l’adresse au chargement', async () => {
    const fetchMock = mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques?projet=1&du=2026-09-01');
    await screen.findByRole('table', { name: 'Par projet' });
    expect(queryOf(fetchMock)).toEqual({ project_id: '1', from: '2026-09-01' });
    expect(screen.getByLabelText('Du')).toHaveValue('2026-09-01');
  });

  it('affiche les erreurs de filtre sous le bon champ', async () => {
    mockApi({
      'GET /statistics': () =>
        jsonResponse({ message: 'Invalid', errors: { to: ['La fin de la période doit être après son début.'] } }, 422),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques?du=2026-10-10&au=2026-10-01');
    expect(await screen.findByText('La fin de la période doit être après son début.')).toBeInTheDocument();
    expect(screen.getByLabelText('Au')).toHaveAttribute('aria-invalid', 'true');
    expect(screen.queryByRole('button', { name: 'Réessayer' })).not.toBeInTheDocument();
  });

  it('propose de réessayer après une erreur du serveur', async () => {
    let calls = 0;
    mockApi({
      'GET /statistics': () => (++calls === 1 ? jsonResponse({ message: 'Erreur' }, 500) : jsonResponse({ data: statistics() })),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    const user = userEvent.setup();
    renderAt('/statistiques');
    expect(await screen.findByRole('alert')).toHaveTextContent('n’ont pas pu être chargées');
    await user.click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByRole('table', { name: 'Par projet' })).toBeInTheDocument();
  });

  it('indique quand il n’y a aucun projet', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics({ projects: [] }) }),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/statistiques');
    expect(await screen.findByText('Aucun projet à afficher.')).toBeInTheDocument();
  });

  it('n’est pas accessible à un intervenant', async () => {
    const fetchMock = mockApi({});
    renderAt('/statistiques', MOCK_USERS.contributor);
    expect(screen.queryByRole('heading', { name: 'Statistiques' })).not.toBeInTheDocument();
    expect(screenCalls(fetchMock)).toHaveLength(0);
  });
});
