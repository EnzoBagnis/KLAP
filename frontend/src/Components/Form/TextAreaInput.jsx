import { useId } from 'react';

/**
 * Le champ de texte sur plusieurs lignes, au même format que TextInput, avec un compteur de
 * caractères quand on fixe une longueur maximale.
 *
 * @param {object} props
 * @param {string} props.label
 * @param {boolean} [props.required=false]
 * @param {string} [props.error]
 * @param {string} [props.hint]  une aide, qu'on affiche sous le libellé
 * @param {number} [props.maxLength]
 * @param {string} [props.value='']
 */
export default function TextAreaInput({ label, required = false, error, hint, id, maxLength, value = '', ...props }) {
  const generatedId = useId();
  const inputId = id ?? generatedId;
  const errorId = `${inputId}-erreur`;
  const hintId = `${inputId}-aide`;
  const describedBy = [hint && hintId, error && errorId].filter(Boolean).join(' ') || undefined;

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
      {hint && (
        <p id={hintId} className="form-hint">
          {hint}
        </p>
      )}
      <textarea
        id={inputId}
        className={`field-input field-textarea${error ? ' has-error' : ''}`}
        rows={3}
        maxLength={maxLength}
        value={value}
        aria-required={required || undefined}
        aria-invalid={error ? true : undefined}
        aria-describedby={describedBy}
        {...props}
      />
      {maxLength && (
        <p className="field-counter" aria-hidden="true">
          {value.length} / {maxLength}
        </p>
      )}
      {error && (
        <p id={errorId} className="field-error">
          {error}
        </p>
      )}
    </div>
  );
}
