import { useCallback, useEffect, useId, useRef, useState } from 'react';
import Button from '../UI/Button.jsx';
import LoadingSpinner from '../UI/LoadingSpinner.jsx';
import TextInput from '../Form/TextInput.jsx';
import SelectInput from '../Form/SelectInput.jsx';
import useAuth from '../../Hooks/useAuth.js';

/*
 * Une liste de référence que l'administratrice tient elle-même (#249) : financeurs, types de
 * projet, lieux, et les groupes d'un projet (#250). On ajoute, on corrige sur place, on
 * désactive ou on réactive ; on ne supprime jamais. Désactivé, un élément n'est plus proposé
 * dans les menus, mais ce qui l'utilise déjà le garde.
 */

/**
 * Un champ du formulaire d'une liste.
 *
 * @typedef {object} Field
 * @property {string} key  le nom du champ dans l'API
 * @property {string} label
 * @property {boolean} [required]
 * @property {string} [requiredMessage]  le message si le champ obligatoire est vide
 * @property {number} [maxLength]
 * @property {RegExp} [pattern]
 * @property {string} [patternMessage]  le message si la valeur ne respecte pas `pattern`
 * @property {Array<{value: string, label: string}>} [options]  s'il y en a, le champ est un menu
 * @property {string} [inputMode]
 */

/** Un brouillon vide : chaque champ à la chaîne vide. */
const emptyDraft = (fields) => Object.fromEntries(fields.map((f) => [f.key, '']));

/**
 * Le brouillon prêt à être envoyé : on retire les espaces autour des textes.
 *
 * @param {Field[]} fields
 * @param {object} draft
 * @returns {object}
 */
function cleaned(fields, draft) {
  return Object.fromEntries(fields.map((f) => [f.key, typeof draft[f.key] === 'string' ? draft[f.key].trim() : draft[f.key]]));
}

/**
 * Les erreurs qu'on détecte avant d'appeler l'API.
 *
 * @param {Field[]} fields
 * @param {object} draft
 * @returns {Object<string, string>}
 */
function localErrors(fields, draft) {
  const errors = {};
  for (const f of fields) {
    const value = typeof draft[f.key] === 'string' ? draft[f.key].trim() : draft[f.key];
    if (f.required && !value) errors[f.key] = f.requiredMessage ?? `Indiquez ${f.label.toLowerCase()}.`;
    else if (value && f.pattern && !f.pattern.test(value)) errors[f.key] = f.patternMessage;
    else if (value && f.maxLength && value.length > f.maxLength) errors[f.key] = `Ce champ ne doit pas dépasser ${f.maxLength} caractères.`;
  }
  return errors;
}

/**
 * Le formulaire d'ajout ou de modification d'un élément, qu'on affiche sur place dans la liste.
 */
function DraftForm({ fields, draft, setDraft, errors, onSubmit, onCancel, submitLabel, busy, idPrefix }) {
  const ref = useRef(null);
  useEffect(() => {
    ref.current?.querySelector('input, select')?.focus();
  }, []);
  const set = (key) => (event) => setDraft((current) => ({ ...current, [key]: event.target.value }));
  return (
    <form ref={ref} className="reference-form" onSubmit={onSubmit} noValidate>
      {fields.map((f) =>
        f.options ? (
          <SelectInput key={f.key} label={f.label} name={`${idPrefix}-${f.key}`} required={f.required} value={draft[f.key]} onChange={set(f.key)} error={errors[f.key]} options={f.options} />
        ) : (
          <TextInput key={f.key} label={f.label} name={`${idPrefix}-${f.key}`} required={f.required} maxLength={f.maxLength} inputMode={f.inputMode} value={draft[f.key] ?? ''} onChange={set(f.key)} error={errors[f.key]} />
        ),
      )}
      <div className="reference-form-actions">
        <Button type="submit" disabled={busy}>
          {busy ? 'Enregistrement…' : submitLabel}
        </Button>
        <Button variant="ghost" onClick={onCancel} disabled={busy}>
          Annuler
        </Button>
      </div>
    </form>
  );
}

