import { useId } from 'react';

/**
 * Le champ de formulaire de la maquette : libellé en capitales, astérisque rouge s'il est
 * obligatoire, et message d'erreur qu'on relie au champ pour les lecteurs d'écran. Les autres
 * propriétés (type, value, onChange…) vont telles quelles à l'<input>.
 *
 * @param {object} props
 * @param {string} props.label
 * @param {boolean} [props.required=false]
 * @param {string} [props.error]  le message d'erreur, s'il y en a un
 * @param {string} [props.id]  sinon on en génère un
 */
export default function TextInput({ label, required = false, error, id, 'aria-describedby': describedBy, ...inputProps }) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-erreur`;

  return (
    <div className="field">
      <label htmlFor={inputId} className="field-label">
        {label}
        {required && (
          <span className="field-required" aria-hidden="true">
            {' '}
            *
          </span>
        )}
      </label>
      <input
        id={inputId}
        className={`field-input${error ? ' has-error' : ''}`}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-describedby={[error && errorId, describedBy].filter(Boolean).join(' ') || undefined}
        {...inputProps}
      />
      {error && (
        <p id={errorId} className="field-error">
          {error}
        </p>
      )}
    </div>
  );
}
