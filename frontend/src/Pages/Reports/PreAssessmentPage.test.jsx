import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { afterEach, beforeEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { filenameFrom } from '../../Services/ApiClient.js';
import { MOCK_USERS, jsonResponse, mockApi, screenCalls } from '../../testUtils.js';

const projects = [
  { id: 1, name: 'Arts Danse au Lycée Charles', status: 'active' },
  { id: 2, name: 'AIMER', status: 'archived' },
];

const project = (overrides = {}) => ({
  id: 1,
  name: 'Arts Danse au Lycée Charles',
  status: 'active',
  start_date: '2026-09-15',
  end_date: '2027-06-15',
  funders: [
    { id: 4, name: 'DRAC' },
    { id: 5, name: 'Ville de Marseille' },
  ],
  sub_projects: [],
  team: [],
  ...overrides,
});

const docx = (name = 'pre-bilan-arts-danse-drac-2026-11-09.docx') =>
  new Response(new Blob(['PK']), {
    status: 200,
    headers: {
      'Content-Type': 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'Content-Disposition': `attachment; filename=${name}`,
    },
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

const routes = (overrides = {}) => ({
  'GET /projects': () => jsonResponse({ data: projects }),
  'GET /projects/1': () => jsonResponse({ data: project() }),
  'GET /notifications': () => jsonResponse({ data: [], unread_count: 0 }),
  ...overrides,
});

const reportCall = (fetchMock) => fetchMock.mock.calls.find(([url]) => new URL(url).pathname.endsWith('/pre-report'));

let clicked;

beforeEach(() => {
  clicked = [];
  URL.createObjectURL = vi.fn(() => 'blob:pre-bilan');
  URL.revokeObjectURL = vi.fn();
  vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(function click() {
    clicked.push({ href: this.href, download: this.download });
  });
});

afterEach(() => {
  vi.restoreAllMocks();
});

describe('Pré-bilan', () => {
  it('télécharge le Word du financeur choisi, sur la période donnée', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi(routes({ 'GET /projects/1/pre-report': () => docx() }));
    renderAt('/pre-bilan');

    expect(screen.getByRole('heading', { name: 'Pré-bilan' })).toBeInTheDocument();
    expect(document.title).toBe('Pré-bilan · KLAP');
    // Les projets archivés sont proposés, et signalés.
    expect(await screen.findByRole('option', { name: 'AIMER (archivé)' })).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText(/^Projet/), '1');
    await user.selectOptions(await screen.findByLabelText(/^Financeur/), '4');
    await user.type(screen.getByLabelText('Du'), '2026-09-01');
    await user.type(screen.getByLabelText('Au'), '2026-12-31');
    await user.click(screen.getByRole('button', { name: 'Télécharger le pré-bilan' }));

    expect(await screen.findByRole('status')).toHaveTextContent('Pré-bilan téléchargé : pre-bilan-arts-danse-drac-2026-11-09.docx');
    const [url] = reportCall(fetchMock);
    expect(Object.fromEntries(new URL(url).searchParams)).toEqual({ funder_id: '4', from: '2026-09-01', to: '2026-12-31' });
    expect(clicked).toEqual([{ href: 'blob:pre-bilan', download: 'pre-bilan-arts-danse-drac-2026-11-09.docx' }]);
    expect(screen.getByText(/du 15 septembre 2026 au 15 juin 2027/)).toBeInTheDocument();
  });

  it('part du projet de l’URL et présélectionne son seul financeur', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi(
      routes({
        'GET /projects/1': () => jsonResponse({ data: project({ funders: [{ id: 4, name: 'DRAC' }] }) }),
        'GET /projects/1/pre-report': () => docx(),
      }),
    );
    renderAt('/pre-bilan?projet=1');

    expect(await screen.findByLabelText(/^Financeur/)).toHaveValue('4');
    await user.click(screen.getByRole('button', { name: 'Télécharger le pré-bilan' }));
    await waitFor(() => expect(reportCall(fetchMock)).toBeDefined());
    expect(Object.fromEntries(new URL(reportCall(fetchMock)[0]).searchParams)).toEqual({ funder_id: '4' });
  });

  it('vérifie le formulaire avant l’envoi', async () => {
    const user = userEvent.setup();
    const fetchMock = mockApi(routes());
    renderAt('/pre-bilan?projet=1');

    await screen.findByLabelText(/^Financeur/);
    await user.click(screen.getByRole('button', { name: 'Télécharger le pré-bilan' }));
    expect(screen.getByText('Choisissez le financeur.')).toBeInTheDocument();

    await user.selectOptions(screen.getByLabelText(/^Financeur/), '5');
    await user.type(screen.getByLabelText('Du'), '2026-12-01');
    await user.type(screen.getByLabelText('Au'), '2026-11-01');
    await user.click(screen.getByRole('button', { name: 'Télécharger le pré-bilan' }));
    expect(screen.getByText('La fin de la période doit être après son début.')).toBeInTheDocument();
    expect(reportCall(fetchMock)).toBeUndefined();
  });

  it('affiche le refus de l’API sous le bon champ', async () => {
    const user = userEvent.setup();
    mockApi(
      routes({
        'GET /projects/1/pre-report': () =>
          jsonResponse({ message: 'Invalide', errors: { funder_id: ['Ce financeur ne finance pas ce projet.'] } }, 422),
      }),
    );
    renderAt('/pre-bilan?projet=1');

    await user.selectOptions(await screen.findByLabelText(/^Financeur/), '5');
    await user.click(screen.getByRole('button', { name: 'Télécharger le pré-bilan' }));
    expect(await screen.findByText('Ce financeur ne finance pas ce projet.')).toBeInTheDocument();
    expect(clicked).toEqual([]);
  });

  it('explique quoi faire quand le projet n’a pas de financeur', async () => {
    mockApi(routes({ 'GET /projects/1': () => jsonResponse({ data: project({ funders: [] }) }) }));
    renderAt('/pre-bilan?projet=1');

    expect(await screen.findByText(/Ce projet n’a aucun financeur/)).toBeInTheDocument();
    expect(screen.getByRole('link', { name: 'Ajouter un financeur au projet' })).toHaveAttribute('href', '/gestion/projets/1/modifier');
    expect(screen.queryByRole('button', { name: 'Télécharger le pré-bilan' })).not.toBeInTheDocument();
  });

  it('demande de restaurer un projet archivé avant de lui ajouter un financeur', async () => {
    mockApi(routes({ 'GET /projects/1': () => jsonResponse({ data: project({ status: 'archived', funders: [] }) }) }));
    renderAt('/pre-bilan?projet=1');

    expect(await screen.findByText(/Restaurez d’abord le projet/)).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Ajouter un financeur au projet' })).not.toBeInTheDocument();
  });

  it('n’affiche pas le résultat d’un ancien projet sous le nouveau', async () => {
    const user = userEvent.setup();
    let finish;
    mockApi(
      routes({
        'GET /projects/2': () => jsonResponse({ data: project({ id: 2, name: 'AIMER', funders: [{ id: 6, name: 'Fondation' }, { id: 7, name: 'Ville' }] }) }),
        'GET /projects/1/pre-report': () => new Promise((resolve) => (finish = resolve)),
      }),
    );
    renderAt('/pre-bilan?projet=1');

    await user.selectOptions(await screen.findByLabelText(/^Financeur/), '4');
    await user.click(screen.getByRole('button', { name: 'Télécharger le pré-bilan' }));
    await user.selectOptions(screen.getByLabelText(/^Projet/), '2');
    await screen.findByRole('option', { name: 'Fondation' });
    finish(jsonResponse({ message: 'Invalide', errors: { funder_id: ['Ce financeur ne finance pas ce projet.'] } }, 422));

    // Le bouton du nouveau projet est disponible, sans l'erreur de l'ancien.
    expect(await screen.findByRole('button', { name: 'Télécharger le pré-bilan' })).toBeEnabled();
    await new Promise((resolve) => setTimeout(resolve, 0));
    expect(screen.queryByText('Ce financeur ne finance pas ce projet.')).not.toBeInTheDocument();
  });

  it('renvoie la super-utilisatrice vers l’administratrice pour ajouter un financeur', async () => {
    mockApi(routes({ 'GET /projects/1': () => jsonResponse({ data: project({ funders: [] }) }) }));
    renderAt('/pre-bilan?projet=1', MOCK_USERS.super_user);

    expect(await screen.findByText(/Voyez avec l’administratrice/)).toBeInTheDocument();
    expect(screen.queryByRole('link', { name: 'Ajouter un financeur au projet' })).not.toBeInTheDocument();
  });

  it('n’est pas accessible à un intervenant', () => {
    const fetchMock = mockApi({ 'GET /dashboard': () => jsonResponse({ to_fill: [], upcoming: [], recent_entries: [], follow_up: null }) });
    renderAt('/pre-bilan', MOCK_USERS.contributor);

    expect(screen.queryByRole('heading', { name: 'Pré-bilan' })).not.toBeInTheDocument();
    expect(screenCalls(fetchMock).filter(([url]) => new URL(url).pathname.startsWith('/api/projects'))).toHaveLength(0);
  });

  it('propose le pré-bilan depuis la page d’un projet, pas à un intervenant', async () => {
    mockApi(routes());
    const { unmount } = renderAt('/projets/1');
    await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles', level: 1 });
    // Deux liens « Pré-bilan » : celui du menu, et celui du projet, qui le présélectionne.
    expect(screen.getAllByRole('link', { name: 'Pré-bilan' }).map((link) => link.getAttribute('href'))).toContain('/pre-bilan?projet=1');
    unmount();

    mockApi(routes());
    renderAt('/projets/1', MOCK_USERS.contributor);
    await screen.findByRole('heading', { name: 'Arts Danse au Lycée Charles', level: 1 });
    // Le menu de l'intervenant n'a pas d'entrée Pré-bilan, et la page projet pas de bouton.
    expect(screen.queryByRole('link', { name: 'Pré-bilan' })).not.toBeInTheDocument();
  });
});

describe('Nom du fichier téléchargé', () => {
  it.each([
    ['attachment; filename=pre-bilan.docx', 'pre-bilan.docx'],
    ['attachment; filename="pre bilan.docx"', 'pre bilan.docx'],
    ["attachment; filename*=UTF-8''pr%C3%A9-bilan.docx", 'pré-bilan.docx'],
    ['attachment; filename="../../etc/passwd"', 'passwd'],
    [null, null],
    ['attachment', null],
  ])('%s donne %s', (header, expected) => {
    expect(filenameFrom(header)).toBe(expected);
  });
});
