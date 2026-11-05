import { createContext, useCallback, useMemo, useState } from 'react';
import useAuth from '../Hooks/useAuth.js';
import { submitEntry, unlockEntry, updateEntry } from '../Services/LogbookService.js';
import { flattenErrors, formatDate, formFromEntry, payloadFromForm, stepsWithErrors } from '../Pages/Logbook/logbookModel.js';

export const LogbookContext = createContext(null);

const LOCK_MESSAGE = 'La fiche est verrouillée : le délai de 10 jours après la séance est passé.';

/*
 * État d'une fiche en cours de saisie : la fiche de l'API, le formulaire, les erreurs
 * par champ, et les actions enregistrer / soumettre. Les étapes lisent et écrivent ici.
 */
export function LogbookProvider({ initialEntry, children }) {
  const { user, logout } = useAuth();
  const [entry, setEntry] = useState(initialEntry);
  // Les groupes sont ceux de la séance : leur ordre fixe les index des erreurs de présents.
  const [groupIds] = useState(() => initialEntry.session.sub_projects.map((group) => group.id));
  const [form, setForm] = useState(() => formFromEntry(initialEntry));
  const [snapshot, setSnapshot] = useState(() =>
    JSON.stringify(payloadFromForm(formFromEntry(initialEntry), groupIds)),
  );
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState(null); // { tone: 'error' | 'success', text }
  const [busy, setBusy] = useState(false);
  const [lockedByApi, setLockedByApi] = useState(null);

  const payload = useMemo(() => payloadFromForm(form, groupIds), [form, groupIds]);
  const dirty = JSON.stringify(payload) !== snapshot;

  let readOnlyReason = lockedByApi;
  if (!readOnlyReason && !entry.editable) {
    readOnlyReason = entry.author.id !== user?.id ? `Fiche de ${entry.author.name}, en lecture seule.` : LOCK_MESSAGE;
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

  /** Traite une erreur de l'API ; renvoie false (l'action a échoué). */
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

  /** Enregistre la saisie si elle a changé. */
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

  /** Enregistre, puis soumet : l'API vérifie la fiche enregistrée. */
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

  /** Rouvre la fiche (administratrice) : nouvelle échéance donnée par l'API. */
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

  const value = useMemo(
    () => ({ entry, form, set, errors, message, setMessage, busy, dirty, readOnlyReason, save, submit, unlock }),
    [entry, form, set, errors, message, busy, dirty, readOnlyReason, save, submit, unlock],
  );

  return <LogbookContext.Provider value={value}>{children}</LogbookContext.Provider>;
}
