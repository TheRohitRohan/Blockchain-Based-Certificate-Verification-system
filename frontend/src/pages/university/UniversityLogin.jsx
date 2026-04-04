import { useState } from 'react';
import { useNavigate, Navigate, Link } from 'react-router';
import { useAuthContext } from '../../context/AuthContext';
import { FormField, Spinner } from '../../components/ui';
import { ShieldCheck, LogIn } from 'lucide-react';
import { universityLogin } from '../../api/auth.api';

export default function UniversityLogin() {
  const { loginWithData, isAuthenticated, role } = useAuthContext();
  const navigate = useNavigate();
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  // Already authenticated as university → go to dashboard
  if (isAuthenticated && role === 'university') {
    return <Navigate to="/university" replace />;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    if (!email || !password) {
      setError('Email and password are required.');
      return;
    }
    setLoading(true);
    try {
      const data = await universityLogin(email, password);
      if (data.success && data.token) {
        // Merge university_name and admin_name into the user object so the
        // dashboard can display them even after a page reload (they are also
        // encoded inside the JWT payload).
        const user = {
          ...data.user,
          admin_name: data.admin?.name ?? data.user?.name,
          university_name: data.university?.name,
        };
        loginWithData({ token: data.token, user });
        navigate('/university', { replace: true });
      } else {
        setError(data.error ?? 'Login failed. Please try again.');
      }
    } catch (err) {
      setError(err.response?.data?.error ?? err.message ?? 'Login failed. Please try again.');
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
        <p className="auth-title">University Sign in</p>
        <p className="auth-sub">Sign in with your university admin credentials.</p>

        {error && <div className="auth-error">{error}</div>}

        <form onSubmit={handleSubmit} className="form-grid">
          <FormField label="Admin Email" required>
            <input
              type="email"
              className="form-input"
              placeholder="admin@university.edu"
              value={email}
              onChange={e => setEmail(e.target.value)}
              autoComplete="email"
            />
          </FormField>

          <FormField label="Password" required>
            <input
              type="password"
              className="form-input"
              placeholder="Min. 8 characters"
              value={password}
              onChange={e => setPassword(e.target.value)}
              autoComplete="current-password"
            />
          </FormField>

          <button
            type="submit"
            className="btn-primary"
            disabled={loading}
            style={{ width: '100%', justifyContent: 'center', marginTop: 8 }}
          >
            {loading ? <Spinner size={14} /> : <LogIn size={14} />}
            {loading ? 'Signing in…' : 'Sign in'}
          </button>
        </form>

        <p style={{ marginTop: 20, fontSize: '0.75rem', color: 'var(--text3)', textAlign: 'center' }}>
          Don&apos;t have an account?{' '}
          <Link to="/university/register" style={{ color: 'var(--accent)', textDecoration: 'none' }}>
            Register your university
          </Link>
        </p>
      </div>
    </div>
  );
}
