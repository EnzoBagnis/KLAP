import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import NewPasswordFields, { newPasswordErrors } from '../../Components/Form/NewPasswordFields.jsx';
import Button from '../../Components/UI/Button.jsx';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import { activateInvitation, getInvitation } from '../../Services/InvitationService.js';

const loadError = (error) =>
  error.status === 0 || error.status === 404 || error.status === 410
    ? error.message
    : error.status === 429
      ? `Trop de tentatives. Réessayez dans ${error.retryAfter ?? 60} secondes.`
      : 'L’invitation n’a pas pu être lue. Réessayez dans un instant.';

/*
 * Activation d'un compte depuis le lien d'invitation (#127), hors connexion. Le nom et
 * l'adresse viennent de l'invitation ; seul le mot de passe se choisit (contexte client).
 */
export default function ActivationPage() {
  const { token } = useParams();
  const navigate = useNavigate();
  const [state, setState] = useState({ status: 'loading', invitation: null, error: '' });
  const [password, setPassword] = useState('');
  const [confirmation, setConfirmation] = useState('');
  const [errors, setErrors] = useState({});
  const [formError, setFormError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    document.title = 'Activer mon compte · KLAP';
    let cancelled = false;
    getInvitation(token)
      .then((invitation) => !cancelled && setState({ status: 'ready', invitation, error: '' }))
      .catch((error) => !cancelled && setState({ status: 'error', invitation: null, error: loadError(error) }));
    return () => {
      cancelled = true;
    };
  }, [token]);

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
      const account = await activateInvitation(token, password, confirmation);
      navigate('/connexion', { replace: true, state: { email: account.email, activated: true } });
    } catch (error) {
      if (error.status === 422) {
        setErrors(Object.fromEntries(Object.entries(error.errors ?? {}).map(([key, messages]) => [key, messages[0]])));
      } else {
        setFormError(
          [0, 404, 409, 410].includes(error.status)
            ? error.message
            : error.status === 429
              ? `Trop de tentatives. Réessayez dans ${error.retryAfter ?? 60} secondes.`
              : 'Le compte n’a pas pu être activé. Réessayez dans un instant.',
        );
      }
      setSubmitting(false);
    }
  }

  if (state.status === 'loading') return <LoadingSpinner label="Lecture de l’invitation…" />;

  if (state.status === 'error') {
    return (
      <>
        <h1 className="auth-title">Activer mon compte</h1>
        <p className="form-alert" role="alert">
          {state.error}
        </p>
        <Link to="/connexion" className="btn btn-line">
          Aller à la connexion
        </Link>
      </>
    );
  }

  const { invitation } = state;
  return (
    <>
      <h1 className="auth-title">Activer mon compte</h1>
      <p className="auth-subtitle">Bienvenue {invitation.full_name}. Choisissez votre mot de passe.</p>

      <form className="auth-form" onSubmit={handleSubmit} noValidate>
        {formError && (
          <p className="form-alert" role="alert">
            {formError}
          </p>
        )}
        <dl className="activation-facts">
          <dt>Nom</dt>
          <dd>{invitation.full_name}</dd>
          <dt>Adresse mail</dt>
          <dd>{invitation.email}</dd>
        </dl>
        <NewPasswordFields
          password={password}
          confirmation={confirmation}
          onPassword={setPassword}
          onConfirmation={setConfirmation}
          errors={errors}
        />
        <Button type="submit" className="btn-block" disabled={submitting}>
          {submitting ? 'Activation…' : 'Activer mon compte'}
        </Button>
      </form>
    </>
  );
}
