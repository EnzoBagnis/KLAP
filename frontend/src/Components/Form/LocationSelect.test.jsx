import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, vi } from 'vitest';
import { AuthProvider } from '../../Context/AuthContext.jsx';
import { MOCK_USERS, jsonResponse, mockApi } from '../../testUtils.js';
import LocationSelect from './LocationSelect.jsx';

const STUDIO = { id: 2, name: 'Grand studio', postal_code: '13003', city: 'Marseille' };

function Harness({ onSubmit = vi.fn() }) {
  const [locations, setLocations] = useState([STUDIO]);
  const [value, setValue] = useState('');
  return (
    <form
      onSubmit={(event) => {
        event.preventDefault();
        onSubmit(value);
      }}
    >
      <LocationSelect
        label="Lieu"
        name="location_id"
        value={value}
        onChange={setValue}
        onCreated={(place) => setLocations((current) => [...current, place])}
        locations={locations}
      />
      <output data-testid="value">{value}</output>
      <button type="submit">Envoyer</button>
    </form>
  );
}

function renderHarness(props) {
  return render(
    <AuthProvider initialUser={MOCK_USERS.contributor}>
      <Harness {...props} />
    </AuthProvider>,
  );
}

const bodyOf = (fetchMock) => JSON.parse(fetchMock.mock.calls.find(([, o]) => o?.method === 'POST')[1].body);

afterEach(() => vi.unstubAllGlobals());

