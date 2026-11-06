import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter, useLocation } from 'react-router-dom';
import { afterEach, beforeEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi, screenCalls } from '../../testUtils.js';
import { todayKey } from '../Planning/sessionLabels.js';
import { formFromEntry, payloadFromForm, stepOfError, stepsWithErrors } from './logbookModel.js';

const entry = (overrides = {}) => ({
  id: 7,
  status: 'draft',
  edit_deadline: '2026-10-15',
  submitted_at: null,
  editable: true,
  author: { id: 3, name: 'Léa Fournier' },
  session: {
    id: 1,
    session_date: '2026-10-05',
    start_time: '10:00',
    end_time: '12:00',
    session_type: 'practice_workshop',
    session_type_detail: null,
    project: { id: 1, name: 'Arts Danse au Lycée Charles' },
    location: { id: 2, name: 'Grand studio' },
    sub_projects: [{ id: 11, name: 'Seconde 3', attendees: null }],
  },
  objectives: null,
  adjustment_done: null,
  adjustment_detail: null,
  highlights: null,
  tools_resources: null,
  learnings: null,
  artist_review: null,
  next_steps: null,
  dropped_leads: null,
  keep_in_class: false,
  additional_notes: null,
  evaluation: null,
  exercises: [],
  ...overrides,
});

function Where() {
  const location = useLocation();
  return <output data-testid="where">{location.pathname + location.search}</output>;
}

function renderAt(path, user = MOCK_USERS.contributor) {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
        <Where />
      </MemoryRouter>
    </AuthProvider>,
  );
}

const bodyOf = (fetchMock, method) => {
  const call = fetchMock.mock.calls.find(([, options]) => options?.method === method);
  return call && JSON.parse(call[1].body);
};

beforeEach(() => {
  vi.useFakeTimers({ toFake: ['Date'] });
  vi.setSystemTime(new Date(2026, 9, 6, 9, 0));
});

afterEach(() => {
  vi.useRealTimers();
  vi.unstubAllGlobals();
});

describe('Modèle de la fiche', () => {
  it('convertit la fiche de l’API en formulaire et retour', () => {
    const form = formFromEntry(entry({ exercises: ['Marche', 'Miroir'], objectives: 'Écoute' }));
    expect(form.exercises).toEqual(['Marche', 'Miroir']);
    const payload = payloadFromForm({ ...form, highlights: '   ' });
    expect(payload.objectives).toBe('Écoute');
    expect(payload.highlights).toBeNull();
    expect(payload.attendees).toEqual([{ sub_project_id: 11, count: null }]);
  });

  it('propose d’emblée deux lignes d’exercice, sans les envoyer vides', () => {
    const form = formFromEntry(entry());
    expect(form.exercises).toEqual(['', '']);
    expect(payloadFromForm(form).exercises).toEqual([]);
    expect(formFromEntry(entry({ exercises: ['Marche', 'Miroir', 'Duo'] })).exercises).toHaveLength(3);
    expect(formFromEntry(entry({ editable: false, exercises: ['Marche'] })).exercises).toEqual(['Marche']);
  });

  it('donne la date du jour à l’heure de Paris', () => {
    expect(todayKey(new Date('2026-10-06T22:30:00Z'))).toBe('2026-10-07');
    expect(todayKey(new Date('2026-10-06T21:59:00Z'))).toBe('2026-10-06');
  });

  it('ne garde le détail que lorsque la réponse le demande', () => {
    const form = formFromEntry(entry());
    const payload = payloadFromForm({
      ...form,
      adjustment_done: false,
      adjustment_detail: 'oublié',
      exercises: ['Marche', '  '],
      evaluation: { ...form.evaluation, feeling: 'joy', feeling_detail: 'oublié' },
    });
    expect(payload.adjustment_detail).toBeNull();
    expect(payload.evaluation.feeling_detail).toBeNull();
    expect(payload.exercises).toEqual(['Marche']);
  });

  it.each([
    ['session', 1],
    ['objectives', 2],
    ['exercises.3', 3],
    ['adjustment_detail', 3],
    ['evaluation.feeling', 4],
    ['attendees.0.count', 4],
    ['artist_review', 5],
    ['next_steps', 6],
  ])('range l’erreur %s à l’étape %i', (key, step) => {
    expect(stepOfError(key)).toBe(step);
  });

  it('liste les étapes en erreur une seule fois, dans l’ordre', () => {
    expect(stepsWithErrors({ next_steps: 'x', exercises: 'x', 'exercises.0': 'x' })).toEqual([3, 6]);
  });
});

