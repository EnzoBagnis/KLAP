import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';
import { mapServerErrors, toPayload, validateProject } from './NewProjectPage.jsx';

const references = {
  'GET /project-types': () =>
    jsonResponse({
      data: [
        { id: 1, name: 'Partenariat lycée', is_active: true },
        { id: 2, name: 'Ancien type', is_active: false },
      ],
    }),
  'GET /locations': () =>
    jsonResponse({ data: [{ id: 2, name: 'Grand studio', postal_code: '13003', city: 'Marseille' }] }),
  'GET /users': () => jsonResponse({ data: [{ id: 3, name: 'Léa Fournier' }] }),
  'GET /funders': () =>
    jsonResponse({
      data: [
        { id: 1, name: 'DRAC', type: 'state' },
        { id: 2, name: 'Ville de Marseille', type: 'local_authority' },
      ],
    }),
};

function renderAt(path, user = MOCK_USERS.admin) {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

const field = (label) => screen.getByLabelText(new RegExp(label));

async function fillRequired(user) {
  await user.type(field('Nom du projet'), 'BRAISES');
  await user.selectOptions(field('Lieu par défaut'), '2');
  await user.selectOptions(field('Intervenant·e principal·e'), '3');
}

describe('Gestion des projets', () => {
  it('liste les projets, sans bouton Modifier ni Supprimer, avec le lien de création', async () => {
    mockApi({
      'GET /projects': () =>
        jsonResponse({
          data: [{ id: 1, name: 'AIMER', project_type: { id: 1, name: 'Partenariat lycée' }, status: 'draft', sub_projects_count: 1 }],
        }),
    });
    renderAt('/gestion');

    const link = await screen.findByRole('link', { name: 'AIMER' });
    expect(link).toHaveAttribute('href', '/projets/1');
    expect(within(link.closest('li')).getByText('Brouillon')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Modifier|Supprimer/ })).not.toBeInTheDocument();
    expect(screen.getByRole('link', { name: '+ Nouveau projet' })).toHaveAttribute('href', '/gestion/projets/nouveau');
  });

  it('dit qu’il n’y a encore aucun projet', async () => {
    mockApi({ 'GET /projects': () => jsonResponse({ data: [] }) });
    renderAt('/gestion');

    expect(await screen.findByText(/Aucun projet/)).toBeInTheDocument();
  });

  it('montre les projets archivés à la demande, pour pouvoir les restaurer (#196)', async () => {
    const user = userEvent.setup();
    // Le gestionnaire de mockApi ne reçoit pas l'URL : on lit celle de l'appel en cours.
    const archivedAsked = () => new URL(fetchMock.mock.calls.at(-1)[0]).searchParams.get('include_archived') === '1';
    const fetchMock = mockApi({
      'GET /projects': () =>
        jsonResponse({
          data: [
            { id: 1, name: 'AIMER', status: 'active', sub_projects_count: 1 },
            ...(archivedAsked()
              ? [{ id: 2, name: 'BRAISES', status: 'archived', sub_projects_count: 1 }]
              : []),
          ],
        }),
    });
    renderAt('/gestion');

    await screen.findByRole('link', { name: 'AIMER' });
    expect(screen.queryByRole('link', { name: 'BRAISES' })).not.toBeInTheDocument();

    await user.click(screen.getByRole('checkbox', { name: 'Afficher les projets archivés' }));
    const archived = await screen.findByRole('link', { name: 'BRAISES' });
    expect(archived).toHaveAttribute('href', '/projets/2');
    expect(within(archived.closest('li')).getByText('Archivé')).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([url]) => new URL(url).searchParams.get('include_archived') === '1')).toBe(true);

    await user.click(screen.getByRole('checkbox', { name: 'Afficher les projets archivés' }));
    await screen.findByRole('link', { name: 'AIMER' });
    expect(screen.queryByRole('link', { name: 'BRAISES' })).not.toBeInTheDocument();
  });

  it('garde la case cochée depuis l’adresse (?archives=1)', async () => {
    mockApi({ 'GET /projects': () => jsonResponse({ data: [] }) });
    renderAt('/gestion?archives=1');

    expect(await screen.findByText('Aucun projet, archivé ou non.')).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'Afficher les projets archivés' })).toBeChecked();
  });

  it.each([
    ['super-utilisatrice', MOCK_USERS.super_user],
    ['intervenant', MOCK_USERS.contributor],
  ])('refuse la création à la %s', (_, user) => {
    renderAt('/gestion/projets/nouveau', user);

    expect(screen.queryByRole('heading', { name: 'Nouveau projet' })).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
  });
});

