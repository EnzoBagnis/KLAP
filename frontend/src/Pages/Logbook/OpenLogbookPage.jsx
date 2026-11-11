import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import LoadingSpinner from '../../Components/UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { createEntryForSession, getEntryForSession } from '../../Services/LogbookService.js';

const OTHER_AUTHOR =
  'Cette séance a déjà une fiche, rédigée par un autre intervenant. Demandez à l’administratrice de vous la transférer.';

/*
 * Ouvre la fiche d'une séance depuis le planning : la reprend si elle existe, la crée
 * sinon (si une autre ouverture l'a créée entre-temps, 409, on la relit), puis y renvoie.
 */
export default function OpenLogbookPage() {
  const { sessionId } = useParams();
  const navigate = useNavigate();
  const { logout } = useAuth();
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;

    async function open() {
      try {
        let entry;
        try {
          entry = await getEntryForSession(sessionId);
        } catch (notFound) {
          if (notFound.status !== 404) throw notFound;
          try {
            entry = await createEntryForSession(sessionId);
          } catch (conflict) {
            if (conflict.status !== 409) throw conflict;
            // Fiche déjà rédigée par un autre intervenant (changement de référent).
            entry = await getEntryForSession(sessionId).catch((reread) => {
              throw reread.status === 404 ? Object.assign(new Error(OTHER_AUTHOR), { status: 403 }) : reread;
            });
          }
        }
        if (!cancelled) navigate(`/fiches/${entry.id}`, { replace: true });
      } catch (failure) {
        if (cancelled) return;
        if (failure.status === 401) {
          logout();
          return;
        }
        const firstFieldError = Object.values(failure.errors ?? {})[0]?.[0];
        setError(
          firstFieldError ||
            (failure.status === 0 || failure.status === 403 ? failure.message : 'La fiche n’a pas pu être ouverte.'),
        );
      }
    }

    open();
    return () => {
      cancelled = true;
    };
  }, [sessionId, navigate, logout]);

  return (
    <section className="page">
      {!error && <LoadingSpinner label="Ouverture de la fiche…" />}
      {error && (
        <div className="page-error" role="alert">
          <h1 className="page-title">Fiche indisponible</h1>
          <p>{error}</p>
          <Link to="/planning" className="btn btn-line">
            Retour au planning
          </Link>
        </div>
      )}
    </section>
  );
}
