// ── StatCard ──────────────────────────────────────────────────
export function StatCard({ label, value, sub }) {
  return (
    <div className="stat-card">
      <span className="stat-label">{label}</span>
      <span className="stat-value">{value ?? '—'}</span>
      {sub && <span className="stat-sub">{sub}</span>}
    </div>
  );
}

// ── Badge ──────────────────────────────────────────────────────
export function Badge({ status }) {
  const map = {
    active: 'badge-active',
    valid: 'badge-active',
    revoked: 'badge-revoked',
    inactive: 'badge-inactive',
  };
  return (
    <span className={`badge ${map[status?.toLowerCase()] ?? 'badge-inactive'}`}>
      {status}
    </span>
  );
}

// ── Spinner ────────────────────────────────────────────────────
export function Spinner({ size = 20 }) {
  return (
    <span
      className="spinner"
      style={{ width: size, height: size }}
      aria-label="Loading"
    />
  );
}

// ── PageSpinner ────────────────────────────────────────────────
export function PageSpinner() {
  return (
    <div className="page-spinner-wrap">
      <Spinner size={32} />
    </div>
  );
}

// ── EmptyState ─────────────────────────────────────────────────
export function EmptyState({ icon = '○', title, sub }) {
  return (
    <div className="empty-state">
      <span className="empty-icon">{icon}</span>
      <span className="empty-title">{title}</span>
      {sub && <span className="empty-sub">{sub}</span>}
    </div>
  );
}

// ── Modal ──────────────────────────────────────────────────────
export function Modal({ title, children, onClose, footer }) {
  return (
    <div className="modal-overlay" onClick={e => e.target === e.currentTarget && onClose()}>
      <div className="modal-box" role="dialog" aria-modal="true">
        <div className="modal-header">
          <span className="modal-title">{title}</span>
          <button className="modal-close" onClick={onClose}>✕</button>
        </div>
        <div className="modal-body">{children}</div>
        {footer && <div className="modal-footer">{footer}</div>}
      </div>
    </div>
  );
}

// ── ConfirmModal ───────────────────────────────────────────────
export function ConfirmModal({ title, message, onConfirm, onCancel, confirmLabel = 'Confirm', danger = false }) {
  return (
    <Modal title={title} onClose={onCancel}
      footer={
        <>
          <button className="btn-ghost-sm" onClick={onCancel}>Cancel</button>
          <button
            className={danger ? 'btn-danger-sm' : 'btn-primary-sm'}
            onClick={onConfirm}
          >
            {confirmLabel}
          </button>
        </>
      }
    >
      <p className="confirm-message">{message}</p>
    </Modal>
  );
}

// ── Form Field ─────────────────────────────────────────────────
export function FormField({ label, error, children, required }) {
  return (
    <div className="form-field">
      <label className="form-label">
        {label}{required && <span className="required">*</span>}
      </label>
      {children}
      {error && <span className="form-error">{error}</span>}
    </div>
  );
}
