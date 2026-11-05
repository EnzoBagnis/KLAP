import TextAreaInput from '../../../Components/Form/TextAreaInput.jsx';
import useLogbook from '../../../Hooks/useLogbook.js';

// Étape 2 : objectifs. Aucun pré-remplissage, à la demande de la cliente.
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