describe('Nouveau projet', () => {
  it('propose les listes de l’API, sans type désactivé', async () => {
    mockApi(references);
    renderAt('/gestion/projets/nouveau');

    const types = await screen.findByLabelText(/Type de projet/);
    expect(within(types).getByRole('option', { name: 'Partenariat lycée' })).toBeInTheDocument();
    expect(within(types).queryByRole('option', { name: 'Ancien type' })).not.toBeInTheDocument();
    expect(screen.getByRole('option', { name: 'Grand studio (Marseille)' })).toBeInTheDocument();
    expect(screen.getByRole('checkbox', { name: 'DRAC' })).toBeInTheDocument();
  });

  it('bloque l’envoi et met le focus sur le premier champ manquant', async () => {
    const fetchMock = mockApi(references);
    renderAt('/gestion/projets/nouveau');
    const user = userEvent.setup();

    await user.click(await screen.findByRole('button', { name: 'Créer le projet' }));

    expect(screen.getByText('Donnez un nom au projet.')).toBeInTheDocument();
    expect(screen.getByText('Choisissez le lieu par défaut.')).toBeInTheDocument();
    expect(screen.getByText('Choisissez l’intervenant·e principal·e.')).toBeInTheDocument();
    expect(field('Nom du projet')).toHaveFocus();
    expect(fetchMock.mock.calls.some(([, options]) => options?.method === 'POST')).toBe(false);
  });

  it('crée le projet avec le bon contenu puis propose de l’ouvrir', async () => {
    const fetchMock = mockApi({
      ...references,
      'POST /projects': () => jsonResponse({ data: { id: 42, name: 'BRAISES' } }, 201),
      'GET /projects/42': () =>
        jsonResponse({ data: { id: 42, name: 'BRAISES', status: 'active', sub_projects: [], team: [], funders: [] } }),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/gestion/projets/nouveau');
    const user = userEvent.setup();
    await screen.findByRole('button', { name: 'Créer le projet' });

    await fillRequired(user);
    await user.click(screen.getByRole('checkbox', { name: 'DRAC' }));
    await user.click(screen.getByRole('button', { name: '+ Ajouter un sous-projet' }));
    await user.type(field('Sous-projet 1'), 'Collège Aragon');
    await user.type(screen.getByLabelText('Tranche d’âge du sous-projet 1'), '11-12 ans');
    await user.type(screen.getByLabelText('Effectif prévu du sous-projet 1'), '26');
    await user.click(screen.getByRole('button', { name: 'Créer le projet' }));

    const dialog = await screen.findByRole('dialog', { name: 'Projet créé' });
    const post = fetchMock.mock.calls.find(([, options]) => options?.method === 'POST');
    expect(JSON.parse(post[1].body)).toEqual({
      name: 'BRAISES',
      default_location_id: 2,
      principal_user_id: 3,
      funder_ids: [1],
      sub_projects: [{ name: 'Collège Aragon', age_range: '11-12 ans', expected_headcount: 26 }],
    });

    await user.click(within(dialog).getByRole('button', { name: 'Voir le projet' }));
    expect(await screen.findByRole('heading', { name: 'BRAISES' })).toBeInTheDocument();
  });

  it('affiche les erreurs de l’API sur les bons champs, y compris un sous-projet', async () => {
    mockApi({
      ...references,
      'POST /projects': () =>
        jsonResponse(
          {
            message: 'invalide',
            errors: {
              postal_code: ['Le champ code postal doit contenir 5 chiffres.'],
              'sub_projects.0.name': ['Deux sous-projets portent le même nom.'],
            },
          },
          422,
        ),
    });
    renderAt('/gestion/projets/nouveau');
    const user = userEvent.setup();
    await screen.findByRole('button', { name: 'Créer le projet' });

    await fillRequired(user);
    await user.click(screen.getByRole('button', { name: '+ Ajouter un sous-projet' }));
    await user.type(field('Sous-projet 1'), 'Seconde 3');
    await user.click(screen.getByRole('button', { name: 'Créer le projet' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('Le projet n’a pas été créé');
    expect(screen.getByText('Le champ code postal doit contenir 5 chiffres.')).toBeInTheDocument();
    expect(screen.getByText('Deux sous-projets portent le même nom.')).toBeInTheDocument();
    expect(field('Nom du projet')).toHaveValue('BRAISES');
  });

  it('ne peut pas renvoyer le formulaire derrière la modale, qui se ferme avec Échap', async () => {
    const fetchMock = mockApi({
      ...references,
      'POST /projects': () => jsonResponse({ data: { id: 42, name: 'BRAISES' } }, 201),
      'GET /projects': () => jsonResponse({ data: [] }),
    });
    renderAt('/gestion/projets/nouveau');
    const user = userEvent.setup();
    await screen.findByRole('button', { name: 'Créer le projet' });

    await fillRequired(user);
    await user.click(screen.getByRole('button', { name: 'Créer le projet' }));
    const dialog = await screen.findByRole('dialog', { name: 'Projet créé' });

    expect(within(dialog).getByRole('button', { name: 'Retour à la gestion' })).toHaveFocus();
    expect(screen.getByRole('button', { name: 'Création…' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Création…' }).closest('[inert]')).not.toBeNull();

    await user.keyboard('{Escape}');
    expect(await screen.findByRole('heading', { name: 'Gestion' })).toBeInTheDocument();
    expect(fetchMock.mock.calls.filter(([, options]) => options?.method === 'POST')).toHaveLength(1);
  });

  it('affiche une erreur de financeur et met le focus sur le premier champ en erreur', async () => {
    mockApi({
      ...references,
      'POST /projects': () =>
        jsonResponse(
          {
            errors: {
              principal_user_id: ['L’intervenant·e principal·e doit être un compte intervenant actif.'],
              'funder_ids.0': ['Le financeur choisi n’existe pas.'],
              status: ['Le statut choisi n’est pas valide à la création.'],
            },
          },
          422,
        ),
    });
    renderAt('/gestion/projets/nouveau');
    const user = userEvent.setup();
    await screen.findByRole('button', { name: 'Créer le projet' });

    await fillRequired(user);
    await user.click(screen.getByRole('checkbox', { name: 'DRAC' }));
    await user.click(screen.getByRole('button', { name: 'Créer le projet' }));

    const alert = await screen.findByRole('alert');
    expect(alert).toHaveTextContent('Le statut choisi n’est pas valide à la création.');
    expect(screen.getByRole('group', { name: 'Financeurs destinataires' })).toHaveAccessibleDescription(
      'Le financeur choisi n’existe pas.',
    );
    expect(field('Intervenant·e principal·e')).toHaveFocus();
  });

  it('garde la saisie et prévient quand le serveur est injoignable', async () => {
    const routes = { ...references };
    mockApi(routes);
    renderAt('/gestion/projets/nouveau');
    const user = userEvent.setup();
    await screen.findByRole('button', { name: 'Créer le projet' });

    await fillRequired(user);
    await user.click(screen.getByRole('button', { name: 'Créer le projet' }));

    // Aucune route POST simulée : mockApi lève une erreur, traitée comme une panne réseau.
    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de joindre le serveur.');
    expect(field('Nom du projet')).toHaveValue('BRAISES');
    expect(screen.getByRole('button', { name: 'Créer le projet' })).toBeEnabled();
  });

  it('propose de réessayer si les listes ne se chargent pas', async () => {
    renderAt('/gestion/projets/nouveau');

    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de joindre le serveur.');
    mockApi(references);
    await userEvent.setup().click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByRole('button', { name: 'Créer le projet' })).toBeInTheDocument();
  });
});

describe('Règles du formulaire', () => {
  const base = {
    name: 'BRAISES',
    project_type_id: '',
    organization: '',
    city: '',
    postal_code: '',
    planned_sessions: '',
    start_date: '',
    end_date: '',
    default_location_id: '2',
    principal_user_id: '3',
    funder_ids: [],
    sub_projects: [],
  };

  it.each([
    ['code postal sur 4 chiffres', { postal_code: '1300' }, 'postal_code'],
    ['zéro séance', { planned_sessions: '0' }, 'planned_sessions'],
    ['séances non entières', { planned_sessions: '2.5' }, 'planned_sessions'],
    ['fin avant début', { start_date: '2027-06-19', end_date: '2026-09-15' }, 'end_date'],
    ['nom trop long', { name: 'a'.repeat(151) }, 'name'],
    ['sous-projet vide', { sub_projects: [{ name: ' ' }] }, 'sub_projects.0.name'],
    ['sous-projets en double', { sub_projects: [{ name: 'Seconde 3' }, { name: 'seconde 3' }] }, 'sub_projects.1.name'],
    ['effectif nul (#244)', { sub_projects: [{ name: 'Seconde 3', expected_headcount: '0' }] }, 'sub_projects.0.expected_headcount'],
    ['effectif décimal', { sub_projects: [{ name: 'Seconde 3', expected_headcount: '24.5' }] }, 'sub_projects.0.expected_headcount'],
    ['tranche d’âge trop longue', { sub_projects: [{ name: 'Seconde 3', age_range: 'a'.repeat(31) }] }, 'sub_projects.0.age_range'],
    ['effectif du groupe unique', { expected_headcount: '-2' }, 'expected_headcount'],
  ])('refuse : %s', (_, override, key) => {
    expect(validateProject({ ...base, ...override })).toHaveProperty([key]);
  });

  it.each([
    ['séances au-delà du maximum', { planned_sessions: '65536' }, 'planned_sessions'],
    ['structure trop longue', { organization: 'a'.repeat(151) }, 'organization'],
  ])('refuse aussi : %s', (_, override, key) => {
    expect(validateProject({ ...base, ...override })).toHaveProperty([key]);
  });

  it('accepte un code postal entouré d’espaces, comme le serveur', () => {
    expect(validateProject({ ...base, postal_code: ' 13003 ' })).toEqual({});
  });

  it('regroupe les erreurs de financeurs et isole les clés inconnues', () => {
    expect(
      mapServerErrors({ 'funder_ids.1': ['A'], 'funder_ids.0': ['B'], 'sub_projects.2.name': ['C'], description: ['D'] }),
    ).toEqual({ fields: { funder_ids: 'A', 'sub_projects.2.name': 'C' }, others: ['D'] });
  });

  it('envoie l’âge et l’effectif de chaque sous-projet, ou du groupe unique (#244)', () => {
    expect(
      toPayload({
        ...base,
        age_range: '12-13 ans', // ignoré : les sous-projets ont les leurs
        sub_projects: [
          { name: 'Seconde 3', age_range: ' 15-16 ans ', expected_headcount: '24' },
          { name: 'Seconde 4', age_range: '', expected_headcount: '' },
        ],
      }).sub_projects,
    ).toEqual([{ name: 'Seconde 3', age_range: '15-16 ans', expected_headcount: 24 }, { name: 'Seconde 4' }]);
    expect(toPayload({ ...base, age_range: '15-16 ans', expected_headcount: '30' })).toMatchObject({
      age_range: '15-16 ans',
      expected_headcount: 30,
    });
    expect(toPayload({ ...base, age_range: '15-16 ans' })).not.toHaveProperty('sub_projects');
  });

  it('accepte une saisie minimale et n’envoie pas les champs vides', () => {
    expect(validateProject(base)).toEqual({});
    expect(toPayload(base)).toEqual({ name: 'BRAISES', default_location_id: 2, principal_user_id: 3, funder_ids: [] });
  });
});
