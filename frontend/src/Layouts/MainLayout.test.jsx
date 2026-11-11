import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MemoryRouter } from 'react-router-dom';
import { AuthProvider } from '../Context/AuthContext.jsx';
import { MOCK_USERS } from '../testUtils.js';
import AppRouter from '../Router/AppRouter.jsx';
import NotificationBell from '../Components/Layout/NotificationBell.jsx';
import { initials } from '../Components/Layout/Sidebar.jsx';

function renderLayout(user, path = '/') {
  return render(
    <AuthProvider initialUser={user}>
      <MemoryRouter initialEntries={[path]}>
        <AppRouter />
      </MemoryRouter>
    </AuthProvider>,
  );
}

const mainNav = () => screen.getByRole('navigation', { name: 'Navigation principale' });
const menuButton = () => screen.getByRole('button', { name: /le menu$/ });

describe('Navigation principale', () => {
  it("n'existe qu'en un seul exemplaire, partagé par l'ordinateur et le téléphone", () => {
    renderLayout(MOCK_USERS.admin);
    expect(screen.getAllByRole('navigation')).toHaveLength(1);
  });

  it("affiche les six entrées à l'administratrice", () => {
    renderLayout(MOCK_USERS.admin);
    expect(within(mainNav()).getAllByRole('link')).toHaveLength(6);
    expect(within(mainNav()).getByRole('link', { name: /Gestion/ })).toBeInTheDocument();
  });

  it("masque Statistiques, Pré-bilan et Gestion à l'intervenant et renumérote", () => {
    renderLayout(MOCK_USERS.contributor);
    const links = within(mainNav()).getAllByRole('link');
    expect(links.map((link) => link.textContent)).toEqual([
      '01Tableau de bord',
      '02Projets',
      '03Planning',
    ]);
  });

  it("marque l'entrée de la page courante", () => {
    renderLayout(MOCK_USERS.admin, '/planning');
    expect(within(mainNav()).getByRole('link', { name: /Planning/ })).toHaveAttribute(
      'aria-current',
      'page',
    );
    expect(within(mainNav()).getByRole('link', { name: /Tableau de bord/ })).not.toHaveAttribute(
      'aria-current',
    );
  });

  it("affiche le nom, les initiales et le libellé de rôle fourni par l'API", () => {
    renderLayout(MOCK_USERS.contributor);
    expect(screen.getByText('Léa Fournier')).toBeInTheDocument();
    expect(screen.getByText('LF')).toBeInTheDocument();
    expect(screen.getByText('Intervenant')).toBeInTheDocument();
  });

  it('affiche la super-utilisatrice avec son libellé, sans Gestion', () => {
    renderLayout(MOCK_USERS.super_user);
    expect(screen.getByText('Super-utilisatrice')).toBeInTheDocument();
    expect(within(mainNav()).getAllByRole('link')).toHaveLength(5);
    expect(within(mainNav()).queryByRole('link', { name: /Gestion/ })).not.toBeInTheDocument();
  });

  it('renvoie vers la connexion après la déconnexion', async () => {
    const user = userEvent.setup();
    renderLayout(MOCK_USERS.admin);
    await user.click(screen.getByRole('button', { name: 'Se déconnecter' }));
    expect(screen.queryByText('Mariette Travard')).not.toBeInTheDocument();
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
    expect(screen.getByRole('heading', { name: 'Connexion' })).toBeInTheDocument();
  });
});

