import SelectInput from '../../../Components/Form/SelectInput.jsx';
import StarRating from '../../../Components/Form/StarRating.jsx';
import TextInput from '../../../Components/Form/TextInput.jsx';
import useLogbook from '../../../Hooks/useLogbook.js';
import { FEELINGS, INTERACTION_KEYWORDS, PROGRESS } from '../logbookModel.js';

// Étape 4 : les participants (notes de 1 à 5, interaction, sensation, évolution, présents).
export default function ParticipantsStep() {
  const { entry, form, set, errors } = useLogbook();
  const evaluation = form.evaluation;

  function addKeyword(keyword) {
    const current = evaluation.interaction.trim();
    if (current.toLowerCase().split(/,\s*/).includes(keyword)) return;
    const next = current ? `${current}, ${keyword}` : keyword;
    if (next.length <= 100) set('evaluation.interaction', next);
  }

  return (
    <>
      <fieldset className="field" aria-describedby={errors.attendees ? 'presents-erreur' : undefined}>
        <legend className="field-label">
          Présents par groupe<span className="field-required" aria-hidden="true"> *</span>
        </legend>
        <div className="attendees-grid">
          {entry.session.sub_projects.map((group, index) => (
            <TextInput
              key={group.id}
              label={group.name}
              name={`attendees.${index}.count`}
              type="number"
              min={0}
              inputMode="numeric"
              value={form.attendees[group.id] ?? ''}
              onChange={(event) => set('attendees', { ...form.attendees, [group.id]: event.target.value })}
              error={errors[`attendees.${index}.count`]}
            />
          ))}
        </div>
        {errors.attendees && (
          <p id="presents-erreur" className="field-error">
            {errors.attendees}
          </p>
        )}
      </fieldset>

      {[
        ['engagement', 'Engagement'],
        ['expression', 'Expression'],
        ['autonomy', 'Autonomie'],
      ].map(([key, label]) => (
        <StarRating
          key={key}
          label={label}
          name={`evaluation.${key}`}
          required
          value={evaluation[key]}
          onChange={(note) => set(`evaluation.${key}`, note)}
          error={errors[`evaluation.${key}`]}
        />
      ))}

      <TextInput
        label="Interaction"
        name="evaluation.interaction"
        required
        maxLength={100}
        value={evaluation.interaction}
        onChange={(event) => set('evaluation.interaction', event.target.value)}
        error={errors['evaluation.interaction']}
      />
      <div className="keyword-row" role="group" aria-label="Mots-clés suggérés">
        {INTERACTION_KEYWORDS.map((keyword) => (
          <button key={keyword} type="button" className="keyword" onClick={() => addKeyword(keyword)}>
            + {keyword}
          </button>
        ))}
      </div>

      <SelectInput
        label="Sensations"
        name="evaluation.feeling"
        required
        value={evaluation.feeling}
        onChange={(event) => {
          set('evaluation.feeling', event.target.value);
          if (event.target.value !== 'other') set('evaluation.feeling_detail', '');
        }}
        error={errors['evaluation.feeling']}
        options={FEELINGS.map(([value, label]) => ({ value, label }))}
      />
      {evaluation.feeling === 'other' && (
        <TextInput
          label="Précisez la sensation"
          name="evaluation.feeling_detail"
          required
          maxLength={100}
          value={evaluation.feeling_detail}
          onChange={(event) => set('evaluation.feeling_detail', event.target.value)}
          error={errors['evaluation.feeling_detail']}
        />
      )}

      <SelectInput
        label="Par rapport à la séance précédente"
        name="evaluation.progress"
        required
        value={evaluation.progress}
        onChange={(event) => set('evaluation.progress', event.target.value)}
        error={errors['evaluation.progress']}
        options={PROGRESS.map(([value, label]) => ({ value, label }))}
      />
    </>
  );
}
