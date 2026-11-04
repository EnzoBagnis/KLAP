import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

const artsDanse = {
  id: 1,
  name: 'Arts Danse au Lycée Charles',
  description: 'Lycée Saint-Charles, option et spécialité danse',
  project_type: { id: 1, name: 'Partenariat lycée' },
  planned_sessions: 32,
  start_date: '2026-09-15',
  end_date: '2027-06-19',
  status: 'active',
  default_location: { id: 2, name: 'Grand studio' },
  sub_projects: ['Seconde 3', 'Seconde 7', 'Première 2', 'Terminale 5'].map((name, i) => ({
    id: i + 1,
    name,
    organization: 'Lycée Saint-Charles',
    class_group: null,
    age_range: '15-16 ans',
    city: 'Marseille',
    postal_code: '13003',
    expected_headcount: 24,
  })),
  team: [
    { id: 3, name: 'Léa Fournier', member_role: 'lead' },
    { id: 7, name: 'Marco Delaunay', member_role: 'occasional' },
  ],
  funders: [{ id: 1, name: 'DRAC', type: 'state' }],
};

const aimer = {
  id: 3,
  name: 'AIMER',
  description: null,
  project_type: null,
  planned_sessions: null,
  start_date: null,
  end_date: null,
  status: 'archived',
  default_location: null,
  sub_projects: [{ id: 9, name: 'AIMER', city: null, postal_code: null }],
  team: [],
  funders: [],
};

const list = [
  { id: 1, name: artsDanse.name, project_type: artsDanse.project_type, status: 'active', sub_projects_count: 4, planned_sessions: 32 },
  { id: 3, name: 'AIMER', project_type: null, status: 'draft', sub_projects_count: 1, planned_sessions: null },
];

