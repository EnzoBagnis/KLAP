/**
 * L'indicateur d'attente. On lui donne un rôle « status » : les lecteurs d'écran lisent son libellé.
 *
 * @param {object} props
 * @param {string} [props.label='Chargement…']
 */
export default function LoadingSpinner({ label = 'Chargement…' }) {
  return (
    <div className="loading" role="status">
      <span className="loading-dot" aria-hidden="true" />
      <span>{label}</span>
    </div>
  );
}
