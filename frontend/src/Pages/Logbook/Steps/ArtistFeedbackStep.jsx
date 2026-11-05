import TextAreaInput from '../../../Components/Form/TextAreaInput.jsx';
import useLogbook from '../../../Hooks/useLogbook.js';

// Étape 5 : les artistes (acquis, bilan de l'intervenant·e).
export default function ArtistFeedbackStep() {
  const { form, set, errors } = useLogbook();

  return (
    <>
      <TextAreaInput
        label="Les acquis"
        name="learnings"
        maxLength={200}
        value={form.learnings}
        onChange={(event) => set('learnings', event.target.value)}
        error={errors.learnings}
      />
      <TextAreaInput
        label="Bilan de l’intervenant·e"
        name="artist_review"
        maxLength={200}
        hint="Ce qui fonctionne, ce qu’il faut surveiller ou améliorer."
        value={form.artist_review}
        onChange={(event) => set('artist_review', event.target.value)}
        error={errors.artist_review}
      />
    </>
  );
}
