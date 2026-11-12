/**
 * Le bouton d'ouverture du menu, visible sur téléphone seulement. Le menu lui-même est la
 * Sidebar : on n'a qu'une navigation pour tous les écrans.
 *
 * @param {object} props
 * @param {boolean} props.open
 * @param {() => void} props.onToggle
 * @param {string} props.controls  l'identifiant du menu
 * @param {React.Ref<HTMLButtonElement>} props.ref  pour que le gabarit rende le focus au bouton quand le menu se ferme
 */
export default function HamburgerMenu({ open, onToggle, controls, ref }) {
  return (
    <button
      ref={ref}
      type="button"
      className="icon-button menu-toggle"
      aria-expanded={open}
      aria-controls={controls}
      aria-label={open ? 'Fermer le menu' : 'Ouvrir le menu'}
      onClick={onToggle}
    >
      {open ? (
        <svg width="14" height="14" viewBox="0 0 14 14" aria-hidden="true">
          <path d="M1 1l12 12M13 1L1 13" stroke="currentColor" strokeWidth="1.5" />
        </svg>
      ) : (
        <svg width="20" height="14" viewBox="0 0 20 14" aria-hidden="true">
          <path d="M0 1h20M0 7h20M0 13h20" stroke="currentColor" strokeWidth="1.5" />
        </svg>
      )}
    </button>
  );
}