describe('Ouverture depuis le planning', () => {
  it('reprend la fiche existante', async () => {
    mockApi({
      'GET /sessions/1/logbook-entry': () => jsonResponse({ data: entry() }),
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
    });
    renderAt('/planning/seances/1/fiche');
    expect(await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' })).toBeInTheDocument();
    expect(screen.getByTestId('where')).toHaveTextContent('/fiches/7');
  });

  it('crée la fiche quand elle n’existe pas', async () => {
    const fetchMock = mockApi({
      'GET /sessions/1/logbook-entry': () => jsonResponse({ message: 'Not found' }, 404),
      'POST /sessions/1/logbook-entry': () => jsonResponse({ data: entry() }, 201),
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
    });
    renderAt('/planning/seances/1/fiche');
    await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' });
    expect(screenCalls(fetchMock).map(([, o]) => o?.method ?? 'GET')).toEqual(['GET', 'POST', 'GET']);
  });

  it('relit la fiche si une autre ouverture l’a créée entre-temps', async () => {
    let reads = 0;
    mockApi({
      'GET /sessions/1/logbook-entry': () =>
        ++reads === 1 ? jsonResponse({ message: 'Not found' }, 404) : jsonResponse({ data: entry() }),
      'POST /sessions/1/logbook-entry': () => jsonResponse({ message: 'Une fiche existe déjà.' }, 409),
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
    });
    renderAt('/planning/seances/1/fiche');
    await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' });
  });

  it('affiche le refus de l’API', async () => {
    mockApi({
      'GET /sessions/1/logbook-entry': () => jsonResponse({ message: 'Not found' }, 404),
      'POST /sessions/1/logbook-entry': () =>
        jsonResponse({ message: 'Invalid', errors: { session: ['Une séance annulée n’a pas de fiche.'] } }, 422),
    });
    renderAt('/planning/seances/1/fiche');
    expect(await screen.findByRole('alert')).toHaveTextContent('Une séance annulée n’a pas de fiche.');
  });
});

describe('Fiche de séance', () => {
  it('rappelle la séance et l’échéance à la première étape', async () => {
    mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ data: entry() }) });
    renderAt('/fiches/7');
    expect(await screen.findByText('Grand studio')).toBeInTheDocument();
    expect(screen.getByText(/Modifiable jusqu’au 15\/10\/2026 inclus/)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Généralités/ })).toHaveAttribute('aria-current', 'step');
  });

  it('enregistre le brouillon en changeant d’étape, seulement s’il a changé', async () => {
    const fetchMock = mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'PATCH /logbook-entries/7': (options) =>
        jsonResponse({ data: entry({ objectives: JSON.parse(options.body).objectives }) }),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=2');
    await user.type(await screen.findByLabelText(/Objectifs pédagogiques/), 'Écouter le groupe');
    await user.click(screen.getByRole('button', { name: 'Étape suivante' }));
    await screen.findByRole('heading', { name: '3. La séance' });
    expect(bodyOf(fetchMock, 'PATCH').objectives).toBe('Écouter le groupe');

    await user.click(screen.getByRole('button', { name: 'Étape suivante' }));
    await screen.findByRole('heading', { name: '4. Les participants' });
    expect(fetchMock.mock.calls.filter(([, o]) => o?.method === 'PATCH')).toHaveLength(1);
  });

  it('reste sur l’étape quand l’enregistrement échoue', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'PATCH /logbook-entries/7': () =>
        jsonResponse({ message: 'Invalid', errors: { objectives: ['400 caractères au plus.'] } }, 422),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=2');
    await user.type(await screen.findByLabelText(/Objectifs pédagogiques/), 'Trop long');
    await user.click(screen.getByRole('button', { name: 'Étape suivante' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Étapes concernées : Objectifs.');
    expect(screen.getByText('400 caractères au plus.')).toBeInTheDocument();
    expect(screen.getByRole('heading', { name: '2. Objectifs' })).toBeInTheDocument();
  });

  it('soumet la fiche et signale les étapes incomplètes', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'POST /logbook-entries/7/submit': () =>
        jsonResponse(
          { message: 'Invalid', errors: { exercises: ['Au moins 2 exercices.'], next_steps: ['Obligatoire.'] } },
          422,
        ),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=6');
    await user.click(await screen.findByRole('button', { name: 'Soumettre la fiche' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Étapes concernées : La séance, La suite.');
    expect(screen.getByRole('button', { name: /La séance.*corriger/ })).toBeInTheDocument();
    expect(screen.getByText('Obligatoire.')).toBeInTheDocument();
  });

  it('confirme la soumission', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'POST /logbook-entries/7/submit': () => jsonResponse({ data: entry({ status: 'submitted' }) }),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=6');
    await user.click(await screen.findByRole('button', { name: 'Soumettre la fiche' }));
    expect(await screen.findByText(/^Fiche soumise\./)).toHaveAttribute('role', 'status');
    expect(screen.getByRole('button', { name: 'Modifications enregistrées' })).toBeDisabled();
  });

  it('montre une fiche verrouillée en lecture seule', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry({ status: 'locked', editable: false }) }),
    });
    renderAt('/fiches/7?etape=2');
    expect(await screen.findByLabelText(/Objectifs pédagogiques/)).toBeDisabled();
    expect(screen.queryByRole('button', { name: /Enregistrer/ })).not.toBeInTheDocument();
  });

  it('signale une fiche introuvable', async () => {
    mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ message: 'Not found' }, 404) });
    renderAt('/fiches/7');
    expect(await screen.findByRole('heading', { name: 'Fiche introuvable' })).toBeInTheDocument();
  });
});