describe('Accès aux écrans', () => {
  it('renvoie un visiteur non connecté vers la connexion', () => {
    renderLayout(null, '/projets');
    expect(screen.getByRole('heading', { name: 'Connexion' })).toBeInTheDocument();
    expect(screen.queryByRole('navigation')).not.toBeInTheDocument();
  });

  it.each(['/gestion', '/statistiques', '/pre-bilan'])(
    "renvoie l'intervenant à l'accueil s'il tape %s",
    (path) => {
      renderLayout(MOCK_USERS.contributor, path);
      expect(screen.getByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
    },
  );

  it('renvoie la super-utilisatrice à l’accueil sur /gestion', () => {
    renderLayout(MOCK_USERS.super_user, '/gestion');
    expect(screen.getByRole('heading', { name: 'Tableau de bord' })).toBeInTheDocument();
  });

  it("laisse l'administratrice ouvrir Gestion", () => {
    renderLayout(MOCK_USERS.admin, '/gestion');
    expect(screen.getByRole('heading', { name: 'Gestion' })).toBeInTheDocument();
  });
});

describe('Menu mobile', () => {
  it("s'ouvre et se ferme avec le bouton", async () => {
    const user = userEvent.setup();
    renderLayout(MOCK_USERS.admin);
    expect(menuButton()).toHaveAttribute('aria-expanded', 'false');
    expect(menuButton()).toHaveAttribute('aria-controls', 'menu-principal');

    await user.click(menuButton());
    expect(menuButton()).toHaveAttribute('aria-expanded', 'true');
    expect(menuButton()).toHaveAccessibleName('Fermer le menu');
    expect(document.getElementById('menu-principal')).toHaveClass('is-open');

    await user.click(menuButton());
    expect(menuButton()).toHaveAttribute('aria-expanded', 'false');
  });

  it('se ferme quand on choisit une entrée', async () => {
    const user = userEvent.setup();
    renderLayout(MOCK_USERS.admin);
    await user.click(menuButton());
    await user.click(within(mainNav()).getByRole('link', { name: /Projets/ }));
    expect(menuButton()).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('heading', { name: 'Projets' })).toBeInTheDocument();
  });

  it("met le focus sur la première entrée à l'ouverture et fige la page", async () => {
    const user = userEvent.setup();
    renderLayout(MOCK_USERS.admin);
    await user.click(menuButton());
    expect(within(mainNav()).getAllByRole('link')[0]).toHaveFocus();
    expect(document.body.style.overflow).toBe('hidden');
  });

  it('se ferme avec Échap, rend le focus au bouton et libère la page', async () => {
    const user = userEvent.setup();
    renderLayout(MOCK_USERS.admin);
    await user.click(menuButton());
    await user.keyboard('{Escape}');
    expect(menuButton()).toHaveAttribute('aria-expanded', 'false');
    expect(menuButton()).toHaveFocus();
    expect(document.body.style.overflow).toBe('');
  });

  it('se ferme quand on ouvre les notifications ou le profil', async () => {
    const user = userEvent.setup();
    renderLayout(MOCK_USERS.admin);
    await user.click(menuButton());
    const header = screen.getByRole('banner');
    await user.click(within(header).getByRole('link', { name: 'Notifications' }));
    expect(menuButton()).toHaveAttribute('aria-expanded', 'false');

    await user.click(menuButton());
    await user.click(screen.getByRole('link', { name: /Mariette Travard/ }));
    expect(menuButton()).toHaveAttribute('aria-expanded', 'false');
    expect(screen.getByRole('heading', { name: 'Mon profil' })).toBeInTheDocument();
  });
});

describe('NotificationBell', () => {
  const renderBell = (count) =>
    render(
      <MemoryRouter>
        <NotificationBell count={count} />
      </MemoryRouter>,
    );

  it('sans notification, ni pastille ni compteur', () => {
    renderBell(0);
    expect(screen.getByRole('link', { name: 'Notifications' })).toBeInTheDocument();
    expect(screen.queryByTestId('notification-dot')).not.toBeInTheDocument();
  });

  it('annonce le nombre de notifications non lues', () => {
    renderBell(3);
    expect(screen.getByRole('link', { name: 'Notifications, 3 non lues' })).toBeInTheDocument();
    expect(screen.getByTestId('notification-dot')).toBeInTheDocument();
  });
});

describe('initials', () => {
  it('prend la première lettre des deux premiers mots', () => {
    expect(initials('Léa Fournier')).toBe('LF');
    expect(initials('  emilie   garetier ')).toBe('EG');
    expect(initials('Mariette')).toBe('M');
    expect(initials('')).toBe('');
  });
});

describe('Lien d’évitement (#233)', () => {
  it('est le premier élément au clavier et donne le focus au contenu principal', async () => {
    const user = userEvent.setup();
    renderLayout(MOCK_USERS.admin);
    await user.tab();
    const skip = screen.getByRole('link', { name: 'Aller au contenu' });
    expect(skip).toHaveFocus();

    await user.keyboard('{Enter}');
    expect(screen.getByRole('main')).toHaveFocus();
    // Le Tab suivant part du contenu, sans repasser par le menu.
    await user.tab();
    expect(screen.getByRole('main').contains(document.activeElement)).toBe(true);
  });
});
