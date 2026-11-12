/**
 * Des barres horizontales d'une seule série (#185), qu'on dessine sans bibliothèque.
 * - On écrit la valeur au bout de chaque barre : la couleur ne porte jamais seule l'information.
 * - On masque le graphique au lecteur d'écran : le tableau qui le suit donne les mêmes chiffres.
 * - L'échelle est commune et part de zéro ; `max` permet de la fixer (100 % pour un taux).
 *
 * @param {object} props
 * @param {string} [props.title]
 * @param {Array<{key?: string, label: string, value: number}>} props.rows
 * @param {(value: number) => string} [props.format]  l'écriture d'une valeur
 * @param {number} [props.max]  le haut de l'échelle ; sinon la plus grande valeur
 * @param {string} [props.tone='participants']  la couleur, parmi les classes `tone-…`
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
