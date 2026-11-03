import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import AppRouter from './Router/AppRouter.jsx';

describe('AppRouter', () => {
  it("affiche l'accueil sur /", () => {
    render(
      <MemoryRouter initialEntries={['/']}>
        <AppRouter />
      </MemoryRouter>,
    );
    expect(screen.getByRole('heading', { name: 'KLAP · Carnet de bord' })).toBeInTheDocument();
  });

  it('affiche une page introuvable sur une route inconnue', () => {
    render(
      <MemoryRouter initialEntries={['/nimporte-quoi']}>
        <AppRouter />
      </MemoryRouter>,
    );
    expect(screen.getByRole('heading', { name: 'Page introuvable' })).toBeInTheDocument();
  });
});
