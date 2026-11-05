import DynamicExerciseList from '../../../Components/Form/DynamicExerciseList.jsx';
import TextAreaInput from '../../../Components/Form/TextAreaInput.jsx';
import useLogbook from '../../../Hooks/useLogbook.js';
import { MAX_EXERCISES, MIN_EXERCISES } from '../logbookModel.js';

// Étape 3 : la séance (exercices, ajustement, temps forts, ressources).
export default function SessionStep() {
  const { form, set, errors } = useLogbook();

  return (
    <>
      <DynamicExerciseList
        exercises={form.exercises}
        onChange={(exercises) => set('exercises', exercises)}
        errors={errors}
        min={MIN_EXERCISES}
        max={MAX_EXERCISES}
      />

      <fieldset className="field" aria-describedby={errors.adjustment_done ? 'ajustement-erreur' : undefined}>
        <legend className="field-label">
          Ajustement prévu<span className="field-required" aria-hidden="true"> *</span>
        </legend>
        <div className="choice-row">
          {[
            [true, 'Fait'],
            [false, 'Non fait'],
          ].map(([value, label]) => (
            <label key={label} className={`choice${form.adjustment_done === value ? ' is-selected' : ''}`}>
              <input
                type="radio"
                name="adjustment_done"
                checked={form.adjustment_done === value}
                onChange={() => set('adjustment_done', value)}
              />
              {label}
            </label>
          ))}
        </div>
        {errors.adjustment_done && (
          <p id="ajustement-erreur" className="field-error">
            {errors.adjustment_done}
          </p>
        )}
      </fieldset>

      {form.adjustment_done === true && (
        <TextAreaInput
          label="Quel ajustement, et pourquoi ?"
          name="adjustment_detail"
          required
          maxLength={500}
          value={form.adjustment_detail}
          onChange={(event) => set('adjustment_detail', event.target.value)}
          error={errors.adjustment_detail}
        />
      )}

      <TextAreaInput
        label="Temps forts et anecdotes"
        name="highlights"
        required
        maxLength={200}
        hint="S’il n’y en a pas eu, écrivez « Rien à signaler »."
        value={form.highlights}
        onChange={(event) => set('highlights', event.target.value)}
        error={errors.highlights}
      />
      <TextAreaInput
        label="Outils et ressources externes"
        name="tools_resources"
        maxLength={200}
        value={form.tools_resources}
        onChange={(event) => set('tools_resources', event.target.value)}
        error={errors.tools_resources}
      />
    </>
  );
}