describe('Menu des lieux', () => {
  it('choisit un lieu existant', async () => {
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), '2');
    expect(screen.getByTestId('value')).toHaveTextContent('2');
    expect(screen.queryByRole('group', { name: 'Nouveau lieu' })).not.toBeInTheDocument();
  });

  it('crée un lieu, l’ajoute au menu et le sélectionne', async () => {
    const fetchMock = mockApi({
      'POST /locations': () => jsonResponse({ data: { id: 9, name: 'Théâtre du Merlan', postal_code: '13014', city: 'Marseille' } }, 201),
    });
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    const panel = screen.getByRole('group', { name: 'Nouveau lieu' });
    expect(within(panel).getByLabelText(/Nom du lieu/)).toHaveFocus();

    await user.type(within(panel).getByLabelText(/Nom du lieu/), ' Théâtre du Merlan ');
    await user.type(within(panel).getByLabelText(/Code postal/), '13014');
    await user.type(within(panel).getByLabelText(/Ville/), 'Marseille');
    await user.click(within(panel).getByRole('button', { name: 'Ajouter le lieu' }));

    expect(await screen.findByRole('option', { name: 'Théâtre du Merlan (Marseille)' })).toBeInTheDocument();
    expect(screen.getByTestId('value')).toHaveTextContent('9');
    expect(screen.getByLabelText('Lieu')).toHaveValue('9');
    expect(screen.queryByRole('group', { name: 'Nouveau lieu' })).not.toBeInTheDocument();
    expect(bodyOf(fetchMock)).toEqual({ name: 'Théâtre du Merlan', postal_code: '13014', city: 'Marseille', address: null });
  });

  it('vérifie les champs avant l’envoi', async () => {
    const fetchMock = mockApi({});
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Code postal/), '1300');
    await user.click(screen.getByRole('button', { name: 'Ajouter le lieu' }));
    expect(screen.getByText('Indiquez le nom du lieu.')).toBeInTheDocument();
    expect(screen.getByText('Le code postal doit compter 5 chiffres.')).toBeInTheDocument();
    expect(screen.getByText('Indiquez la ville.')).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('affiche le refus de l’API sous le bon champ', async () => {
    mockApi({
      'POST /locations': () => jsonResponse({ message: 'Invalid', errors: { name: ['Ce lieu existe déjà pour ce code postal.'] } }, 422),
    });
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Nom du lieu/), 'Grand studio');
    await user.type(screen.getByLabelText(/Code postal/), '13003');
    await user.type(screen.getByLabelText(/Ville/), 'Marseille');
    await user.click(screen.getByRole('button', { name: 'Ajouter le lieu' }));
    expect(await screen.findByText('Ce lieu existe déjà pour ce code postal.')).toBeInTheDocument();
    expect(screen.getByLabelText(/Nom du lieu/)).toHaveAttribute('aria-invalid', 'true');
  });

  it('signale une erreur réseau', async () => {
    vi.stubGlobal('fetch', vi.fn(async () => {
      throw new TypeError('Failed to fetch');
    }));
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Nom du lieu/), 'Salle');
    await user.type(screen.getByLabelText(/Code postal/), '13003');
    await user.type(screen.getByLabelText(/Ville/), 'Marseille');
    await user.click(screen.getByRole('button', { name: 'Ajouter le lieu' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible de joindre le serveur');
  });

  it('Entrée crée le lieu sans envoyer le formulaire parent', async () => {
    mockApi({
      'POST /locations': () => jsonResponse({ data: { id: 9, name: 'Salle', postal_code: '13003', city: 'Marseille' } }, 201),
    });
    const onSubmit = vi.fn();
    const user = userEvent.setup();
    renderHarness({ onSubmit });
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Nom du lieu/), 'Salle');
    await user.type(screen.getByLabelText(/Code postal/), '13003');
    await user.type(screen.getByLabelText(/Ville/), 'Marseille{Enter}');
    expect(await screen.findByRole('option', { name: 'Salle (Marseille)' })).toBeInTheDocument();
    expect(onSubmit).not.toHaveBeenCalled();
  });

  it('Entrée sur « Annuler » annule au lieu de créer', async () => {
    const fetchMock = mockApi({});
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Nom du lieu/), 'Salle');
    screen.getByRole('button', { name: 'Annuler' }).focus();
    await user.keyboard('{Enter}');
    expect(screen.queryByRole('group', { name: 'Nouveau lieu' })).not.toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('place le focus sur le champ refusé par l’API', async () => {
    mockApi({
      'POST /locations': () => jsonResponse({ message: 'Invalid', errors: { name: ['Ce lieu existe déjà pour ce code postal.'] } }, 422),
    });
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Nom du lieu/), 'Grand studio');
    await user.type(screen.getByLabelText(/Code postal/), '13003');
    await user.type(screen.getByLabelText(/Ville/), 'Marseille');
    await user.click(screen.getByRole('button', { name: 'Ajouter le lieu' }));
    await screen.findByText('Ce lieu existe déjà pour ce code postal.');
    await waitFor(() => expect(screen.getByLabelText(/Nom du lieu/)).toHaveFocus());
  });

  it('montre un refus sans champ identifié', async () => {
    mockApi({ 'POST /locations': () => jsonResponse({ message: 'Données invalides.', errors: {} }, 422) });
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Nom du lieu/), 'Salle');
    await user.type(screen.getByLabelText(/Code postal/), '13003');
    await user.type(screen.getByLabelText(/Ville/), 'Marseille');
    await user.click(screen.getByRole('button', { name: 'Ajouter le lieu' }));
    expect(await screen.findByRole('alert')).toHaveTextContent('Données invalides.');
  });

  it('choisir un lieu existant referme la saisie d’un nouveau', async () => {
    mockApi({});
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.selectOptions(screen.getByLabelText('Lieu'), '2');
    expect(screen.queryByRole('group', { name: 'Nouveau lieu' })).not.toBeInTheDocument();
    expect(screen.getByTestId('value')).toHaveTextContent('2');
  });

  it('Annuler referme sans rien créer et rend le focus au menu', async () => {
    const fetchMock = mockApi({});
    const user = userEvent.setup();
    renderHarness();
    await user.selectOptions(screen.getByLabelText('Lieu'), 'Nouveau lieu…');
    await user.type(screen.getByLabelText(/Nom du lieu/), 'Salle');
    await user.click(screen.getByRole('button', { name: 'Annuler' }));
    expect(screen.queryByRole('group', { name: 'Nouveau lieu' })).not.toBeInTheDocument();
    expect(screen.getByLabelText('Lieu')).toHaveFocus();
    expect(screen.getByLabelText('Lieu')).toHaveValue('');
    expect(fetchMock).not.toHaveBeenCalled();
  });
});