describe('Lien depuis le planning', () => {
  const session = (overrides) => ({
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

  it('apparaît le jour même de la séance', async () => {
    mockApi({ 'GET /sessions': () => jsonResponse({ data: [session({ session_date: '2026-10-06' })] }) });
    renderAt('/planning?mois=2026-10');
    expect(await screen.findByRole('link', { name: /Remplir la fiche/ })).toBeInTheDocument();
  });

  it('n’apparaît pas sur une séance à venir', async () => {
    mockApi({ 'GET /sessions': () => jsonResponse({ data: [session({ session_date: '2026-10-07' })] }) });
    renderAt('/planning?mois=2026-10');
    await screen.findByText('Arts Danse au Lycée Charles');
    expect(screen.queryByRole('link', { name: /Remplir la fiche/ })).not.toBeInTheDocument();
  });

  it('n’apparaît que pour l’intervenant référent d’une séance non annulée', async () => {
    mockApi({
      'GET /sessions': () =>
        jsonResponse({
          data: [session(), session({ id: 2, status: 'cancelled' }), session({ id: 3, referent: { id: 9, name: 'Autre' } })],
        }),
    });
    renderAt('/planning?mois=2026-10');
    await screen.findAllByText('Arts Danse au Lycée Charles');
    const links = screen.getAllByRole('link', { name: /Remplir la fiche/ });
    expect(links).toHaveLength(1);
    expect(links[0]).toHaveAttribute('href', '/planning/seances/1/fiche');
  });
});

it('n’envoie rien quand rien n’a changé', async () => {
  const fetchMock = mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ data: entry() }) });
  const user = userEvent.setup();
  renderAt('/fiches/7');
  await user.click(await screen.findByRole('button', { name: 'Étape suivante' }));
  await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('etape=2'));
  expect(screenCalls(fetchMock)).toHaveLength(1);
});

