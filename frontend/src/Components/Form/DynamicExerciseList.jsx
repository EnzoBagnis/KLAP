import Button from '../UI/Button.jsx';

/*
 * Liste des exercices de la séance : de `min` à `max` lignes (2 à 10, contexte client).
 * L'ajout est bloqué au maximum ; les erreurs de l'API arrivent par position (exercises.N).
 */
export default function DynamicExerciseList({ exercises, onChange, errors = {}, min = 2, max = 10, disabled = false }) {
  const update = (index, value) => onChange(exercises.map((label, i) => (i === index ? value : label)));
  const remove = (index) => onChange(exercises.filter((_, i) => i !== index));
  // Les lignes vides ne sont pas envoyées : l'erreur exercises.N de l'API vise la N-ième ligne remplie.
  const filled = exercises.map((label) => label.trim() !== '');
  const apiIndex = (index) => (filled[index] ? filled.slice(0, index).filter(Boolean).length : -1);
  const errorOf = (index) => errors[`exercises.${apiIndex(index)}`];

  return (
    <fieldset className="field" aria-describedby={errors.exercises ? 'exercices-erreur' : 'exercices-compte'} disabled={disabled}>
      <legend className="field-label">
        Exercices<span className="field-required" aria-hidden="true"> *</span>
      </legend>
      <p id="exercices-compte" className="form-hint">
        {filled.filter(Boolean).length} / {max} exercices renseignés ({min} au moins)
      </p>
      <ol className="exercise-list">
        {exercises.map((label, index) => (
          <li key={index} className="exercise-row">
            <div className="field">
              <label htmlFor={`exercice-${index}`} className="visually-hidden">{`Exercice ${index + 1}`}</label>
              <input
                id={`exercice-${index}`}
                name={`exercises.${index}`}
                className={`field-input${errorOf(index) ? ' has-error' : ''}`}
                maxLength={200}
                value={label}
                aria-invalid={errorOf(index) ? true : undefined}
                onChange={(event) => update(index, event.target.value)}
              />
              {errorOf(index) && <p className="field-error">{errorOf(index)}</p>}
            </div>
            <Button variant="ghost" aria-label={`Retirer l’exercice ${index + 1}`} onClick={() => remove(index)}>
              Retirer
            </Button>
          </li>
        ))}
      </ol>
      <Button variant="line" disabled={exercises.length >= max} onClick={() => onChange([...exercises, ''])}>
        {exercises.length >= max ? `${max} exercices au plus` : '+ Ajouter un exercice'}
      </Button>
      {errors.exercises && (
        <p id="exercices-erreur" className="field-error">
          {errors.exercises}
        </p>
      )}
    </fieldset>
  );
}
