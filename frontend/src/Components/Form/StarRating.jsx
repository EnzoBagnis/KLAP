import { useId } from 'react';

/*
 * Note de 1 à 5 (engagement, expression, autonomie). Un groupe de boutons radio :
 * flèches du clavier natives, lecture « 3 sur 5 » par les lecteurs d'écran.
 */
export default function StarRating({ label, name, value, onChange, error, required = false, disabled = false }) {
  const errorId = `${useId()}-erreur`;

  return (
    <fieldset className="field rating" aria-describedby={error ? errorId : undefined} disabled={disabled}>
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
              aria-invalid={error ? true : undefined}
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
