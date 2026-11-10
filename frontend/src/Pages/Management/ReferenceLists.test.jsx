import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import AppRouter from '../../Router/AppRouter.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';

const FUNDERS = [
  { id: 1, name: 'DRAC', type: 'state', is_active: true, projects_count: 2 },
  { id: 2, name: 'Ancien mécène', type: 'foundation', is_active: false, projects_count: 0 },
];
const TYPES = [{ id: 5, name: 'Partenariat lycée', is_active: true, projects_count: 1 }];
const PLACES = [{ id: 9, name: 'Grand studio', address: null, postal_code: '13003', city: 'Marseille', is_klap: true, is_active: true }];
const lists = {
  'GET /funders': () => jsonResponse({ data: FUNDERS }),
  'GET /project-types': () => jsonResponse({ data: TYPES }),
  'GET /locations': () => jsonResponse({ data: PLACES }),
};

function renderPage(role = 'admin') {
  return render(
    <AuthProvider initialUser={MOCK_USERS[role]}>
      <MemoryRouter initialEntries={['/gestion/listes']}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}
// Nom d'un bouton suivi du nom de l'élément (texte caché) : jsdom les colle sans espace.
const named = (action, item) => new RegExp(`^${action}\\s*${item}$`);
const section = (name) => screen.getByRole('region', { name });
const calls = (fetchMock, method) => fetchMock.mock.calls.filter(([, options]) => options?.method === method);

describe('Gestion › Listes (#249)', () => {
  afterEach(() => vi.restoreAllMocks());

  it('montre les trois listes, avec l’usage et l’état de chaque élément', async () => {
    const fetchMock = mockApi(lists);
    renderPage();
    expect(await screen.findByRole('button', { name: named('Modifier', 'Ancien mécène') })).toBeInTheDocument();
    const funders = section('Financeurs');
    expect(within(funders).getByText('État · 2 projets')).toBeInTheDocument();
    expect(within(funders).getByText('Désactivé')).toBeInTheDocument();
    expect(within(funders).getByRole('button', { name: named('Réactiver', 'Ancien mécène') })).toBeInTheDocument();
    expect(within(section('Types de projet')).getByText('1 projet')).toBeInTheDocument();
    expect(within(section('Lieux')).getByText('13003 Marseille · lieu de KLAP')).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([url]) => url.endsWith('/locations?tous=1'))).toBe(true);
    expect(screen.getByRole('link', { name: 'Listes' })).toHaveAttribute('aria-current', 'page');
  });

  it('ajoute un financeur, après les contrôles du formulaire', async () => {
    let created = false;
    const fetchMock = mockApi({
      ...lists,
      'GET /funders': () => jsonResponse({ data: created ? [...FUNDERS, { id: 3, name: 'Région Sud', type: 'local_authority', is_active: true, projects_count: 0 }] : FUNDERS }),
      'POST /funders': () => {
        created = true;
        return jsonResponse({ data: { id: 3, name: 'Région Sud', type: 'local_authority', is_active: true } }, 201);
      },
    });
    const user = userEvent.setup();
    renderPage();
    await screen.findByRole('button', { name: named('Modifier', 'DRAC') });
    const funders = section('Financeurs');
    await user.click(within(funders).getByRole('button', { name: '+ Ajouter un financeur' }));
    expect(within(funders).getByLabelText(/Nom du financeur/)).toHaveFocus();
    await user.click(within(funders).getByRole('button', { name: 'Ajouter' }));
    expect(within(funders).getByText('Indiquez le nom du financeur.')).toBeInTheDocument();
    expect(within(funders).getByText('Choisissez la catégorie du financeur.')).toBeInTheDocument();
    expect(calls(fetchMock, 'POST')).toHaveLength(0);

    await user.type(within(funders).getByLabelText(/Nom du financeur/), '  Région Sud ');
    await user.selectOptions(within(funders).getByLabelText(/Catégorie/), 'Collectivité');
    await user.click(within(funders).getByRole('button', { name: 'Ajouter' }));
    expect(await within(funders).findByRole('status')).toHaveTextContent('Financeur « Région Sud » ajouté.');
    expect(JSON.parse(calls(fetchMock, 'POST')[0][1].body)).toEqual({ name: 'Région Sud', type: 'local_authority' });
    // La liste est relue : l'élément ajouté arrive complet, avec son usage.
    expect(await within(funders).findByText('Collectivité · Aucun projet')).toBeInTheDocument();
    expect(within(funders).getByRole('heading', { name: 'Financeurs' })).toHaveFocus();
  });

  it('corrige un lieu sur place et affiche l’erreur de l’API sur le bon champ', async () => {
    let attempt = 0;
    const fetchMock = mockApi({
      ...lists,
      'PATCH /locations/9': () =>
        ++attempt === 1
          ? jsonResponse({ message: 'invalide', errors: { name: ['Ce lieu existe déjà pour ce code postal.'] } }, 422)
          : jsonResponse({ data: { ...PLACES[0], name: 'Grand studio KLAP' } }),
    });
    const user = userEvent.setup();
    renderPage();
    await screen.findByRole('button', { name: named('Modifier', 'Grand studio') });
    const places = section('Lieux');
    await user.click(within(places).getByRole('button', { name: named('Modifier', 'Grand studio') }));
    const field = within(places).getByLabelText(/Nom du lieu/);
    expect(field).toHaveValue('Grand studio');
    await user.clear(field);
    await user.type(field, 'Petit studio');
    await user.click(within(places).getByRole('button', { name: 'Enregistrer' }));
    expect(await within(places).findByText('Ce lieu existe déjà pour ce code postal.')).toBeInTheDocument();
    await user.clear(field);
    await user.type(field, 'Grand studio KLAP');
    await user.click(within(places).getByRole('button', { name: 'Enregistrer' }));
    expect(await within(places).findByRole('button', { name: named('Modifier', 'Grand studio KLAP') })).toBeInTheDocument();
    expect(JSON.parse(calls(fetchMock, 'PATCH')[1][1].body)).toMatchObject({ name: 'Grand studio KLAP', postal_code: '13003', city: 'Marseille' });
  });

  it('refuse un code postal à 4 chiffres sans appeler l’API', async () => {
    const fetchMock = mockApi(lists);
    const user = userEvent.setup();
    renderPage();
    await screen.findByRole('button', { name: named('Modifier', 'Grand studio') });
    const places = section('Lieux');
    await user.click(within(places).getByRole('button', { name: '+ Ajouter un lieu' }));
    await user.type(within(places).getByLabelText(/Nom du lieu/), 'Gymnase');
    await user.type(within(places).getByLabelText(/Code postal/), '1300');
    await user.type(within(places).getByLabelText(/^Ville/), 'Marseille');
    await user.click(within(places).getByRole('button', { name: 'Ajouter' }));
    expect(within(places).getByText('Le code postal doit compter 5 chiffres.')).toBeInTheDocument();
    expect(calls(fetchMock, 'POST')).toHaveLength(0);
  });

  it('désactive un type de projet après confirmation, et n’envoie rien si on annule', async () => {
    const confirm = vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
    const fetchMock = mockApi({ ...lists, 'PATCH /project-types/5': () => jsonResponse({ data: { id: 5, name: 'Partenariat lycée', is_active: false } }) });
    const user = userEvent.setup();
    renderPage();
    await screen.findByRole('button', { name: named('Modifier', 'Partenariat lycée') });
    const types = section('Types de projet');
    await user.click(within(types).getByRole('button', { name: named('Désactiver', 'Partenariat lycée') }));
    expect(calls(fetchMock, 'PATCH')).toHaveLength(0);
    await user.click(within(types).getByRole('button', { name: named('Désactiver', 'Partenariat lycée') }));
    expect(await within(types).findByText('Désactivé')).toBeInTheDocument();
    expect(JSON.parse(calls(fetchMock, 'PATCH')[0][1].body)).toEqual({ is_active: false });
    expect(confirm).toHaveBeenCalledTimes(2);
  });

  it('n’est pas accessible à une intervenante', async () => {
    mockApi({ ...lists, 'GET /dashboard': () => jsonResponse({ data: {} }) });
    renderPage('contributor');
    expect(screen.queryByRole('heading', { name: 'Financeurs' })).not.toBeInTheDocument();
  });
});