describe('Cas d’erreur et garde-fous', () => {
  it('envoie les présents dans l’ordre des groupes affichés', () => {
    const form = formFromEntry(entry());
    const payload = payloadFromForm({ ...form, attendees: { 11: '4', 12: '' } }, [12, 11]);
    expect(payload.attendees).toEqual([
      { sub_project_id: 12, count: null },
      { sub_project_id: 11, count: 4 },
    ]);
  });

  it('déconnecte quand la session a expiré pendant l’enregistrement', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'PATCH /logbook-entries/7': () => jsonResponse({ message: 'Unauthenticated.' }, 401),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=2');
    await user.type(await screen.findByLabelText(/Objectifs pédagogiques/), 'x');
    await user.click(screen.getByRole('button', { name: 'Étape suivante' }));
    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('/connexion'));
  });

  it('passe en lecture seule quand l’API refuse la modification', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'PATCH /logbook-entries/7': () => jsonResponse({ message: 'Le projet est archivé.' }, 403),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=2');
    await user.type(await screen.findByLabelText(/Objectifs pédagogiques/), 'x');
    await user.click(screen.getByRole('button', { name: 'Enregistrer le brouillon' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Le projet est archivé.');
    expect(screen.getByLabelText(/Objectifs pédagogiques/)).toBeDisabled();
  });

  it('ne soumet pas quand l’enregistrement préalable échoue', async () => {
    const fetchMock = mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'PATCH /logbook-entries/7': () =>
        jsonResponse({ message: 'Invalid', errors: { next_steps: ['2000 caractères au plus.'] } }, 422),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=6');
    await user.type(await screen.findByLabelText(/À reprendre/), 'x');
    await user.click(screen.getByRole('button', { name: 'Soumettre la fiche' }));
    expect(await screen.findByText('2000 caractères au plus.')).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([url]) => url.endsWith('/submit'))).toBe(false);
  });

  it('n’enregistre pas une fiche soumise en changeant d’étape, mais par le bouton', async () => {
    const fetchMock = mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry({ status: 'submitted' }) }),
      'PATCH /logbook-entries/7': (options) =>
        jsonResponse({ data: entry({ status: 'submitted', objectives: JSON.parse(options.body).objectives }) }),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=2');
    await user.type(await screen.findByLabelText(/Objectifs pédagogiques/), 'Écoute');
    await user.click(screen.getByRole('button', { name: 'Étape suivante' }));
    await screen.findByRole('heading', { name: '3. La séance' });
    expect(bodyOf(fetchMock, 'PATCH')).toBeUndefined();
    await user.click(screen.getByRole('button', { name: 'Enregistrer les modifications' }));
    await screen.findByRole('button', { name: 'Modifications enregistrées' });
    expect(bodyOf(fetchMock, 'PATCH').objectives).toBe('Écoute');
  });

  it('montre en lecture seule la fiche d’un autre intervenant', async () => {
    mockApi({
      'GET /logbook-entries/7': () =>
        jsonResponse({ data: entry({ editable: false, author: { id: 9, name: 'Paul Martin' } }) }),
    });
    renderAt('/fiches/7', MOCK_USERS.admin);
    expect(await screen.findByText('Fiche de Paul Martin, en lecture seule.')).toBeInTheDocument();
  });

  it('enregistre avant de revenir au planning', async () => {
    const fetchMock = mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'PATCH /logbook-entries/7': (options) =>
        jsonResponse({ data: entry({ objectives: JSON.parse(options.body).objectives }) }),
      'GET /sessions': () => jsonResponse({ data: [] }),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=2');
    await user.type(await screen.findByLabelText(/Objectifs pédagogiques/), 'Écoute');
    await user.click(screen.getByRole('link', { name: 'Retour au planning' }));
    await waitFor(() => expect(screen.getByTestId('where')).toHaveTextContent('/planning?mois=2026-10'));
    expect(bodyOf(fetchMock, 'PATCH').objectives).toBe('Écoute');
  });

  it('demande confirmation avant de quitter par le menu avec une saisie non enregistrée', async () => {
    mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ data: entry() }) });
    const confirm = vi.fn(() => false);
    vi.stubGlobal('confirm', confirm);
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=2');
    await user.type(await screen.findByLabelText(/Objectifs pédagogiques/), 'x');
    await user.click(screen.getAllByRole('link', { name: 'Projets' })[0]);
    expect(confirm).toHaveBeenCalled();
    expect(screen.getByTestId('where')).toHaveTextContent('/fiches/7');
  });

  it('explique qu’un autre intervenant a déjà la fiche de la séance', async () => {
    mockApi({
      'GET /sessions/1/logbook-entry': () => jsonResponse({ message: 'Not found' }, 404),
      'POST /sessions/1/logbook-entry': () => jsonResponse({ message: 'Une fiche existe déjà.' }, 409),
    });
    renderAt('/planning/seances/1/fiche');
    expect(await screen.findByRole('alert')).toHaveTextContent('rédigée par un autre intervenant');
  });
});