function renderAt(path, user = MOCK_USERS.admin) {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

describe('Liste des projets', () => {
  it('liste les projets et mène au détail', async () => {
    mockApi({
      'GET /projects': () => jsonResponse({ data: list }),
      'GET /projects/1': () => jsonResponse({ data: artsDanse }),
    });
    renderAt('/projets');

    const card = await screen.findByRole('link', { name: /Arts Danse au Lycée Charles/ });
    expect(card).toHaveTextContent('4 sous-projets · 32 séances prévues');
    expect(screen.getByRole('link', { name: /AIMER/ })).toHaveTextContent('Brouillon');

    await userEvent.setup().click(card);
    expect(await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' })).toBeInTheDocument();
  });

  it('dit qu’il n’y a aucun projet', async () => {
    mockApi({ 'GET /projects': () => jsonResponse({ data: [] }) });
    renderAt('/projets', MOCK_USERS.contributor);

    expect(await screen.findByText('Aucun projet pour l’instant.')).toBeInTheDocument();
  });

  it('renvoie à la connexion si la session a expiré', async () => {
    mockApi({ 'GET /projects': () => jsonResponse({ message: 'Unauthenticated.' }, 401) });
    renderAt('/projets');

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument();
  });

  it('propose de réessayer quand le serveur est injoignable', async () => {
    renderAt('/projets');

    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de joindre le serveur.');
    mockApi({ 'GET /projects': () => jsonResponse({ data: list }) });
    await userEvent.setup().click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByRole('link', { name: /AIMER/ })).toBeInTheDocument();
  });
});

describe('Détail d’un projet', () => {
  it('affiche l’en-tête, l’équipe et les sous-projets', async () => {
    mockApi({
      'GET /projects/1': () => jsonResponse({ data: artsDanse }),
      'GET /projects': () => jsonResponse({ data: list }),
    });
    renderAt('/projets/1');

    expect(await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' })).toBeInTheDocument();
    expect(screen.getByText('Partenariat lycée')).toBeInTheDocument();
    expect(screen.getByText('Marseille · 13003')).toBeInTheDocument();
    expect(screen.getByText('Léa Fournier')).toBeInTheDocument();
    expect(screen.getByText('Marco Delaunay')).toBeInTheDocument();
    expect(screen.getByText('du 15 septembre 2026 au 19 juin 2027')).toBeInTheDocument();
    expect(screen.getByText('DRAC')).toBeInTheDocument();

    const subProjects = screen.getByRole('region', { name: 'Sous-projets' });
    expect(within(subProjects).getAllByRole('listitem')).toHaveLength(4);
    // Aucun chiffre inventé tant que l'API des séances n'existe pas.
    expect(screen.queryByText(/participants$/i)).not.toBeInTheDocument();
    expect(screen.queryByText(/Taux de présence/i)).not.toBeInTheDocument();
  });

  it('garde « Projets » actif dans le menu et propose les autres projets en onglets', async () => {
    mockApi({
      'GET /projects/1': () => jsonResponse({ data: artsDanse }),
      'GET /projects': () => jsonResponse({ data: list }),
    });
    renderAt('/projets/1');

    const tabs = await screen.findByRole('navigation', { name: 'Projets' });
    expect(within(tabs).getByRole('link', { name: 'Arts Danse au Lycée Charles' })).toHaveAttribute('aria-current', 'page');
    const menu = screen.getByRole('navigation', { name: 'Navigation principale' });
    expect(within(menu).getByRole('link', { name: /Projets/ })).toHaveAttribute('aria-current', 'page');
  });

  it('n’affiche pas de découpage pour un projet à un seul sous-projet, et supporte les champs vides', async () => {
    mockApi({
      'GET /projects/3': () => jsonResponse({ data: aimer }),
      'GET /projects': () => jsonResponse({ data: list }),
    });
    renderAt('/projets/3');

    expect(await screen.findByRole('heading', { name: 'AIMER' })).toBeInTheDocument();
    expect(screen.getByText('Archivé')).toBeInTheDocument();
    expect(screen.getByText('Non désigné·e')).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Sous-projets' })).not.toBeInTheDocument();
  });

  it('affiche « Projet introuvable » sur une 404', async () => {
    mockApi({
      'GET /projects/99': () => jsonResponse({ message: 'Not Found' }, 404),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/projets/99', MOCK_USERS.contributor);

    expect(await screen.findByRole('heading', { name: 'Projet introuvable' })).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Retour aux projets' })).toHaveAttribute('href', '/projets');
  });

  it('renvoie à la connexion si la session a expiré', async () => {
    mockApi({
      'GET /projects/1': () => jsonResponse({ message: 'Unauthenticated.' }, 401),
      'GET /projects': () => jsonResponse({ message: 'Unauthenticated.' }, 401),
    });
    renderAt('/projets/1');

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument();
  });

  it('recharge quand on change de projet par les onglets', async () => {
    mockApi({
      'GET /projects/1': () => jsonResponse({ data: artsDanse }),
      'GET /projects/3': () => jsonResponse({ data: aimer }),
      'GET /projects': () => jsonResponse({ data: list }),
    });
    renderAt('/projets/1');

    const tabs = await screen.findByRole('navigation', { name: 'Projets' });
    await userEvent.setup().click(within(tabs).getByRole('link', { name: 'AIMER' }));

    expect(await screen.findByRole('heading', { name: 'AIMER' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Arts Danse au Lycée Charles' })).not.toBeInTheDocument();
  });

  it('réessaie le détail après une erreur serveur', async () => {
    let attempts = 0;
    mockApi({
      'GET /projects/1': () =>
        ++attempts === 1 ? jsonResponse({ message: 'Server Error' }, 500) : jsonResponse({ data: artsDanse }),
      'GET /projects': () => jsonResponse({ data: list }),
    });
    renderAt('/projets/1');

    expect(await screen.findByRole('alert')).toHaveTextContent('Le projet n’a pas pu être chargé.');
    await userEvent.setup().click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' })).toBeInTheDocument();
    await waitFor(() => expect(document.title).toBe('Arts Danse au Lycée Charles · KLAP'));
  });

  it('ignore une réponse arrivée après le changement de projet', async () => {
    let releaseFirst;
    mockApi({
      'GET /projects/1': () => new Promise((resolve) => (releaseFirst = resolve)),
      'GET /projects/3': () => jsonResponse({ data: aimer }),
      'GET /projects': () => jsonResponse({ data: list }),
    });
    renderAt('/projets/1');

    const tabs = await screen.findByRole('navigation', { name: 'Projets' });
    await userEvent.setup().click(within(tabs).getByRole('link', { name: 'AIMER' }));
    expect(await screen.findByRole('heading', { name: 'AIMER' })).toBeInTheDocument();

    releaseFirst(jsonResponse({ data: artsDanse })); // la réponse du projet 1 arrive en retard
    await new Promise((resolve) => setTimeout(resolve, 0));

    expect(screen.getByRole('heading', { name: 'AIMER' })).toBeInTheDocument();
    expect(screen.queryByRole('heading', { name: 'Arts Danse au Lycée Charles' })).not.toBeInTheDocument();
  });

  it('affiche le projet même si la liste des onglets échoue', async () => {
    mockApi({
      'GET /projects/1': () => jsonResponse({ data: artsDanse }),
      'GET /projects': () => jsonResponse({ message: 'Server Error' }, 500),
    });
    renderAt('/projets/1');

    expect(await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' })).toBeInTheDocument();
    expect(screen.queryByRole('navigation', { name: 'Projets' })).not.toBeInTheDocument();
  });

  it('n’affiche pas de zéro isolé et nomme un statut inconnu', async () => {
    const odd = {
      ...artsDanse,
      status: 'en_pause',
      planned_sessions: 0,
      sub_projects: artsDanse.sub_projects.map((sub) => ({ ...sub, expected_headcount: 0 })),
    };
    mockApi({
      'GET /projects/1': () => jsonResponse({ data: odd }),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/projets/1');

    expect(await screen.findByText('Statut inconnu')).toBeInTheDocument();
    expect(screen.queryByText('0')).not.toBeInTheDocument();
    expect(screen.queryByText(/Séances prévues/)).not.toBeInTheDocument();
  });

  it('annonce un projet introuvable aux lecteurs d’écran', async () => {
    mockApi({
      'GET /projects/99': () => jsonResponse({ message: 'Not Found' }, 404),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/projets/99');

    expect(await screen.findByRole('alert')).toHaveTextContent('Projet introuvable');
    await waitFor(() => expect(document.title).toBe('Projet introuvable · KLAP'));
  });
});
