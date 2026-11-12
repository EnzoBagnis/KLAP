import { useCallback, useEffect, useState } from 'react';
import Button from '../UI/Button.jsx';
import LoadingSpinner from '../UI/LoadingSpinner.jsx';
import useAuth from '../../Hooks/useAuth.js';
import { listAccounts, setAccountActive } from '../../Services/AccountService.js';
import { formatInstant } from '../../Pages/Logbook/logbookModel.js';

/**
 * Les comptes de l'application (#159) : on désactive le compte d'une personne qui quitte
 * KLAP, ou on le réactive. Désactivé, le compte perd aussitôt l'accès ; on conserve ses
 * fiches. On ne propose pas de bouton à l'administratrice sur son propre compte.
 */
export default function AccountList() {
  const { user, logout } = useAuth();
  const [state, setState] = useState({ status: 'loading', accounts: [] });
  const [notice, setNotice] = useState({ kind: '', text: '' });
  const [busyId, setBusyId] = useState(null);

  const load = useCallback(() => {
    setState({ status: 'loading', accounts: [] });
    listAccounts()
      .then((accounts) => setState({ status: 'ready', accounts }))
      .catch((error) => {
        if (error.status === 401) {
          logout();
          return;
        }
        setState({ status: 'error', accounts: [], error: error.status === 0 ? error.message : 'La liste des comptes n’a pas pu être chargée.' });
      });
  }, [logout]);

  useEffect(() => {
    load();
  }, [load]);

  async function toggle(account) {
    const activate = !account.is_active;
    const question = activate
      ? `Réactiver le compte de ${account.name} ? La personne pourra de nouveau se connecter.`
      : `Désactiver le compte de ${account.name} ? La personne n’aura plus accès à l’application. Ses fiches sont conservées.`;
    if (!window.confirm(question)) return;
    setBusyId(account.id);
    setNotice({ kind: '', text: '' });
    try {
      const updated = await setAccountActive(account.id, activate);
      setState((current) => ({ ...current, accounts: current.accounts.map((item) => (item.id === updated.id ? updated : item)) }));
      setNotice({ kind: 'success', text: activate ? `Compte de ${updated.name} réactivé.` : `Compte de ${updated.name} désactivé.` });
    } catch (error) {
      if (error.status === 401) {
        logout();
        return;
      }
      const text = error.status === 422 ? error.errors?.is_active?.[0] : error.status === 0 ? error.message : null;
      setNotice({ kind: 'error', text: text ?? 'Le compte n’a pas pu être modifié. Réessayez dans un instant.' });
    } finally {
      setBusyId(null);
    }
  }

  return (
    <section className="account-list" aria-labelledby="comptes">
      <h2 id="comptes" className="section-title">
        Comptes
      </h2>
      {state.status === 'loading' && <LoadingSpinner label="Chargement des comptes…" />}
      {state.status === 'error' && (
        <div className="page-error">
          <p>{state.error}</p>
          <Button variant="line" onClick={load}>
            Réessayer
          </Button>
        </div>
      )}
      {notice.text && (
        <p className={notice.kind === 'success' ? 'form-success' : 'form-alert'} role={notice.kind === 'success' ? 'status' : 'alert'}>
          {notice.text}
        </p>
      )}
      {state.status === 'ready' && (
        <ul className="account-rows">
          {state.accounts.map((account) => (
            <li key={account.id} className={`account-row${account.is_active ? '' : ' is-inactive'}`}>
              <div>
                <p className="account-name">
                  {account.name}
                  {!account.is_active && <span className="tag">Désactivé</span>}
                </p>
                <p className="account-meta">
                  {account.role_label} · {account.email}
                  {account.last_login_at ? ` · dernière connexion le ${formatInstant(account.last_login_at)}` : ' · jamais connecté·e'}
                </p>
              </div>
              {account.id !== user?.id && (
                <Button variant="line" onClick={() => toggle(account)} disabled={busyId === account.id}>
                  {account.is_active ? 'Désactiver' : 'Réactiver'}
                </Button>
              )}
            </li>
          ))}
        </ul>
      )}
    </section>
  );
}