/**
 * Les mots de l'interface, qu'une liste peut remplacer : pour les groupes d'un projet, on dit
 * « Retirer » et « Remettre » plutôt que « Désactiver » et « Réactiver ».
 */
const DEFAULT_WORDING = {
  off: 'Désactiver',
  on: 'Réactiver',
  tag: 'Désactivé',
  doneOff: 'désactivé',
  doneOn: 'réactivé',
  askOff: (n) => `Désactiver « ${n} » ? Il ne sera plus proposé dans les menus ; ce qui l’utilise déjà le garde.`,
  askOn: (n) => `Réactiver « ${n} » ? Il sera de nouveau proposé dans les menus.`,
};

/**
 * La liste, avec ses actions.
 *
 * @param {object} props
 * @param {string} props.title
 * @param {string} [props.intro]
 * @param {string} props.noun  le nom d'un élément, pour les messages (« Financeur »)
 * @param {string} props.addLabel  le libellé du bouton d'ajout
 * @param {Field[]} props.fields
 * @param {() => Promise<object[]>} props.load
 * @param {(payload: object) => Promise<object>} props.create
 * @param {(id: number, changes: object) => Promise<object>} props.update
 * @param {(item: object) => string} [props.meta]  la ligne d'information sous le nom
 * @param {(item: object) => string} [props.name]  le nom affiché d'un élément
 * @param {object} [props.wording]  les mots à remplacer, voir DEFAULT_WORDING
 * @param {2|3} [props.headingLevel=2]
 */
