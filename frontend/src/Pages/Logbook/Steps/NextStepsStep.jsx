import TextAreaInput from '../../../Components/Form/TextAreaInput.jsx';
import useLogbook from '../../../Hooks/useLogbook.js';

// Étape 6 : la suite (à reprendre, pistes abandonnées, maintien en classe, notes).
export default function NextStepsStep() {
  const { form, set, errors } = useLogbook();

  return (
    <>
      <TextAreaInput
        label="À reprendre"
        name="next_steps"
        required
        maxLength={500}
        value={form.next_steps}
        onChange={(event) => set('next_steps', event.target.value)}
        error={errors.next_steps}
      />
      <TextAreaInput
        label="Pistes à abandonner, et pourquoi"
        name="dropped_leads"
        maxLength={500}
        value={form.dropped_leads}
        onChange={(event) => set('dropped_leads', event.target.value)}
        error={errors.dropped_leads}
      />
      <label className="checkbox">
        <input
          type="checkbox"
          name="keep_in_class"
          checked={form.keep_in_class}
          onChange={(event) => set('keep_in_class', event.target.checked)}
        />
        Maintien en classe avec l’équipe enseignante
      </label>
      <TextAreaInput
        label="Notes complémentaires"
        name="additional_notes"
        maxLength={500}
        value={form.additional_notes}
        onChange={(event) => set('additional_notes', event.target.value)}
        error={errors.additional_notes}
      />
    </>
  );
}
