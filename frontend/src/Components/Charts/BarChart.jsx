/*
 * Barres horizontales d'une seule série (#185), sans bibliothèque.
 * - La valeur est écrite au bout de chaque barre : la couleur ne porte jamais seule l'information.
 * - Le graphique est masqué au lecteur d'écran : le tableau qui le suit donne les mêmes chiffres.
 * - Échelle commune partant de zéro ; `max` permet de la fixer (100 % pour un taux).
 */
export default function BarChart({ title, rows, format = (value) => value.toLocaleString('fr-FR'), max, tone = 'participants' }) {
  if (rows.length === 0) return null;
  const top = max ?? Math.max(...rows.map((row) => row.value), 0);
  return (
    <figure className="bar-chart" aria-hidden="true">
      {title && <figcaption className="bar-chart-title">{title}</figcaption>}
      <ul className="bar-chart-rows">
        {rows.map((row) => (
          <li key={row.key ?? row.label} className="bar-chart-row" title={`${row.label} : ${format(row.value)}`}>
            <span className="bar-chart-label">{row.label}</span>
            <span className="bar-chart-track">
              <span
                className={`bar-chart-bar tone-${tone}`}
                style={{ width: top > 0 ? `${Math.min(100, (row.value / top) * 100)}%` : '0%' }}
              />
            </span>
            <span className="bar-chart-value">{format(row.value)}</span>
          </li>
        ))}
      </ul>
    </figure>
  );
}
