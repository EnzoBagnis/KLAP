import { createContext, useCallback, useMemo, useState } from 'react';
import useAuth from '../Hooks/useAuth.js';
import { ROLES } from './roles.js';
import { todayKey } from '../Pages/Planning/sessionLabels.js';
import { getEntry, requestUnlock as requestUnlockEntry, submitEntry, unlockEntry, updateEntry } from '../Services/LogbookService.js';
import { flattenErrors, formatDate, formFromEntry, payloadFromForm, stepsWithErrors } from '../Pages/Logbook/logbookModel.js';

/** Le contexte de la fiche en cours de saisie. On le lit avec le hook useLogbook. */
export const LogbookContext = createContext(null);

/** Le message d'une fiche verrouillée, quand l'API n'en donne pas de plus précis. */
const LOCK_MESSAGE = 'La fiche est verrouillée : le délai de 10 jours après la séance est passé.';

/**
 * L'état d'une fiche en cours de saisie : la fiche de l'API, le formulaire, les erreurs par
 * champ, et les actions (enregistrer, soumettre, rouvrir, demander la réouverture). Les six
 * étapes lisent et écrivent ici.
 *
 * @param {object} props
 * @param {object} props.initialEntry  la fiche telle que l'API la renvoie
 * @param {React.ReactNode} props.children
 */
