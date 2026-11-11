import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import { mapSessionErrors, toSessionPayload, validateSession } from '../../Modals/ScheduleSessionModal.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';
import { monthBounds, shiftMonth } from './sessionLabels.js';

const session = (overrides = {}) => ({
  id: 1,
  session_date: '2026-10-05',
  start_time: '10:00',
  end_time: '12:00',
  session_type: 'practice_workshop',
  session_type_detail: null,
  status: 'planned',
  project: { id: 1, name: 'Arts Danse au Lycée Charles' },
  location: { id: 2, name: 'Grand studio' },
  referent: { id: 3, name: 'Léa Fournier' },
  sub_projects: [{ id: 11, name: 'Seconde 3' }],
  ...overrides,
});

function renderAt(path, user = MOCK_USERS.admin) {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

const queryOf = (fetchMock, index = 0) => Object.fromEntries(new URL(fetchMock.mock.calls[index][0]).searchParams);

beforeEach(() => {
  // Seule la date est simulée : les minuteries restent réelles pour userEvent.
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date(2026, 9, 15, 9, 0));
});

afterEach(() => {
  vi.useRealTimers();
});

describe('Bornes de mois', () => {
  it.each([
    [{ year: 2026, month: 10 }, '2026-10-01', '2026-10-31'],
    [{ year: 2027, month: 2 }, '2027-02-01', '2027-02-28'],
    [{ year: 2028, month: 2 }, '2028-02-01', '2028-02-29'],
  ])('%o va du %s au %s', (month, from, to) => {
    expect(monthBounds(month)).toEqual({ from, to });
  });

  it('passe d’une année à l’autre', () => {
    expect(shiftMonth({ year: 2026, month: 12 }, 1)).toBe('2027-01');
    expect(shiftMonth({ year: 2026, month: 1 }, -1)).toBe('2025-12');
  });
});

describe('Planning', () => {
  it('affiche le mois courant, groupé par jour, avec les annulées', async () => {
    const fetchMock = mockApi({
      'GET /sessions': () =>
        jsonResponse({
          data: [
            session(),
            session({ id: 2, start_time: '14:00', end_time: '16:00', sub_projects: [{ id: 11, name: 'Seconde 3' }, { id: 12, name: 'Seconde 7' }] }),
            session({ id: 3, session_date: '2026-10-12', status: 'cancelled', session_type: 'other', session_type_detail: 'Sortie au spectacle' }),
          ],
        }),
    });
    renderAt('/planning');

    expect(await screen.findByRole('heading', { name: 'lundi 5 octobre' })).toBeInTheDocument();
    expect(screen.getByText('Octobre 2026')).toBeInTheDocument();
    expect(queryOf(fetchMock)).toEqual({ from: '2026-10-01', to: '2026-10-31' });
    expect(screen.getByText('Atelier de pratique · Seconde 3, Seconde 7')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'lundi 12 octobre' })).toBeInTheDocument();
    expect(screen.getByText('Annulée')).toBeInTheDocument();
    expect(screen.getByText(/Sortie au spectacle/)).toBeInTheDocument();
  });

  it('change de mois et lit le mois dans l’URL', async () => {
    const fetchMock = mockApi({ 'GET /sessions': () => jsonResponse({ data: [] }) });
    renderAt('/planning?mois=2026-12');

    expect(await screen.findByText('Aucune séance programmée en décembre 2026.')).toBeInTheDocument();
    await userEvent.setup().click(screen.getByRole('button', { name: 'Mois suivant' }));

    expect(await screen.findByText('Aucune séance programmée en janvier 2027.')).toBeInTheDocument();
    expect(queryOf(fetchMock, fetchMock.mock.calls.length - 1)).toEqual({ from: '2027-01-01', to: '2027-01-31' });
  });

  it('ignore la réponse d’un mois quitté avant qu’elle arrive', async () => {
    let releaseOctober;
    const fetchMock = vi.fn(async (url) => {
      const { from } = Object.fromEntries(new URL(url).searchParams);
      if (from === '2026-10-01') return new Promise((resolve) => (releaseOctober = resolve));
      return new Response(JSON.stringify({ data: [] }), { status: 200, headers: { 'Content-Type': 'application/json' } });
    });
    vi.stubGlobal('fetch', fetchMock);
    renderAt('/planning');

    await userEvent.setup().click(await screen.findByRole('button', { name: 'Mois suivant' }));
    expect(await screen.findByText('Aucune séance programmée en novembre 2026.')).toBeInTheDocument();

    releaseOctober(new Response(JSON.stringify({ data: [session()] }), { status: 200, headers: { 'Content-Type': 'application/json' } }));
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(screen.queryByRole('link', { name: 'Arts Danse au Lycée Charles' })).not.toBeInTheDocument();
  });

  it('ignore un mois invalide dans l’URL', async () => {
    mockApi({ 'GET /sessions': () => jsonResponse({ data: [] }) });
    renderAt('/planning?mois=0050-01');

    expect(await screen.findByText('Aucune séance programmée en octobre 2026.')).toBeInTheDocument();
  });

  it('propose de réessayer après une erreur', async () => {
    let attempts = 0;
    mockApi({
      'GET /sessions': () => (++attempts === 1 ? jsonResponse({ message: 'x' }, 500) : jsonResponse({ data: [session()] })),
    });
    renderAt('/planning');

    expect(await screen.findByRole('alert')).toHaveTextContent('Le planning n’a pas pu être chargé.');
    await userEvent.setup().click(screen.getByRole('button', { name: 'Réessayer' }));
    expect(await screen.findByRole('link', { name: 'Arts Danse au Lycée Charles' })).toBeInTheDocument();
  });

  it('renvoie à la connexion si la session a expiré', async () => {
    mockApi({ 'GET /sessions': () => jsonResponse({ message: 'Unauthenticated.' }, 401) });
    renderAt('/planning');

    expect(await screen.findByRole('heading', { name: 'Connexion' })).toBeInTheDocument();
  });

  it.each([
    ['super-utilisatrice', MOCK_USERS.super_user],
    ['intervenant', MOCK_USERS.contributor],
  ])('ne propose pas de programmer à la %s', async (_, user) => {
    mockApi({ 'GET /sessions': () => jsonResponse({ data: [] }) });
    renderAt('/planning', user);

    await screen.findByText(/Aucune séance programmée/);
    expect(screen.queryByRole('button', { name: 'Programmer une séance' })).not.toBeInTheDocument();
  });
});

