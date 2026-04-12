import { useState } from 'react';
import { Link } from 'react-router';
import { forgotPassword } from '../api/auth.api';
import { FormField, Spinner } from '../components/ui';
import { ShieldCheck, Mail, ArrowLeft, CheckCircle } from 'lucide-react';

export default function ForgotPasswordPage() {
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [error, setError] = useState('');

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');

    if (!email) {
      setError('Please enter your email address.');
      return;
    }

    setLoading(true);
    try {
      await forgotPassword(email);
      setSubmitted(true);
    } catch (err) {
      const message =
        err.response?.data?.error ?? err.message ?? 'Something went wrong. Please try again.';
      setError(message);
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="auth-shell">
      <div className="auth-brand">
        <ShieldCheck size={20} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
        <span className="logo-text">CertiLedger</span>
      </div>

      <div className="auth-card">
        {submitted ? (
          /* ── Success state ──────────────────────────────── */
          <>
            <div className="forgot-success-icon">
              <CheckCircle size={36} strokeWidth={1.5} />
            </div>
            <p className="auth-title" style={{ textAlign: 'center' }}>Check your email</p>
            <p className="auth-sub" style={{ textAlign: 'center' }}>
              If an account with <strong>{email}</strong> exists, we've sent a password reset link.
              The link will expire in <strong>15 minutes</strong>.
            </p>

            <p
              style={{
                fontSize: '0.75rem',
                color: 'var(--text3)',
                textAlign: 'center',
                lineHeight: 1.6,
                marginBottom: 20,
              }}
            >
              Didn't receive the email? Check your spam folder or{' '}
              <button
                type="button"
                className="forgot-resend-btn"
                onClick={() => {
                  setSubmitted(false);
                  setEmail('');
                }}
              >
                try again
              </button>
            </p>

            <Link
              to="/login"
              className="btn-primary"
              style={{ width: '100%', justifyContent: 'center', textDecoration: 'none' }}
            >
              <ArrowLeft size={14} />
              Back to Sign in
            </Link>
          </>
        ) : (
          /* ── Form state ─────────────────────────────────── */
          <>
            <p className="auth-title">Forgot password?</p>
            <p className="auth-sub">
              Enter the email associated with your account and we'll send you a link to reset your
              password.
            </p>

            {error && <div className="auth-error">{error}</div>}

            <form onSubmit={handleSubmit} className="form-grid">
              <FormField label="Email address" required>
                <input
                  id="forgot-email"
                  type="email"
                  className="form-input"
                  placeholder="you@example.com"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  autoComplete="email"
                  autoFocus
                />
              </FormField>

              <button
                type="submit"
                className="btn-primary"
                disabled={loading}
                style={{ width: '100%', justifyContent: 'center', marginTop: 4 }}
              >
                {loading ? <Spinner size={14} /> : <Mail size={14} />}
                {loading ? 'Sending…' : 'Send reset link'}
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
          </>
        )}
      </div>
    </div>
  );
}
