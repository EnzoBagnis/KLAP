// Indicateur d'attente accessible : le libellé est lu par les lecteurs d'écran.
export default function LoadingSpinner({ label = 'Chargement…' }) {
  return (
    <div className="loading" role="status">
      <span className="loading-dot" aria-hidden="true" />
      <span>{label}</span>
    </div>
  );
}
