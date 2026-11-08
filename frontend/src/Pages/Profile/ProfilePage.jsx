import { useEffect, useState } from 'react';
import NewPasswordFields, { newPasswordErrors } from '../../Components/Form/NewPasswordFields.jsx';
import TextInput from '../../Components/Form/TextInput.jsx';
import Button from '../../Components/UI/Button.jsx';
import { ROLES } from '../../Context/roles.js';
import useAuth from '../../Hooks/useAuth.js';
import { changePassword } from '../../Services/AuthService.js';
import { formatInstant } from '../Logbook/logbookModel.js';
import '../../assets/css/FormStyles.css';
import '../../assets/css/ProjectStyles.css';

const EMPTY = { current: '', password: '', confirmation: '' };

/*
 * Mon profil (#71), lien du nom en bas du menu. Le nom et l'adresse viennent de l'invitation
 * (contexte client) : ils s'affichent sans se modifier ici. On y change son mot de passe.
 */
export default function ProfilePage() {
  const { user, logout } = useAuth();
  const [form, setForm] = useState(EMPTY);
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [done, setDone] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    document.title = 'Mon profil · KLAP';
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');
    setDone('');
    const found = form.current ? newPasswordErrors(form.password, form.confirmation) : { current_password: 'Saisissez votre mot de passe actuel.' };
    setErrors(found);
    const firstInvalid = Object.keys(found)[0];
    if (firstInvalid) {
      event.currentTarget.elements[firstInvalid]?.focus();
      return;
    }
    setSubmitting(true);
    try {
      setDone(await changePassword(form.current, form.password, form.confirmation));
      setForm(EMPTY);
      setErrors({});
    } catch (error) {
      if (error.status === 401) {
        logout();
        return;
      }
      if (error.status === 422) {
        setErrors(Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]])));
      } else {
        setFormError(
          error.status === 429
            ? `Trop de tentatives. Réessayez dans ${error.retryAfter ?? 60} secondes.`
            : error.status === 0
              ? error.message
              : 'Le mot de passe n’a pas pu être changé. Réessayez dans un instant.',
        );
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (!user) return null;

  return (
    <section className="page">
      <h1 className="page-title">Mon profil</h1>

      <dl className="project-facts profile-facts">
        <div>
          <dt>Nom</dt>
          <dd>{user.name}</dd>
        </div>
        <div>
          <dt>Adresse mail</dt>
          <dd>{user.email}</dd>
        </div>
        <div>
          <dt>Rôle</dt>
          <dd>{user.role_label}</dd>
        </div>
        <div>
          <dt>Téléphone</dt>
          <dd>{user.phone || 'Non renseigné'}</dd>
        </div>
        {user.last_login_at && (
          <div>
            <dt>Dernière connexion</dt>
            <dd>{formatInstant(user.last_login_at)}</dd>
          </div>
        )}
      </dl>
      {user.role !== ROLES.ADMIN && (
        <p className="form-hint">Pour corriger votre nom, votre adresse ou votre téléphone, voyez avec l’administratrice.</p>
      )}

      <h2 className="section-title">Changer mon mot de passe</h2>
      {done && (
        <p className="form-success" role="status">
          {done}
        </p>
      )}
      <form className="project-form profile-password" onSubmit={handleSubmit} noValidate>
        {formError && (
          <p className="form-alert" role="alert">
            {formError}
          </p>
        )}
        <TextInput
          label="Mot de passe actuel"
          name="current_password"
          type="password"
          autoComplete="current-password"
          required
          value={form.current}
          onChange={(event) => setForm({ ...form, current: event.target.value })}
          error={errors.current_password}
        />
        <NewPasswordFields
          password={form.password}
          confirmation={form.confirmation}
          onPassword={(value) => setForm((current) => ({ ...current, password: value }))}
          onConfirmation={(value) => setForm((current) => ({ ...current, confirmation: value }))}
          errors={errors}
        />
        <div>
          <Button type="submit" disabled={submitting}>
            {submitting ? 'Enregistrement…' : 'Changer mon mot de passe'}
          </Button>
        </div>
      </form>
    </section>
  );
}