describe('Programmer une séance', () => {
  const routes = (overrides = {}) => ({
    'GET /sessions': () => jsonResponse({ data: [] }),
    'GET /projects': () =>
      jsonResponse({
        data: [
          { id: 1, name: 'Arts Danse au Lycée Charles', status: 'active' },
          { id: 5, name: 'AIMER', status: 'active' },
        ],
      }),
    'GET /locations': () => jsonResponse({ data: [{ id: 2, name: 'Grand studio', city: 'Marseille' }] }),
    'GET /projects/1': () =>
      jsonResponse({
        data: {
          id: 1,
          sub_projects: [
            { id: 11, name: 'Seconde 3', is_active: true },
            { id: 12, name: 'Seconde 7', is_active: true },
          ],
          team: [
            { id: 3, name: 'Léa Fournier', member_role: 'lead' },
            { id: 7, name: 'Marco Delaunay', member_role: 'occasional' },
          ],
        },
      }),
    'GET /projects/5': () => jsonResponse({ data: { id: 5, sub_projects: [{ id: 51, name: 'AIMER', is_active: true }], team: [] } }),
    ...overrides,
  });

  async function openModal(fetchRoutes) {
    const fetchMock = mockApi(fetchRoutes);
    renderAt('/planning');
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: 'Programmer une séance' }));
    const dialog = await screen.findByRole('dialog', { name: 'Programmer une séance' });
    await within(dialog).findByLabelText(/Projet/);
    return { fetchMock, user, dialog };
  }

  it('crée un lieu depuis la modale sans la fermer', async () => {
    const { user, dialog } = await openModal(
      routes({ 'POST /locations': () => jsonResponse({ data: { id: 9, name: 'Théâtre du Merlan', postal_code: '13014', city: 'Marseille' } }, 201) }),
    );
    await user.selectOptions(within(dialog).getByLabelText(/^Lieu/), 'Nouveau lieu…');
    await user.type(within(dialog).getByLabelText(/Nom du lieu/), 'Théâtre du Merlan');
    await user.type(within(dialog).getByLabelText(/Code postal/), '13014');
    await user.type(within(dialog).getByLabelText(/Ville/), 'Marseille');
    await user.keyboard('{Escape}');
    // Échap ferme le dépliage, pas la modale.
    expect(screen.getByRole('dialog', { name: 'Programmer une séance' })).toBeInTheDocument();
    await user.selectOptions(within(dialog).getByLabelText(/^Lieu/), 'Nouveau lieu…');
    await user.type(within(dialog).getByLabelText(/Nom du lieu/), 'Théâtre du Merlan');
    await user.type(within(dialog).getByLabelText(/Code postal/), '13014');
    await user.type(within(dialog).getByLabelText(/Ville/), 'Marseille');
    await user.click(within(dialog).getByRole('button', { name: 'Ajouter le lieu' }));
    expect(await within(dialog).findByRole('option', { name: 'Théâtre du Merlan (Marseille)' })).toBeInTheDocument();
    expect(within(dialog).getByLabelText(/^Lieu/)).toHaveValue('9');
  });

  async function fill(user, dialog) {
    await user.selectOptions(within(dialog).getByLabelText(/Projet/), '1');
    await within(dialog).findByRole('checkbox', { name: 'Seconde 3' });
    await user.click(within(dialog).getByRole('checkbox', { name: 'Seconde 3' }));
    await user.type(within(dialog).getByLabelText(/Date/), '2026-11-09');
    await user.type(within(dialog).getByLabelText(/Début/), '10:00');
    await user.type(within(dialog).getByLabelText(/Fin/), '12:00');
    await user.selectOptions(within(dialog).getByLabelText(/Type de séance/), 'practice_workshop');
    await user.selectOptions(within(dialog).getByLabelText(/Lieu/), '2');
  }

  it('le choix du projet charge ses groupes et préselectionne l’intervenant·e principal·e', async () => {
    const { user, dialog } = await openModal(routes());

    await user.selectOptions(within(dialog).getByLabelText(/Projet/), '1');

    expect(await within(dialog).findByRole('checkbox', { name: 'Seconde 7' })).toBeInTheDocument();
    expect(within(dialog).getByLabelText(/Intervenant·e/)).toHaveValue('3');
  });

  it('préselectionne le lieu par défaut du projet, s’il est encore proposé', async () => {
    const detail = (place) => () =>
      jsonResponse({ data: { id: 5, default_location: place, sub_projects: [{ id: 51, name: 'AIMER', is_active: true }], team: [] } });
    const { user, dialog } = await openModal(routes({ 'GET /projects/5': detail({ id: 2, name: 'Grand studio' }) }));
    await user.selectOptions(within(dialog).getByLabelText(/Projet/), '5');
    await within(dialog).findByRole('checkbox', { name: 'AIMER' });
    expect(within(dialog).getByLabelText(/^Lieu/)).toHaveValue('2');
  });

  it('ne préselectionne pas un lieu par défaut devenu inactif', async () => {
    const { user, dialog } = await openModal(
      routes({
        'GET /projects/5': () =>
          jsonResponse({ data: { id: 5, default_location: { id: 99, name: 'Ancienne salle' }, sub_projects: [{ id: 51, name: 'AIMER', is_active: true }], team: [] } }),
      }),
    );
    await user.selectOptions(within(dialog).getByLabelText(/Projet/), '5');
    await within(dialog).findByRole('checkbox', { name: 'AIMER' });
    expect(within(dialog).getByLabelText(/^Lieu/)).toHaveValue('');
  });

  it('un projet à un seul groupe le coche d’office, et sans équipe l’envoi est bloqué', async () => {
    const { fetchMock, user, dialog } = await openModal(routes());

    await user.selectOptions(within(dialog).getByLabelText(/Projet/), '5');
    expect(await within(dialog).findByRole('checkbox', { name: 'AIMER' })).toBeChecked();

    await user.click(within(dialog).getByRole('button', { name: 'Programmer la séance' }));
    expect(within(dialog).getByText('Aucun intervenant·e affecté·e à ce projet.')).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([, options]) => options?.method === 'POST')).toBe(false);
  });

  it('la précision n’apparaît que pour le type « autre »', async () => {
    const { user, dialog } = await openModal(routes());

    expect(within(dialog).queryByLabelText(/Précision/)).not.toBeInTheDocument();
    await user.selectOptions(within(dialog).getByLabelText(/Type de séance/), 'other');
    expect(within(dialog).getByLabelText(/Précision/)).toBeInTheDocument();
  });

  it('programme la séance, ferme la modale et affiche son mois', async () => {
    const fetchMock = mockApi(
      routes({
        'POST /projects/1/sessions': () => jsonResponse({ data: session({ id: 9, session_date: '2026-11-09' }) }, 201),
      }),
    );
    renderAt('/planning');
    const user = userEvent.setup();
    await user.click(await screen.findByRole('button', { name: 'Programmer une séance' }));
    const dialog = await screen.findByRole('dialog', { name: 'Programmer une séance' });
    await within(dialog).findByLabelText(/Projet/);

    await fill(user, dialog);
    await user.click(within(dialog).getByRole('button', { name: 'Programmer la séance' }));

    expect(await screen.findByText('Novembre 2026')).toBeInTheDocument();
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    const post = fetchMock.mock.calls.find(([, options]) => options?.method === 'POST');
    expect(JSON.parse(post[1].body)).toEqual({
      session_date: '2026-11-09',
      start_time: '10:00',
      end_time: '12:00',
      session_type: 'practice_workshop',
      location_id: 2,
      referent_id: 3,
      sub_project_ids: [11],
    });
  });

  it('affiche les erreurs de l’API sur les groupes et le projet archivé en message général', async () => {
    const { user, dialog } = await openModal(
      routes({
        'POST /projects/1/sessions': () =>
          jsonResponse(
            {
              errors: {
                'sub_project_ids.0': ['Ce groupe n’appartient pas au projet ou n’est plus actif.'],
                project: ['Ce projet est archivé : on ne peut plus y programmer de séance.'],
              },
            },
            422,
          ),
      }),
    );

    await fill(user, dialog);
    await user.click(within(dialog).getByRole('button', { name: 'Programmer la séance' }));

    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Ce projet est archivé');
    expect(within(dialog).getByText('Ce groupe n’appartient pas au projet ou n’est plus actif.')).toBeInTheDocument();
    expect(within(dialog).getByRole('button', { name: 'Programmer la séance' })).toBeEnabled();
  });

  it('ne se ferme pas pendant l’envoi, et garde la saisie après une erreur serveur', async () => {
    let releasePost;
    const { user, dialog } = await openModal(
      routes({ 'POST /projects/1/sessions': () => new Promise((resolve) => (releasePost = resolve)) }),
    );

    await fill(user, dialog);
    await user.click(within(dialog).getByRole('button', { name: 'Programmer la séance' }));

    expect(within(dialog).getByRole('button', { name: 'Annuler' })).toBeDisabled();
    await user.keyboard('{Escape}');
    expect(screen.getByRole('dialog', { name: 'Programmer une séance' })).toBeInTheDocument();

    releasePost(jsonResponse({ message: 'Server Error' }, 500));
    expect(await within(dialog).findByRole('alert')).toHaveTextContent('La séance n’a pas pu être programmée.');
    expect(within(dialog).getByRole('button', { name: 'Programmer la séance' })).toBeEnabled();
    expect(within(dialog).getByLabelText(/Début/)).toHaveValue('10:00');
  });

  it('propose de réessayer quand le projet ne se charge pas', async () => {
    let attempts = 0;
    const { user, dialog } = await openModal(
      routes({
        'GET /projects/1': () =>
          ++attempts === 1
            ? jsonResponse({ message: 'x' }, 500)
            : jsonResponse({ data: { id: 1, sub_projects: [{ id: 11, name: 'Seconde 3', is_active: true }], team: [] } }),
      }),
    );

    await user.selectOptions(within(dialog).getByLabelText(/Projet/), '1');
    expect(await within(dialog).findByRole('alert')).toHaveTextContent('Le projet n’a pas pu être chargé.');
    await user.click(within(dialog).getByRole('button', { name: 'Réessayer' }));
    expect(await within(dialog).findByRole('checkbox', { name: 'Seconde 3' })).toBeChecked();
  });

  it('met le focus dans le formulaire à l’ouverture', async () => {
    const { dialog } = await openModal(routes());

    expect(within(dialog).getByLabelText(/Projet/)).toHaveFocus();
  });

  it('se ferme avec Échap sans rien envoyer', async () => {
    const { fetchMock, user } = await openModal(routes());

    await user.keyboard('{Escape}');

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([, options]) => options?.method === 'POST')).toBe(false);
  });
});

