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

  it('montre les sensations, l’interaction et l’évolution des fiches rendues, avec leurs tableaux', async () => {
    const observations = {
      entries: 7,
      feelings: [
        { key: 'well_being', entries: 1 },
        { key: 'enthusiasm', entries: 2 },
        { key: 'other', entries: 1 },
      ],
      progress: [
        { key: 'first_session', entries: 4 },
        { key: 'improvement', entries: 3 },
      ],
      interaction_keywords: [
        { key: 'écoute', entries: 4 },
        { key: 'conflit', entries: 1 },
      ],
    };
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics({ observations }) }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');

    expect(await screen.findByRole('heading', { name: 'Ce que notent les intervenant·e·s' })).toBeInTheDocument();
    expect(screen.getByText('Sur 7 fiches rendues.')).toBeInTheDocument();
    expect(screen.getByRole('row', { name: /Enthousiasme/ })).toHaveTextContent('2');
    expect(screen.getByRole('row', { name: /^Écoute/ })).toHaveTextContent('4');
    expect(screen.getByRole('row', { name: /Première séance/ })).toHaveTextContent('4');
    // Couleurs de la cliente : bleu pour les sensations, ambre pour l'interaction.
    expect(document.querySelectorAll('.tone-feeling')).toHaveLength(3);
    expect(document.querySelectorAll('.tone-interaction')).toHaveLength(2);
  });

  it('sans fiche rendue, le dit au lieu de graphiques vides', async () => {
    const observations = { entries: 0, feelings: [], progress: [], interaction_keywords: [] };
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics({ observations }) }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');
    expect(await screen.findByText('Aucune fiche rendue sur la période.')).toBeInTheDocument();
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

  it('dessine les participants par projet, provenance et âge, masqués au lecteur d’écran', async () => {
    mockApi({
      'GET /statistics': () => jsonResponse({ data: statistics() }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques');

    await screen.findByRole('list', { name: 'Totaux' });
    const charts = [...document.querySelectorAll('figure.bar-chart')];
    expect(charts.map((chart) => chart.querySelector('figcaption').textContent)).toEqual([
      'Participants par provenance',
      'Participants par tranche d’âge',
      'Participants par projet',
    ]);
    // Le tableau qui suit donne les mêmes chiffres : le graphique n'est pas lu deux fois.
    charts.forEach((chart) => expect(chart).toHaveAttribute('aria-hidden', 'true'));

    const byProject = charts[2];
    const bars = [...byProject.querySelectorAll('.bar-chart-bar')].map((bar) => bar.style.width);
    expect(bars[0]).toBe('100%');
    expect(Number.parseFloat(bars[1])).toBeCloseTo((15 / 59) * 100, 1);
    expect(within(byProject).getByText('Atelier Belle de Mai').nextSibling.nextSibling).toHaveTextContent('15');
    expect(within(charts[0]).getByText('Non renseignée · Non renseigné')).toBeInTheDocument();
    // Plusieurs projets : pas d'assiduité par groupe, qui mélangerait les projets.
    expect(document.body).not.toHaveTextContent('Assiduité par groupe');
  });

  it('dessine l’assiduité des groupes quand un projet est filtré', async () => {
    const single = statistics({ projects: [statistics().projects[0]] });
    mockApi({
      'GET /statistics': () => jsonResponse({ data: single }),
      'GET /projects': () => jsonResponse({ data: projects }),
    });
    renderAt('/statistiques?projet=1');

    await screen.findByRole('list', { name: 'Totaux' });
    const captions = [...document.querySelectorAll('figure.bar-chart figcaption')].map((caption) => caption.textContent);
    expect(captions).not.toContain('Participants par projet');
    const attendance = [...document.querySelectorAll('figure.bar-chart')].find((chart) => chart.textContent.includes('Assiduité'));
    // Seul le groupe qui a un effectif prévu a un taux ; l'échelle va jusqu'à 100 %.
    expect(within(attendance).getByText('Seconde 3')).toBeInTheDocument();
    expect(within(attendance).queryByText('Première 1')).not.toBeInTheDocument();
    expect(within(attendance).getByText('86 %')).toBeInTheDocument();
    expect(attendance.querySelector('.bar-chart-bar').style.width).toBe('86%');
  });

  it('n’est pas accessible à un intervenant', async () => {
    const fetchMock = mockApi({});
    renderAt('/statistiques', MOCK_USERS.contributor);
    expect(screen.queryByRole('heading', { name: 'Statistiques' })).not.toBeInTheDocument();
    // Renvoyé au tableau de bord : aucune statistique demandée.
    expect(screenCalls(fetchMock).filter(([url]) => new URL(url).pathname.startsWith('/api/statistics'))).toHaveLength(0);
  });
});
