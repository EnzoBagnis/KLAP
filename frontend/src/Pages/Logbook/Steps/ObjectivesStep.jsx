import TextAreaInput from '../../../Components/Form/TextAreaInput.jsx';
import useLogbook from '../../../Hooks/useLogbook.js';

/** Étape 2 : les objectifs. On ne pré-remplit rien, à la demande de la cliente (retour du 16/09). */
export default function ObjectivesStep() {
  const { form, set, errors } = useLogbook();

  return (
    <TextAreaInput
      label="Objectifs pédagogiques et artistiques"
      name="objectives"
      required
      maxLength={400}
      rows={5}
      value={form.objectives}
      onChange={(event) => set('objectives', event.target.value)}
      error={errors.objectives}
    />
  );
}