describe('Règles de la modale', () => {
  const base = {
    project_id: '1',
    sub_project_ids: [11],
    session_date: '2026-11-09',
    start_time: '10:00',
    end_time: '12:00',
    session_type: 'practice_workshop',
    session_type_detail: '',
    location_id: '2',
    referent_id: '3',
  };
  const team = [{ id: 3, name: 'Léa', member_role: 'lead' }];

  it.each([
    ['fin égale au début', { end_time: '10:00' }, 'end_time'],
    ['aucun groupe', { sub_project_ids: [] }, 'sub_project_ids'],
    ['autre sans précision', { session_type: 'other' }, 'session_type_detail'],
    ['lieu manquant', { location_id: '' }, 'location_id'],
  ])('refuse : %s', (_, override, key) => {
    expect(validateSession({ ...base, ...override }, team)).toHaveProperty([key]);
  });

  it('signale sur le projet un envoi tenté avant son chargement', () => {
    expect(validateSession({ ...base, sub_project_ids: [] }, [], 'loading')).toEqual({ project_id: 'Le projet n’est pas encore chargé.' });
  });

  it('accepte une saisie complète et n’envoie la précision qu’avec « autre »', () => {
    expect(validateSession(base, team)).toEqual({});
    expect(toSessionPayload({ ...base, session_type_detail: 'reste' })).not.toHaveProperty('session_type_detail');
    expect(toSessionPayload({ ...base, session_type: 'other', session_type_detail: ' Sortie ' }).session_type_detail).toBe('Sortie');
  });

  it('regroupe les erreurs de groupes et isole le reste', () => {
    expect(mapSessionErrors({ 'sub_project_ids.1': ['A'], project: ['B'] })).toEqual({
      fields: { sub_project_ids: 'A' },
      others: ['B'],
    });
  });
});