describe('Déverrouillage par l’administratrice', () => {
  const locked = (overrides = {}) =>
    entry({ status: 'locked', editable: false, unlockable: true, edit_deadline: '2026-10-15', ...overrides });

  it('n’affiche le bouton que si l’API l’autorise', async () => {
    mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ data: locked({ unlockable: false }) }) });
    renderAt('/fiches/7', MOCK_USERS.admin);
    await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles' });
    expect(screen.queryByRole('button', { name: 'Rouvrir la fiche' })).not.toBeInTheDocument();
  });

  it('rouvre la fiche après confirmation et indique la nouvelle échéance', async () => {
    const fetchMock = mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: locked() }),
      'POST /logbook-entries/7/unlock': () =>
        jsonResponse({
          data: locked({
            status: 'submitted',
            unlockable: false,
            edit_deadline: '2026-10-30',
            unlocked_at: '2026-10-20T07:00:00+00:00',
            unlocked_by: { id: 1, name: 'Mariette Travard' },
          }),
        }),
    });
    vi.stubGlobal('confirm', vi.fn(() => true));
    const user = userEvent.setup();
    renderAt('/fiches/7', MOCK_USERS.admin);
    await user.click(await screen.findByRole('button', { name: 'Rouvrir la fiche' }));
    expect(await screen.findByText(/Fiche rouverte : Léa Fournier peut la modifier jusqu’au 30\/10\/2026 inclus\./)).toBeInTheDocument();
    expect(screen.getByText('Rouverte le 20/10/2026 par Mariette Travard.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Rouvrir la fiche' })).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.filter(([url]) => url.endsWith('/unlock'))).toHaveLength(1);
  });

  it('ne fait rien si l’administratrice annule', async () => {
    const fetchMock = mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ data: locked() }) });
    vi.stubGlobal('confirm', vi.fn(() => false));
    const user = userEvent.setup();
    renderAt('/fiches/7', MOCK_USERS.admin);
    await user.click(await screen.findByRole('button', { name: 'Rouvrir la fiche' }));
    expect(screenCalls(fetchMock)).toHaveLength(1);
  });

  it('affiche le refus de l’API', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: locked() }),
      'POST /logbook-entries/7/unlock': () => jsonResponse({ message: 'La fiche n’est pas verrouillée.' }, 409),
    });
    vi.stubGlobal('confirm', vi.fn(() => true));
    const user = userEvent.setup();
    renderAt('/fiches/7', MOCK_USERS.admin);
    await user.click(await screen.findByRole('button', { name: 'Rouvrir la fiche' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('La fiche n’est pas verrouillée.');
  });

  it('rend la fiche modifiable à son auteur après réouverture', async () => {
    mockApi({
      'GET /logbook-entries/7': () =>
        jsonResponse({
          data: entry({ status: 'submitted', edit_deadline: '2026-10-30', unlocked_at: '2026-10-20T07:00:00+00:00', unlocked_by: { id: 1, name: 'Mariette Travard' } }),
        }),
    });
    renderAt('/fiches/7?etape=2');
    expect(await screen.findByLabelText(/Objectifs pédagogiques/)).toBeEnabled();
    expect(screen.getByText(/Modifiable jusqu’au 30\/10\/2026 inclus/)).toBeInTheDocument();
  });
});

describe('Liste des exercices', () => {
  it('compte les lignes remplies et place l’erreur de l’API sous la bonne ligne', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: entry() }),
      'PATCH /logbook-entries/7': () =>
        jsonResponse({ message: 'Invalid', errors: { 'exercises.0': ['Libellé refusé.'] } }, 422),
    });
    const user = userEvent.setup();
    renderAt('/fiches/7?etape=3');
    expect(await screen.findByText('0 / 10 exercices renseignés (2 au moins)')).toBeInTheDocument();
    await user.type(screen.getByRole('textbox', { name: 'Exercice 2' }), 'Duo');
    expect(screen.getByText('1 / 10 exercices renseignés (2 au moins)')).toBeInTheDocument();

    await user.click(screen.getByRole('button', { name: 'Enregistrer le brouillon' }));
    expect(await screen.findByText('Libellé refusé.')).toBeInTheDocument();
    expect(screen.getByRole('textbox', { name: 'Exercice 2' })).toHaveAttribute('aria-invalid', 'true');
    expect(screen.getByRole('textbox', { name: 'Exercice 1' })).not.toHaveAttribute('aria-invalid');
  });
});

