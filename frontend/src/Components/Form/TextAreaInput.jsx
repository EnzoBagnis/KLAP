import { useId } from 'react';

// Champ texte multiligne, au même format que TextInput, avec un compteur de caractères.
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
