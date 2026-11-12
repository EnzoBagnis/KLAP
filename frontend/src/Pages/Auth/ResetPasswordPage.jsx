import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams, useSearchParams } from 'react-router-dom';
import NewPasswordFields, { newPasswordErrors } from '../../Components/Form/NewPasswordFields.jsx';
import Button from '../../Components/UI/Button.jsx';
import { resetPassword } from '../../Services/PasswordService.js';

/**
 * Le choix d'un nouveau mot de passe (#81), depuis le lien reçu par mail (le jeton et l'adresse
 * sont dans l'URL). On applique les mêmes règles qu'à l'activation. Ensuite, l'API ferme
 * toutes les sessions : on se reconnecte.
 */
export default function ResetPasswordPage() {
  const { token } = useParams();
  const [searchParams] = useSearchParams();
  const email = searchParams.get('email') ?? '';
  const navigate = useNavigate();
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    document.title = 'Nouveau mot de passe · KLAP';
  }, []);

  async function handleSubmit(event) {
    event.preventDefault();
    if (submitting) return;
    setFormError('');
    const found = newPasswordErrors(password, confirmation);
    setErrors(found);
    const firstInvalid = Object.keys(found)[0];
    if (firstInvalid) {
      event.currentTarget.elements[firstInvalid]?.focus();
      return;
    }
    setSubmitting(true);
    try {
      await resetPassword({ token, email, password, passwordConfirmation: confirmation });
      navigate('/connexion', { replace: true, state: { email, passwordReset: true } });
    } catch (failure) {
      setSubmitting(false);
      if (failure.status === 422) {
        const { token: tokenError, email: emailError, ...fields } = failure.errors ?? {};
        setErrors(Object.fromEntries(Object.entries(fields).map(([key, messages]) => [key, messages[0]])));
        if (tokenError || emailError) setFormError((tokenError ?? emailError)[0]);
      } else {
        setFormError(
          failure.status === 429
            ? `Trop de tentatives. Réessayez dans ${failure.retryAfter ?? 60} secondes.`
            : failure.status === 0
              ? failure.message
              : 'Le mot de passe n’a pas pu être changé. Réessayez dans un instant.',
        );
      }
    }
  }

  return (
    <>
      <h1 className="auth-title">Nouveau mot de passe</h1>
      <p className="auth-subtitle">{email ? `Pour le compte ${email}.` : 'Choisissez votre nouveau mot de passe.'}</p>
      <form className="auth-form" onSubmit={handleSubmit} noValidate>
        {formError && (
          <p className="form-alert" role="alert">
            {formError} <Link to="/mot-de-passe-oublie">Demander un nouveau lien</Link>
          </p>
        )}
        <NewPasswordFields
          password={password}
          confirmation={confirmation}
          onPassword={setPassword}
          onConfirmation={setConfirmation}
          errors={errors}
        />
        <Button type="submit" className="btn-block" disabled={submitting}>
          {submitting ? 'Enregistrement…' : 'Changer mon mot de passe'}
        </Button>
      </form>
    </>
  );
}