describe('Demande de réouverture par l’auteur', () => {
  const locked = (overrides = {}) =>
    entry({ status: 'locked', editable: false, unlock_requestable: true, unlock_requested_at: null, ...overrides });

  it('propose la demande sur une fiche verrouillée, après confirmation', async () => {
    const fetchMock = mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: locked() }),
      'POST /logbook-entries/7/unlock-request': () =>
        jsonResponse({ data: locked({ unlock_requestable: false, unlock_requested_at: '2026-10-20T07:00:00+00:00' }) }),
    });
    vi.stubGlobal('confirm', vi.fn(() => true));
    const user = userEvent.setup();
    renderAt('/fiches/7');
    await user.click(await screen.findByRole('button', { name: 'Demander la réouverture' }));

    expect(await screen.findByText('Demande envoyée : l’administratrice est prévenue.')).toBeInTheDocument();
    expect(screen.getByText('Demande de réouverture envoyée le 20/10/2026.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Demander la réouverture' })).not.toBeInTheDocument();
    expect(screenCalls(fetchMock).filter(([url]) => url.endsWith('/unlock-request'))).toHaveLength(1);
  });

  it('n’envoie rien si l’auteur annule', async () => {
    const fetchMock = mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ data: locked() }) });
    vi.stubGlobal('confirm', vi.fn(() => false));
    const user = userEvent.setup();
    renderAt('/fiches/7');
    await user.click(await screen.findByRole('button', { name: 'Demander la réouverture' }));
    expect(screenCalls(fetchMock)).toHaveLength(1);
  });

  it('affiche le refus de l’API', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: locked() }),
      'POST /logbook-entries/7/unlock-request': () =>
        jsonResponse({ message: 'Une demande de réouverture est déjà en attente pour cette fiche.' }, 409),
    });
    vi.stubGlobal('confirm', vi.fn(() => true));
    const user = userEvent.setup();
    renderAt('/fiches/7');
    await user.click(await screen.findByRole('button', { name: 'Demander la réouverture' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('déjà en attente');
  });

  it('rappelle une demande déjà envoyée, sans bouton', async () => {
    mockApi({
      'GET /logbook-entries/7': () =>
        jsonResponse({ data: locked({ unlock_requestable: false, unlock_requested_at: '2026-10-18T07:00:00+00:00' }) }),
    });
    renderAt('/fiches/7');
    expect(await screen.findByText('Demande de réouverture envoyée le 18/10/2026.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Demander la réouverture' })).not.toBeInTheDocument();
  });

  it('affiche l’absence d’administratrice disponible', async () => {
    mockApi({
      'GET /logbook-entries/7': () => jsonResponse({ data: locked() }),
      'POST /logbook-entries/7/unlock-request': () =>
        jsonResponse({ message: 'Aucune administratrice ne peut recevoir la demande pour le moment.' }, 503),
    });
    vi.stubGlobal('confirm', vi.fn(() => true));
    const user = userEvent.setup();
    renderAt('/fiches/7');
    await user.click(await screen.findByRole('button', { name: 'Demander la réouverture' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Aucune administratrice');
  });

  it('relit la fiche quand la demande a déjà été faite ailleurs', async () => {
    let reads = 0;
    mockApi({
      'GET /logbook-entries/7': () =>
        jsonResponse({
          data: ++reads === 1 ? locked() : locked({ unlock_requestable: false, unlock_requested_at: '2026-10-19T07:00:00+00:00' }),
        }),
      'POST /logbook-entries/7/unlock-request': () =>
        jsonResponse({ message: 'Une demande de réouverture est déjà en attente pour cette fiche.' }, 409),
    });
    vi.stubGlobal('confirm', vi.fn(() => true));
    const user = userEvent.setup();
    renderAt('/fiches/7');
    await user.click(await screen.findByRole('button', { name: 'Demander la réouverture' }));
    expect(await screen.findByText('Demande de réouverture envoyée le 19/10/2026.')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Demander la réouverture' })).not.toBeInTheDocument();
  });

  it('ne propose pas la demande à l’administratrice', async () => {
    mockApi({ 'GET /logbook-entries/7': () => jsonResponse({ data: locked({ unlock_requestable: false, unlockable: true }) }) });
    renderAt('/fiches/7', MOCK_USERS.admin);
    expect(await screen.findByRole('button', { name: 'Rouvrir la fiche' })).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Demander la réouverture' })).not.toBeInTheDocument();
  });
});