export default function ReferenceList({ title, intro, noun, addLabel, fields, load, create, update, meta, name = (item) => item.name, wording = {}, headingLevel = 2 }) {
  const w = { ...DEFAULT_WORDING, ...wording };
  const Heading = `h${headingLevel}`;
  const { logout } = useAuth();
  const headingId = useId();
  const [state, setState] = useState({ status: 'loading', items: [] });
  const [notice, setNotice] = useState({ kind: '', text: '' });
  // Ce qu'on est en train de saisir : 'nouveau', ou l'identifiant de l'élément qu'on modifie.
  const [editing, setEditing] = useState(null);
  const [draft, setDraft] = useState({});
  const [errors, setErrors] = useState({});
  const [busy, setBusy] = useState(false);
  const [busyId, setBusyId] = useState(null);

  const fail = useCallback(
    (error, fallback) => {
      if (error.status === 401) {
        logout();
        return true;
      }
      if (error.status === 422 && error.errors) {
        const fieldErrors = Object.fromEntries(Object.entries(error.errors).map(([key, messages]) => [key, messages[0]]));
        const known = fields.some((f) => fieldErrors[f.key]);
        setErrors(fieldErrors);
        if (!known) setNotice({ kind: 'error', text: Object.values(fieldErrors)[0] ?? fallback });
        return true;
      }
      setNotice({ kind: 'error', text: error.status === 0 ? error.message : fallback });
      return false;
    },
    [fields, logout],
  );

  const reload = useCallback(() => {
    setState({ status: 'loading', items: [] });
    load()
      .then((items) => setState({ status: 'ready', items }))
      .catch((error) => {
        if (error.status === 401) {
          logout();
          return;
        }
        setState({ status: 'error', items: [], error: error.status === 0 ? error.message : 'La liste n’a pas pu être chargée.' });
      });
  }, [load, logout]);

  useEffect(() => {
    reload();
  }, [reload]);

  // Après « Enregistrer » ou « Annuler », le formulaire disparaît : on rend le focus au titre.
  const headingRef = useRef(null);
  function close() {
    setEditing(null);
    requestAnimationFrame(() => headingRef.current?.focus());
  }

  function open(target, item) {
    setEditing(target);
    setErrors({});
    setNotice({ kind: '', text: '' });
    setDraft(item ? Object.fromEntries(fields.map((f) => [f.key, item[f.key] ?? ''])) : emptyDraft(fields));
  }

  async function submit(event) {
    event.preventDefault();
    if (busy) return;
    const found = localErrors(fields, draft);
    setErrors(found);
    if (Object.keys(found).length) return;
    setBusy(true);
    try {
      const payload = cleaned(fields, draft);
      if (editing === 'nouveau') {
        const created = await create(payload);
        // On relit la liste : l'élément arrive ainsi complet (état, usage) et à sa place dans l'ordre.
        setState({ status: 'ready', items: await load() });
        setNotice({ kind: 'success', text: `${noun} « ${name(created)} » ajouté.` });
      } else {
        const updated = await update(editing, payload);
        setState((current) => ({ ...current, items: current.items.map((item) => (item.id === updated.id ? { ...item, ...updated } : item)) }));
        setNotice({ kind: 'success', text: `${noun} « ${name(updated)} » enregistré.` });
      }
      close();
    } catch (error) {
      fail(error, 'L’enregistrement n’a pas abouti. Réessayez dans un instant.');
    } finally {
      setBusy(false);
    }
  }

  async function toggle(item) {
    // Sans is_active (réponse partielle), on considère l'élément actif, comme à l'affichage.
    const activate = item.is_active === false;
    const question = activate ? w.askOn(name(item)) : w.askOff(name(item));
    if (!window.confirm(question)) return;
    setBusyId(item.id);
    setNotice({ kind: '', text: '' });
    try {
      const updated = await update(item.id, { is_active: activate });
      setState((current) => ({ ...current, items: current.items.map((other) => (other.id === updated.id ? { ...other, ...updated } : other)) }));
      setNotice({ kind: 'success', text: `« ${name(updated)} » ${activate ? w.doneOn : w.doneOff}.` });
    } catch (error) {
      fail(error, 'La modification n’a pas abouti. Réessayez dans un instant.');
    } finally {
      setBusyId(null);
    }
  }

  return (
    <section className="reference-list" aria-labelledby={headingId}>
      <div className="reference-heading">
        <Heading id={headingId} className="section-title" ref={headingRef} tabIndex={-1}>
          {title}
        </Heading>
        {editing !== 'nouveau' && (
          <Button variant="line" onClick={() => open('nouveau')}>
            + {addLabel}
          </Button>
        )}
      </div>
      {intro && <p className="form-hint">{intro}</p>}
      {notice.text && (
        <p className={notice.kind === 'success' ? 'form-success' : 'form-alert'} role={notice.kind === 'success' ? 'status' : 'alert'}>
          {notice.text}
        </p>
      )}
      {editing === 'nouveau' && (
        <DraftForm fields={fields} draft={draft} setDraft={setDraft} errors={errors} onSubmit={submit} onCancel={close} submitLabel="Ajouter" busy={busy} idPrefix={`${headingId}-nouveau`} />
      )}
      {state.status === 'loading' && <LoadingSpinner label="Chargement…" />}
      {state.status === 'error' && (
        <div className="page-error">
          <p>{state.error}</p>
          <Button variant="line" onClick={reload}>
            Réessayer
          </Button>
        </div>
      )}
      {state.status === 'ready' && state.items.length === 0 && <p className="page-empty">Aucun élément pour l’instant.</p>}
      {state.status === 'ready' && state.items.length > 0 && (
        <ul className="account-rows">
          {state.items.map((item) => (
            <li key={item.id} className={`account-row${item.is_active === false ? ' is-inactive' : ''}`}>
              {editing === item.id ? (
                <DraftForm fields={fields} draft={draft} setDraft={setDraft} errors={errors} onSubmit={submit} onCancel={close} submitLabel="Enregistrer" busy={busy} idPrefix={`${headingId}-${item.id}`} />
              ) : (
                <>
                  <div>
                    <p className="account-name">
                      {name(item)}
                      {item.is_active === false && <span className="tag">{w.tag}</span>}
                    </p>
                    {meta && <p className="account-meta">{meta(item)}</p>}
                  </div>
                  <div className="reference-actions">
                    <Button variant="ghost" onClick={() => open(item.id, item)} disabled={busyId === item.id}>
                      Modifier<span className="visually-hidden">{` ${name(item)}`}</span>
                    </Button>
                    <Button variant="line" onClick={() => toggle(item)} disabled={busyId === item.id}>
                      {item.is_active === false ? w.on : w.off}
                      <span className="visually-hidden">{` ${name(item)}`}</span>
                    </Button>
                  </div>
                </>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
