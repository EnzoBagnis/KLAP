import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from './Context/AuthContext.jsx';
import { MOCK_USERS } from './testUtils.js';
import AppRouter from './Router/AppRouter.jsx';
import { NAV_ITEMS } from './Components/Layout/navigation.js';

function renderAt(path) {
  return render(
    <AuthProvider initialUser={MOCK_USERS.admin}>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

describe('AppRouter', () => {
  it('affiche le tableau de bord sur /', () => {
    renderAt('/');
    expect(screen.getByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
  });

  it.each(NAV_ITEMS.map((item) => [item.path, item.label]))(
    'a une route pour l’entrée du menu %s',
    (path, label) => {
      renderAt(path);
      expect(screen.getByRole('heading', { name: label })).toBeInTheDocument();
    },
  );

  it('affiche une page introuvable sur une route inconnue', () => {
    renderAt('/nimporte-quoi');
    expect(screen.getByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument();
  });
});