export function LogbookProvider({ initialEntry, children }) {
  const { user, logout } = useAuth();
  const [entry, setEntry] = useState(initialEntry);
  // Les groupes sont ceux de la séance : on fige leur ordre, qui fixe les index des erreurs de présents.
  const [groupIds] = useState(() => initialEntry.session.sub_projects.map((group) => group.id));
  const [form, setForm] = useState(() => formFromEntry(initialEntry));
  const [snapshot, setSnapshot] = useState(() =>
    JSON.stringify(payloadFromForm(formFromEntry(initialEntry), groupIds)),
  );
  const [errors, setErrors] = useState({});
  // Le bandeau en haut de la fiche : { tone: 'error' | 'success', text }.
  const [message, setMessage] = useState(null);
  const [busy, setBusy] = useState(false);
  const [lockedByApi, setLockedByApi] = useState(null);

  const payload = useMemo(() => payloadFromForm(form, groupIds), [form, groupIds]);
  const dirty = JSON.stringify(payload) !== snapshot;

  // L'administration peut modifier toutes les fiches (#141) : si elle ne le peut pas, c'est que
  // la fiche est verrouillée, ou que sa séance est annulée ou son projet archivé. On en déduit
  // la raison à afficher.
  const manages = user?.role === ROLES.ADMIN || user?.role === ROLES.SUPER_USER;
  let readOnlyReason = lockedByApi;
  if (!readOnlyReason && !entry.editable) {
    if (entry.author.id !== user?.id && !manages) readOnlyReason = `Fiche de ${entry.author.name}, en lecture seule.`;
    else if (entry.status === 'locked' || (entry.edit_deadline && entry.edit_deadline < todayKey())) readOnlyReason = LOCK_MESSAGE;
    else readOnlyReason = 'La séance a été annulée ou son projet archivé : la fiche ne se modifie plus.';
  }

  const set = useCallback((path, value) => {
    setForm((current) => {
      const [head, tail] = path.split('.');
      return tail ? { ...current, [head]: { ...current[head], [tail]: value } } : { ...current, [head]: value };
    });
  }, []);

  const accept = useCallback((saved) => {
    setEntry(saved);
    const next = formFromEntry(saved);
    setForm(next);
    setSnapshot(JSON.stringify(payloadFromForm(next, groupIds)));
  }, [groupIds]);

  /** On traite une erreur de l'API ; on renvoie toujours false, puisque l'action a échoué. */
  const fail = useCallback(
    (error, prefix) => {
      if (error.status === 401) {
        logout();
      } else if (error.status === 422) {
        const fieldErrors = flattenErrors(error.errors);
        setErrors(fieldErrors);
        setMessage({ tone: 'error', text: prefix, steps: stepsWithErrors(fieldErrors) });
      } else if (error.status === 403) {
        setLockedByApi(error.message || LOCK_MESSAGE);
        setMessage({ tone: 'error', text: error.message || LOCK_MESSAGE });
      } else {
        setMessage({
          tone: 'error',
          text: error.status === 0 ? error.message : 'La fiche n’a pas pu être enregistrée. Réessayez dans un instant.',
        });
      }
      return false;
    },
    [logout],
  );

  /** On enregistre la saisie, si elle a changé. */
  const save = useCallback(
    async ({ force = false } = {}) => {
      if (readOnlyReason || (!dirty && !force)) return true;
      setBusy(true);
      setMessage(null);
      try {
        accept(await updateEntry(entry.id, payload));
        setErrors({});
        return true;
      } catch (error) {
        return fail(error, 'La fiche n’a pas été enregistrée : corrigez les champs signalés.');
      } finally {
        setBusy(false);
      }
    },
    [readOnlyReason, dirty, entry.id, payload, accept, fail],
  );

  /** On enregistre, puis on soumet : c'est la fiche enregistrée que l'API vérifie. */
  const submit = useCallback(async () => {
    if (!(await save())) return false;
    setBusy(true);
    try {
      accept(await submitEntry(entry.id));
      setErrors({});
      setMessage({ tone: 'success', text: 'Fiche soumise. Vous pouvez encore la modifier jusqu’à l’échéance.' });
      return true;
    } catch (error) {
      return fail(error, 'La fiche n’est pas complète : complétez les champs signalés.');
    } finally {
      setBusy(false);
    }
  }, [save, entry.id, accept, fail]);

  /** L'administratrice rouvre la fiche ; l'API donne la nouvelle échéance. */
  const unlock = useCallback(async () => {
    setBusy(true);
    setMessage(null);
    try {
      const saved = await unlockEntry(entry.id);
      accept(saved);
      setLockedByApi(null);
      setErrors({});
      setMessage({
        tone: 'success',
        text: `Fiche rouverte : ${saved.author.name} peut la modifier jusqu’au ${formatDate(saved.edit_deadline)} inclus.`,
      });
      return true;
    } catch (error) {
      if (error.status === 401) {
        logout();
      } else {
        setMessage({
          tone: 'error',
          text: [403, 409].includes(error.status) || error.status === 0 ? error.message : 'La fiche n’a pas pu être rouverte.',
        });
      }
      return false;
    } finally {
      setBusy(false);
    }
  }, [entry.id, accept, logout]);

  /** L'auteur d'une fiche verrouillée demande sa réouverture à l'administratrice. */
  const requestUnlock = useCallback(async () => {
    setBusy(true);
    setMessage(null);
    try {
      accept(await requestUnlockEntry(entry.id));
      setMessage({ tone: 'success', text: 'Demande envoyée : l’administratrice est prévenue.' });
      return true;
    } catch (error) {
      if (error.status === 401) {
        logout();
      } else {
        // La demande a déjà été faite ailleurs (autre onglet) : on relit la fiche pour afficher son état.
        if (error.status === 409) getEntry(entry.id).then(accept).catch(() => {});
        setMessage({
          tone: 'error',
          text: [403, 409, 503].includes(error.status) || error.status === 0
            ? error.message
            : 'La demande n’a pas pu être envoyée. Réessayez dans un instant.',
        });
      }
      return false;
    } finally {
      setBusy(false);
    }
  }, [entry.id, accept, logout]);

  const value = useMemo(
    () => ({ entry, form, set, errors, message, setMessage, busy, dirty, readOnlyReason, save, submit, unlock, requestUnlock }),
    [entry, form, set, errors, message, busy, dirty, readOnlyReason, save, submit, unlock, requestUnlock],
  );

  return <LogbookContext.Provider value={value}>{children}</LogbookContext.Provider>;
}
