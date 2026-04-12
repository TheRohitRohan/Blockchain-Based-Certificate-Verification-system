import { useState, useMemo } from 'react';
import { Link, useSearchParams } from 'react-router';
import { resetPassword } from '../api/auth.api';
import { FormField, Spinner } from '../components/ui';
import { ShieldCheck, KeyRound, ArrowLeft, CheckCircle, AlertTriangle } from 'lucide-react';

/**
 * Password strength rules (must match backend):
 *  - At least 8 characters
 *  - Contains both letters and numbers
 */
function validatePassword(pw) {
  if (pw.length < 8) return 'Password must be at least 8 characters.';
  if (!/[a-zA-Z]/.test(pw)) return 'Password must contain at least one letter.';
  if (!/[0-9]/.test(pw)) return 'Password must contain at least one number.';
  return null;
}

export default function ResetPasswordPage() {
  const [searchParams] = useSearchParams();
  const token = useMemo(() => searchParams.get('token') ?? '', [searchParams]);

  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);
  const [expired, setExpired] = useState(false);

  // ── No token in URL ──────────────────────────────────────────
  if (!token) {
    return (
      <div className="auth-shell">
        <div className="auth-brand">
          <ShieldCheck size={20} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
          <span className="logo-text">CertiLedger</span>
        </div>
        <div className="auth-card" style={{ textAlign: 'center' }}>
          <div className="forgot-success-icon" style={{ color: 'var(--yellow)' }}>
            <AlertTriangle size={36} strokeWidth={1.5} />
          </div>
          <p className="auth-title">Invalid reset link</p>
          <p className="auth-sub">
            This link doesn't contain a reset token. Please use the link from your email or request
            a new one.
          </p>
          <Link
            to="/forgot-password"
            className="btn-primary"
            style={{ width: '100%', justifyContent: 'center', textDecoration: 'none', marginTop: 4 }}
          >
            Request a new link
          </Link>
        </div>
      </div>
    );
  }

  // ── Form submit ──────────────────────────────────────────────
  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    const pwError = validatePassword(password);
    if (pwError) { setError(pwError); return; }
    if (password !== confirmPassword) { setError('Passwords do not match.'); return; }

    setLoading(true);
    try {
      const data = await resetPassword(token, password);
      if (data.success) {
        setSuccess(true);
      } else {
        setError(data.error ?? 'Failed to reset password.');
      }
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Something went wrong.';
      // Distinguish between expired vs invalid tokens
      if (msg.toLowerCase().includes('expired')) {
        setExpired(true);
      } else {
        setError(msg);
      }
    } finally {
      setLoading(false);
    }
  }

  // ── Token expired state ──────────────────────────────────────
  if (expired) {
    return (
      <div className="auth-shell">
        <div className="auth-brand">
          <ShieldCheck size={20} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
          <span className="logo-text">CertiLedger</span>
        </div>
        <div className="auth-card" style={{ textAlign: 'center' }}>
          <div className="forgot-success-icon" style={{ color: 'var(--yellow)' }}>
            <AlertTriangle size={36} strokeWidth={1.5} />
          </div>
          <p className="auth-title">Link expired</p>
          <p className="auth-sub">
            Your password reset link has expired. Reset links are valid for <strong>15 minutes</strong>.
            Please request a new one.
          </p>
          <Link
            to="/forgot-password"
            className="btn-primary"
            style={{ width: '100%', justifyContent: 'center', textDecoration: 'none', marginTop: 4 }}
          >
            Request a new link
          </Link>
        </div>
      </div>
    );
  }

  // ── Success state ────────────────────────────────────────────
  if (success) {
    return (
      <div className="auth-shell">
        <div className="auth-brand">
          <ShieldCheck size={20} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
          <span className="logo-text">CertiLedger</span>
        </div>
        <div className="auth-card" style={{ textAlign: 'center' }}>
          <div className="forgot-success-icon">
            <CheckCircle size={36} strokeWidth={1.5} />
          </div>
          <p className="auth-title">Password updated</p>
          <p className="auth-sub">
            Your password has been reset successfully. You can now sign in with your new password.
          </p>
          <Link
            to="/login"
            className="btn-primary"
            style={{ width: '100%', justifyContent: 'center', textDecoration: 'none', marginTop: 4 }}
          >
            <ArrowLeft size={14} />
            Sign in
          </Link>
        </div>
      </div>
    );
  }

  // ── Default form state ───────────────────────────────────────
  return (
    <div className="auth-shell">
      <div className="auth-brand">
        <ShieldCheck size={20} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
        <span className="logo-text">CertiLedger</span>
      </div>

      <div className="auth-card">
        <p className="auth-title">Reset your password</p>
        <p className="auth-sub">
          Enter a new password for your account. It must be at least 8 characters and contain both
          letters and numbers.
        </p>

        {error && <div className="auth-error">{error}</div>}

        <form onSubmit={handleSubmit} className="form-grid">
          <FormField label="New password" required>
            <input
              id="reset-password"
              type="password"
              className="form-input"
              placeholder="Min. 8 characters"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="new-password"
              autoFocus
            />
          </FormField>

          <FormField label="Confirm password" required>
            <input
              id="reset-confirm-password"
              type="password"
              className="form-input"
              placeholder="Re-enter your password"
              value={confirmPassword}
              onChange={(e) => setConfirmPassword(e.target.value)}
              autoComplete="new-password"
            />
          </FormField>

          <button
            type="submit"
            className="btn-primary"
            disabled={loading}
            style={{ width: '100%', justifyContent: 'center', marginTop: 4 }}
          >
            {loading ? <Spinner size={14} /> : <KeyRound size={14} />}
            {loading ? 'Resetting…' : 'Reset password'}
          </button>
        </form>

        <p
          style={{
            marginTop: 20,
            fontSize: '0.75rem',
            color: 'var(--text3)',
            textAlign: 'center',
          }}
        >
          <Link
            to="/login"
            style={{
              color: 'var(--accent)',
              textDecoration: 'none',
              display: 'inline-flex',
              alignItems: 'center',
              gap: 4,
            }}
          >
            <ArrowLeft size={12} />
            Back to Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
