import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';
import { projectToForm, toChanges } from './EditProjectPage.jsx';

const PROJECT = {
  id: 7,
  name: 'Arts Danse',
  project_type: { id: 2, name: 'Ancien type' },
  planned_sessions: 32,
  start_date: '2026-09-15',
  end_date: '2027-06-19',
  status: 'active',
  archived_at: null,
  default_location: { id: 2, name: 'Grand studio' },
  sub_projects: [{ id: 1, name: 'Seconde 3' }, { id: 2, name: 'Seconde 7' }],
  team: [{ id: 9, name: 'Ancienne intervenante', member_role: 'lead' }],
  funders: [{ id: 1, name: 'DRAC', type: 'state' }],
};

const references = {
  'GET /project-types': () => jsonResponse({ data: [{ id: 1, name: 'Partenariat lycée', is_active: true }] }),
  'GET /locations': () => jsonResponse({ data: [{ id: 2, name: 'Grand studio', postal_code: '13003', city: 'Marseille' }] }),
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
const bodyOf = (fetchMock, method, path) => {
  const call = fetchMock.mock.calls.findLast(([url, options]) => options?.method === method && new URL(url).pathname === `/api${path}`);
  return call ? JSON.parse(call[1].body ?? '{}') : undefined;
};

describe('Modifier un projet : calculs', () => {
  it('n’envoie que les champs modifiés, au format de l’API', () => {
    const initial = projectToForm(PROJECT);
    const form = { ...initial, name: ' Arts Danse 2027 ', end_date: '', principal_user_id: '3', funder_ids: [2, 1] };

    expect(toChanges(initial, form)).toEqual({ name: 'Arts Danse 2027', end_date: null, principal_user_id: 3, funder_ids: [1, 2] });
    expect(toChanges(initial, initial)).toEqual({});
  });
});

describe('Modifier un projet', () => {
  it('préremplit le formulaire, garde le type et l’intervenante devenus inactifs', async () => {
    mockApi({ 'GET /projects/7': () => jsonResponse({ data: PROJECT }), ...references });
    renderAt('/gestion/projets/7/modifier');

    expect(await screen.findByRole('heading', { name: 'Modifier « Arts Danse »' })).toBeInTheDocument();
    expect(field('Nom du projet')).toHaveValue('Arts Danse');
    expect(field('Type de projet')).toHaveValue('2');
    expect(within(field('Intervenant·e principal·e')).getByRole('option', { name: 'Ancienne intervenante' })).toBeInTheDocument();
    expect(field('Intervenant·e principal·e')).toHaveValue('9');
    expect(screen.getByRole('checkbox', { name: 'DRAC' })).toBeChecked();
    expect(screen.getByText('Seconde 7')).toBeInTheDocument();
  });

  it('envoie seulement ce qui change et affiche la confirmation', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({
      'GET /projects/7': () => jsonResponse({ data: PROJECT }),
      'PATCH /projects/7': () => jsonResponse({ data: { ...PROJECT, name: 'Arts Danse 2027' } }),
      ...references,
    });
    renderAt('/gestion/projets/7/modifier');

    await user.clear(await screen.findByLabelText(/Nom du projet/));
    await user.type(field('Nom du projet'), 'Arts Danse 2027');
    await user.click(screen.getByRole('checkbox', { name: 'Ville de Marseille' }));
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Modifications enregistrées.');
    expect(bodyOf(fetchMock, 'PATCH', '/projects/7')).toEqual({ name: 'Arts Danse 2027', funder_ids: [1, 2] });
  });

  it('ne fait aucun appel quand rien n’a changé', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi({ 'GET /projects/7': () => jsonResponse({ data: PROJECT }), ...references });
    renderAt('/gestion/projets/7/modifier');

    await user.click(await screen.findByRole('button', { name: 'Enregistrer' }));

    expect(screen.getByRole('status')).toHaveTextContent('Aucune modification à enregistrer.');
    expect(fetchMock.mock.calls.some(([, options]) => options?.method === 'PATCH')).toBe(false);
  });

  it('affiche les erreurs de l’API sous les champs', async () => {
    const user = userEvent.setup();
    mockApi({
      'GET /projects/7': () => jsonResponse({ data: PROJECT }),
      'PATCH /projects/7': () =>
        jsonResponse({ message: 'Invalide', errors: { end_date: ['La date de fin doit être postérieure ou égale à la date de début.'] } }, 422),
      ...references,
    });
    renderAt('/gestion/projets/7/modifier');

    await user.clear(await screen.findByLabelText(/Séances prévues/));
    await user.type(field('Séances prévues'), '40');
    await user.click(screen.getByRole('button', { name: 'Enregistrer' }));

    expect(await screen.findByText('La date de fin doit être postérieure ou égale à la date de début.')).toBeInTheDocument();
    expect(screen.getByRole('alert')).toHaveTextContent('Le projet n’a pas été modifié');
  });

  it('archive après la saisie du nom, et montre l’erreur si le nom diffère', async () => {
    const user = userEvent.setup();
    let attempts = 0;
    const fetchMock = mockApi({
      'GET /projects/7': () => jsonResponse({ data: PROJECT }),
      'POST /projects/7/archive': () =>
        ++attempts === 1
          ? jsonResponse({ message: 'Invalide', errors: { confirm_name: ['Le nom saisi ne correspond pas au nom du projet.'] } }, 422)
          : jsonResponse({ data: { ...PROJECT, status: 'archived', archived_at: '2026-11-08T10:00:00+00:00' } }),
      ...references,
    });
    renderAt('/gestion/projets/7/modifier');

    await user.click(await screen.findByRole('button', { name: 'Archiver…' }));
    const dialog = screen.getByRole('dialog', { name: 'Archiver le projet' });
    await user.type(within(dialog).getByLabelText(/Nom du projet/), 'Arts');
    await user.click(within(dialog).getByRole('button', { name: 'Archiver' }));
    expect(await within(dialog).findByText('Le nom saisi ne correspond pas au nom du projet.')).toBeInTheDocument();

    await user.clear(within(dialog).getByLabelText(/Nom du projet/));
    await user.type(within(dialog).getByLabelText(/Nom du projet/), 'Arts Danse');
    await user.click(within(dialog).getByRole('button', { name: 'Archiver' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Projet archivé.');
    expect(bodyOf(fetchMock, 'POST', '/projects/7/archive')).toEqual({ confirm_name: 'Arts Danse' });
    expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Restaurer le projet' })).toBeInTheDocument();
  });

  it('restaure un projet archivé', async () => {
    const user = userEvent.setup();
    mockApi({
      'GET /projects/7': () => jsonResponse({ data: { ...PROJECT, status: 'archived' } }),
      'POST /projects/7/restore': () => jsonResponse({ data: PROJECT }),
      ...references,
    });
    renderAt('/gestion/projets/7/modifier');

    await user.click(await screen.findByRole('button', { name: 'Restaurer le projet' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Projet restauré');
    expect(field('Nom du projet')).toHaveValue('Arts Danse');
  });

  it.each([
    ['super-utilisatrice', MOCK_USERS.super_user],
    ['intervenant', MOCK_USERS.contributor],
  ])('refuse l’écran à la %s', (_, user) => {
    renderAt('/gestion/projets/7/modifier', user);

    expect(screen.queryByRole('heading', { name: /Modifier/ })).not.toBeInTheDocument();
  });

  it('montre le bouton Modifier sur le détail à l’administratrice seulement', async () => {
    mockApi({ 'GET /projects/7': () => jsonResponse({ data: PROJECT }), 'GET /projects': () => jsonResponse({ data: [] }) });
    const { unmount } = renderAt('/projets/7');
    expect(await screen.findByRole('link', { name: 'Modifier' })).toHaveAttribute('href', '/gestion/projets/7/modifier');
    unmount();

    renderAt('/projets/7', MOCK_USERS.super_user);
    await screen.findByRole('heading', { name: 'Arts Danse' });
    expect(screen.queryByRole('link', { name: 'Modifier' })).not.toBeInTheDocument();
  });
});