describe('Modifier, annuler, rétablir une séance (#180)', () => {
  const free = {
    ...session(),
    id: 21,
    session_date: '2026-10-20',
    session_type_detail: null,
    logbook_entry: null,
    project: { id: 1, name: 'Arts Danse au Lycée Charles', status: 'active' },
    sub_projects: [{ id: 11, name: 'Seconde 3' }],
  };
  const withEntry = { ...free, id: 22, session_date: '2026-10-21', logbook_entry: { id: 9, status: 'submitted' } };
  const cancelled = { ...free, id: 23, session_date: '2026-10-22', status: 'cancelled' };
  const sessions = (list = [free, withEntry, cancelled]) => () => jsonResponse({ data: list });
  const references = {
    'GET /projects': () => jsonResponse({ data: [{ id: 1, name: 'Arts Danse au Lycée Charles', status: 'active' }] }),
    'GET /locations': () => jsonResponse({ data: [{ id: 2, name: 'Grand studio', city: 'Marseille' }] }),
    'GET /projects/1': () =>
      jsonResponse({
        data: {
          id: 1,
          default_location: { id: 2, name: 'Grand studio' },
          sub_projects: [{ id: 11, name: 'Seconde 3', is_active: true }, { id: 12, name: 'Seconde 7', is_active: true }],
          team: [{ id: 3, name: 'Léa Fournier', member_role: 'lead' }, { id: 7, name: 'Marco Delaunay', member_role: 'occasional' }],
        },
      }),
  };
  afterEach(() => vi.restoreAllMocks());

  it('propose les actions à l’administratrice, seulement sur les séances sans fiche', async () => {
    mockApi({ 'GET /sessions': sessions() });
    renderAt('/planning?mois=2026-10', MOCK_USERS.admin);

    await screen.findAllByText('Arts Danse au Lycée Charles');
    expect(screen.getByRole('button', { name: /Modifier\s*du mardi 20 octobre/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Annuler la séance\s*du mardi 20 octobre/ })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Modifier\s*du mercredi 21 octobre/ })).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Rétablir\s*du jeudi 22 octobre/ })).toBeInTheDocument();
  });

  it('nomme chaque bouton avec le jour, l’horaire et le projet', async () => {
    mockApi({ 'GET /sessions': sessions([free]) });
    renderAt('/planning?mois=2026-10', MOCK_USERS.admin);

    expect(await screen.findByRole('button', { name: /Annuler la séance\s*du mardi 20 octobre, 10:00, Arts Danse au Lycée Charles/ })).toBeInTheDocument();
  });

  it('ne propose rien sur les séances d’un projet archivé', async () => {
    const archived = { ...cancelled, project: { ...cancelled.project, status: 'archived' } };
    mockApi({ 'GET /sessions': sessions([archived]) });
    renderAt('/planning?mois=2026-10', MOCK_USERS.admin);

    await screen.findAllByText('Arts Danse au Lycée Charles');
    expect(screen.queryByRole('button', { name: /Rétablir/ })).not.toBeInTheDocument();
  });

  it('garde affiché un groupe de la séance désactivé depuis', async () => {
    const user = userEvent.setup();
    const withOldGroup = { ...free, sub_projects: [{ id: 99, name: 'Ancien groupe' }] };
    mockApi({ 'GET /sessions': sessions([withOldGroup]), ...references });
    renderAt('/planning?mois=2026-10', MOCK_USERS.admin);

    await user.click(await screen.findByRole('button', { name: /Modifier\s*du mardi 20 octobre/ }));
    const dialog = await screen.findByRole('dialog', { name: 'Modifier la séance' });
    expect(await within(dialog).findByRole('checkbox', { name: 'Ancien groupe (désactivé)' })).toBeChecked();
  });

  it('ne montre aucune action à un intervenant', async () => {
    mockApi({ 'GET /sessions': sessions() });
    renderAt('/planning?mois=2026-10', MOCK_USERS.contributor);

    await screen.findAllByText('Arts Danse au Lycée Charles');
    expect(screen.queryByRole('button', { name: /Modifier|Annuler la séance|Rétablir/ })).not.toBeInTheDocument();
  });

  it('annule après confirmation et relit le planning', async () => {
    const user = userEvent.setup();
    const confirm = vi.spyOn(window, 'confirm').mockReturnValue(true);
    let list = [free];
    const fetchMock = mockApi({
      'GET /sessions': () => jsonResponse({ data: list }),
      'POST /sessions/21/cancel': () => {
        list = [{ ...free, status: 'cancelled' }];
        return jsonResponse({ data: list[0] });
      },
    });
    renderAt('/planning?mois=2026-10', MOCK_USERS.admin);

    await user.click(await screen.findByRole('button', { name: /Annuler la séance\s*du mardi 20 octobre/ }));

    expect(confirm).toHaveBeenCalledWith(expect.stringContaining('marquée « annulée »'));
    expect(await screen.findByRole('button', { name: /Rétablir\s*du mardi 20 octobre/ })).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([url, options]) => options?.method === 'POST' && url.endsWith('/sessions/21/cancel'))).toBe(true);
  });

  it('affiche le refus de rétablir une séance dont le créneau est pris', async () => {
    const user = userEvent.setup();
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    mockApi({
      'GET /sessions': sessions([cancelled]),
      'POST /sessions/23/restore': () =>
        jsonResponse({ message: 'Invalide', errors: { session: ['Cet·te intervenant·e a déjà une séance de 10:00 à 12:00 ce jour-là (BRAISES).'] } }, 422),
    });
    renderAt('/planning?mois=2026-10', MOCK_USERS.admin);

    await user.click(await screen.findByRole('button', { name: /Rétablir\s*du jeudi 22 octobre/ }));

    expect(await screen.findByRole('alert')).toHaveTextContent('déjà une séance de 10:00 à 12:00 ce jour-là (BRAISES).');
  });

  it('modifie une séance dans la fenêtre préremplie, sans changer de projet', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({
      'GET /sessions': sessions([free]),
      ...references,
      'PATCH /sessions/21': () => jsonResponse({ data: { ...free, start_time: '14:00', end_time: '16:00' } }),
    });
    renderAt('/planning?mois=2026-10', MOCK_USERS.admin);

    await user.click(await screen.findByRole('button', { name: /Modifier\s*du mardi 20 octobre/ }));
    const dialog = await screen.findByRole('dialog', { name: 'Modifier la séance' });
    await within(dialog).findByRole('checkbox', { name: 'Seconde 7' });
    expect(within(dialog).getByLabelText(/Projet/)).toBeDisabled();
    expect(within(dialog).getByRole('checkbox', { name: 'Seconde 3' })).toBeChecked();
    expect(within(dialog).getByLabelText(/Intervenant·e/)).toHaveValue(String(free.referent.id));

    await user.clear(within(dialog).getByLabelText(/Début/));
    await user.type(within(dialog).getByLabelText(/Début/), '14:00');
    await user.clear(within(dialog).getByLabelText(/Fin/));
    await user.type(within(dialog).getByLabelText(/Fin/), '16:00');
    await user.click(within(dialog).getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(screen.queryByRole('dialog')).not.toBeInTheDocument());
    const patch = fetchMock.mock.calls.find(([, options]) => options?.method === 'PATCH');
    expect(JSON.parse(patch[1].body)).toMatchObject({ session_date: '2026-10-20', start_time: '14:00', end_time: '16:00', sub_project_ids: [11] });
  });
});
