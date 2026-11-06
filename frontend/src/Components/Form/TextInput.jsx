import { useId } from 'react';

// Champ de formulaire de la maquette : libellé en capitales, astérisque rouge si obligatoire,
// message d'erreur relié au champ pour les lecteurs d'écran.
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
