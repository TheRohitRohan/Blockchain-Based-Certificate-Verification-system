import { useState } from 'react';
import { useNavigate, Navigate } from 'react-router';
import { useAuthContext } from '../context/AuthContext';
import { FormField, Spinner } from '../components/ui';
import { ShieldCheck, Mail, Lock, LogIn } from 'lucide-react';

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
        <p className="auth-sub">Enter your credentials to access your dashboard.</p>

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

          <button type="submit" className="btn-primary" disabled={loading} style={{ width: '100%', justifyContent: 'center', marginTop: 8 }}>
            {loading ? <Spinner size={14} /> : <LogIn size={14} />}
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <p style={{ marginTop: 20, fontSize: '0.72rem', color: 'var(--text3)', textAlign: 'center' }}>
          Default admin: admin@certificate-system.com / admin123
        </p>
      </div>
    </div>
  );
}
