import { useId } from 'react';

/**
 * Une note de 1 à 5 (engagement, expression, autonomie). On en fait un groupe de boutons
 * radio : les flèches du clavier marchent d'elles-mêmes, et les lecteurs d'écran lisent
 * « 3 sur 5 ».
 *
 * @param {object} props
 * @param {string} props.label
 * @param {string} props.name
 * @param {number|null} props.value
 * @param {(note: number) => void} props.onChange
 * @param {string} [props.error]
 * @param {boolean} [props.required=false]
 * @param {boolean} [props.disabled=false]
 */
export default function StarRating({ label, name, value, onChange, error, required = false, disabled = false }) {
  const errorId = `${useId()}-erreur`;

  return (
    <fieldset className="field rating" role="radiogroup" aria-invalid={error ? true : undefined} aria-describedby={error ? errorId : undefined} disabled={disabled}>
      <legend className="field-label">
        {label}
        {required && (
          <span className="field-required" aria-hidden="true">
            {' '}
            *
          </span>
        )}
      </legend>
      <div className="rating-options">
        {[1, 2, 3, 4, 5].map((note) => (
          <label key={note} className={`rating-option${value === note ? ' is-selected' : ''}`}>
            <input
              type="radio"
              required={required}
              name={name}
              value={note}
              checked={value === note}
              onChange={() => onChange(note)}
            />
            <span aria-hidden="true">{note}</span>
            <span className="visually-hidden">{`${note} sur 5`}</span>
          </label>
        ))}
      </div>
      {error && (
        <p id={errorId} className="field-error">
          {error}
        </p>
      )}
    </fieldset>
  );
}
