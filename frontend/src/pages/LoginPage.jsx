import { useState } from 'react';
import { useNavigate, Navigate, Link } from 'react-router';
import { useAuthContext } from '../context/AuthContext';
import { FormField, Spinner } from '../components/ui';
import { ShieldCheck, LogIn } from 'lucide-react';

const ROLE_HOME = { admin: '/admin', university: '/university', student: '/student' };

export default function LoginPage() {
  const { login, isAuthenticated, role } = useAuthContext();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Already logged in → go to dashboard
  if (isAuthenticated && role) {
    return <Navigate to={ROLE_HOME[role] ?? '/student'} replace />;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    if (!email || !password) { setError('Email and password are required.'); return; }
    setLoading(true);
    const result = await login(email, password);
    setLoading(false);
    if (result.success) {
      navigate(ROLE_HOME[result.user?.role] ?? '/student', { replace: true });
    } else {
      setError(result.error ?? 'Login failed. Please try again.');
    }
  }

  return (
    <div className="auth-shell">
      <div className="auth-brand">
        <ShieldCheck size={20} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
        <span className="logo-text">CertiLedger</span>
      </div>

      <div className="auth-card">
        <p className="auth-title">Sign in</p>
        <p className="auth-sub">
          Sign in with your <strong>admin</strong>, <strong>university</strong>, or <strong>student</strong> account.
        </p>

        {error && <div className="auth-error">{error}</div>}

        <form onSubmit={handleSubmit} className="form-grid">
          <FormField label="Email" required>
            <input
              type="email"
              className="form-input"
              placeholder="you@university.edu"
              value={email}
              onChange={e => setEmail(e.target.value)}
              autoComplete="email"
            />
          </FormField>

          <FormField label="Password" required>
            <input
              type="password"
              className="form-input"
              placeholder="Min. 6 characters"
              value={password}
              onChange={e => setPassword(e.target.value)}
              autoComplete="current-password"
            />
          </FormField>

          <div style={{ textAlign: 'right', marginTop: -6 }}>
            <Link
              to="/forgot-password"
              style={{ fontSize: '0.72rem', color: 'var(--text3)', textDecoration: 'none' }}
            >
              Forgot password?
            </Link>
          </div>

          <button type="submit" className="btn-primary" disabled={loading} style={{ width: '100%', justifyContent: 'center', marginTop: 8 }}>
            {loading ? <Spinner size={14} /> : <LogIn size={14} />}
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <p style={{ marginTop: 20, fontSize: '0.72rem', color: 'var(--text3)', textAlign: 'center' }}>
          <Link to="/university/register" style={{ color: 'var(--accent)', textDecoration: 'none' }}>
            Register your university
          </Link>
          <span style={{ display: 'block', marginTop: 10 }}>
            Default admin: admin@certificate-system.com / admin123
          </span>
        </p>
      </div>
    </div>
  );
}
