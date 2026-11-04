import { useEffect, useId, useRef } from 'react';
import '../assets/css/ModalStyles.css';

/*
 * Fenêtre modale de base : rôle dialog, titre relié, Échap pour fermer, focus placé dans
 * la fenêtre à l'ouverture et rendu à l'élément d'origine à la fermeture.
 */
export default function BaseModal({ open, title, onClose, children, actions }) {
  const titleId = useId();
  const dialogRef = useRef(null);

  useEffect(() => {
    if (!open) return undefined;
    const previouslyFocused = document.activeElement;
    dialogRef.current?.querySelector('button, a[href], input, select, textarea')?.focus();

    const onKeyDown = (event) => {
      if (event.key === 'Escape') onClose();
    };
    window.addEventListener('keydown', onKeyDown);
    return () => {
      window.removeEventListener('keydown', onKeyDown);
      previouslyFocused?.focus?.();
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="modal-backdrop">
      <div ref={dialogRef} className="modal" role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <h2 id={titleId} className="modal-title">
          {title}
        </h2>
        <div className="modal-body">{children}</div>
        {actions && <div className="modal-actions">{actions}</div>}
      </div>
    </div>
  );
}
