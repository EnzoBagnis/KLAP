import Button from '../UI/Button.jsx';

/*
 * Liste des exercices de la séance : de `min` à `max` lignes (2 à 10, contexte client).
 * L'ajout est bloqué au maximum ; les erreurs de l'API arrivent par position (exercises.N).
 */
export default function DynamicExerciseList({ exercises, onChange, errors = {}, min = 2, max = 10, disabled = false }) {
  const update = (index, value) => onChange(exercises.map((label, i) => (i === index ? value : label)));
  const remove = (index) => onChange(exercises.filter((_, i) => i !== index));

  return (
    <fieldset className="field" aria-describedby={errors.exercises ? 'exercices-erreur' : 'exercices-compte'} disabled={disabled}>
      <legend className="field-label">
        Exercices<span className="field-required" aria-hidden="true"> *</span>
      </legend>
      <p id="exercices-compte" className="form-hint">
        {exercises.length} / {max} exercices ({min} au moins)
      </p>
      <ol className="exercise-list">
        {exercises.map((label, index) => (
          <li key={index} className="exercise-row">
            <div className="field">
              <label htmlFor={`exercice-${index}`} className="visually-hidden">{`Exercice ${index + 1}`}</label>
              <input
                id={`exercice-${index}`}
                name={`exercises.${index}`}
                className={`field-input${errors[`exercises.${index}`] ? ' has-error' : ''}`}
                maxLength={200}
                value={label}
                aria-invalid={errors[`exercises.${index}`] ? true : undefined}
                onChange={(event) => update(index, event.target.value)}
              />
              {errors[`exercises.${index}`] && <p className="field-error">{errors[`exercises.${index}`]}</p>}
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
