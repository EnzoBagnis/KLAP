import { useId } from 'react';

/**
 * Le menu déroulant, au même format que TextInput (libellé, astérisque, erreur reliée au champ).
 *
 * @param {object} props
 * @param {string} props.label
 * @param {boolean} [props.required=false]
 * @param {string} [props.error]
 * @param {string} [props.placeholder='Choisir…']  le libellé du choix vide
 * @param {Array<{value: string, label: string}>} props.options
 */
export default function SelectInput({ label, required = false, error, id, placeholder, options, ...selectProps }) {
  const generatedId = useId();
  const selectId = id ?? generatedId;
  const errorId = `${selectId}-erreur`;

  return (
    <div className="field">
      <label htmlFor={selectId} className="field-label">
        {label}
        {required && (
          <span className="field-required" aria-hidden="true">
            {' '}
            *
          </span>
        )}
      </label>
      <select
        id={selectId}
        className={`field-input field-select${error ? ' has-error' : ''}`}
        required={required}
        aria-invalid={error ? true : undefined}
        aria-describedby={error ? errorId : undefined}
        {...selectProps}
      >
        <option value="">{placeholder ?? 'Choisir…'}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      {error && (
        <p id={errorId} className="field-error">
          {error}
        </p>
      )}
    </div>
  );
}
