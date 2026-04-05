import { useState } from 'react';
import { useNavigate, Link } from 'react-router';
import { FormField, Spinner } from '../../components/ui';
import { ShieldCheck, UserPlus } from 'lucide-react';
import { universityRegister } from '../../api/auth.api';

const INITIAL = {
  university_name: '',
  university_email: '',
  university_phone: '',
  university_address: '',
  admin_name: '',
  admin_email: '',
  admin_password: '',
  admin_confirm_password: '',
};

export default function UniversityRegister() {
  const navigate = useNavigate();
  const [form, setForm] = useState(INITIAL);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState('');

  function handleChange(e) {
    const { name, value } = e.target;
    setForm(prev => ({ ...prev, [name]: value }));
  }

  function validate() {
    if (!form.university_name.trim()) return 'University name is required.';
    if (!form.university_email.trim()) return 'University email is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.university_email))
      return 'University email is not valid.';
    if (!form.university_phone.trim()) return 'University phone is required.';
    if (!form.university_address.trim()) return 'University address is required.';
    if (!form.admin_name.trim()) return 'Admin name is required.';
    if (!form.admin_email.trim()) return 'Admin email is required.';
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.admin_email))
      return 'Admin email is not valid.';
    if (!form.admin_password) return 'Password is required.';
    if (form.admin_password.length < 8) return 'Password must be at least 8 characters.';
    if (!/(?=.*[A-Za-z])(?=.*\d)/.test(form.admin_password))
      return 'Password must contain both letters and numbers.';
    if (form.admin_password !== form.admin_confirm_password)
      return 'Passwords do not match.';
    return null;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setError('');
    setSuccess('');

    const validationError = validate();
    if (validationError) {
      setError(validationError);
      return;
    }

    setLoading(true);
    try {
      const payload = {
        university_name: form.university_name.trim(),
        university_email: form.university_email.trim(),
        university_phone: form.university_phone.trim(),
        university_address: form.university_address.trim(),
        admin_name: form.admin_name.trim(),
        admin_email: form.admin_email.trim(),
        admin_password: form.admin_password,
      };

      const data = await universityRegister(payload);

      if (data.success) {
        setSuccess('University registered successfully! Redirecting to login…');
        setTimeout(() => navigate('/login'), 1800);
      } else {
        setError(data.error ?? 'Registration failed. Please try again.');
      }
    } catch (err) {
      setError(err.response?.data?.error ?? err.message ?? 'Registration failed. Please try again.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="auth-shell" style={{ minHeight: '100vh', paddingBlock: 40 }}>
      <div className="auth-brand">
        <ShieldCheck size={20} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
        <span className="logo-text">CertiLedger</span>
      </div>

      <div className="auth-card" style={{ maxWidth: 520 }}>
        <p className="auth-title">Register University</p>
        <p className="auth-sub">Create a university account and admin credentials.</p>

        {error && <div className="auth-error">{error}</div>}
        {success && (
          <div className="auth-error" style={{ background: 'var(--success-bg, #14532d)', color: '#86efac', borderColor: '#166534' }}>
            {success}
          </div>
        )}

        <form onSubmit={handleSubmit} className="form-grid">
          {/* ── University details ────────────────────────────── */}
          <p style={{ fontSize: '0.72rem', color: 'var(--text3)', marginBottom: 4, marginTop: 8 }}>
            UNIVERSITY DETAILS
          </p>

          <FormField label="University Name" required>
            <input
              type="text"
              name="university_name"
              className="form-input"
              placeholder="e.g. State University"
              value={form.university_name}
              onChange={handleChange}
            />
          </FormField>

          <FormField label="University Email" required>
            <input
              type="email"
              name="university_email"
              className="form-input"
              placeholder="contact@university.edu"
              value={form.university_email}
              onChange={handleChange}
            />
          </FormField>

          <FormField label="Phone" required>
            <input
              type="tel"
              name="university_phone"
              className="form-input"
              placeholder="+1 555 000 0000"
              value={form.university_phone}
              onChange={handleChange}
            />
          </FormField>

          <FormField label="Address" required>
            <input
              type="text"
              name="university_address"
              className="form-input"
              placeholder="123 Campus Drive, City, State"
              value={form.university_address}
              onChange={handleChange}
            />
          </FormField>

          {/* ── Admin credentials ─────────────────────────────── */}
          <p style={{ fontSize: '0.72rem', color: 'var(--text3)', marginBottom: 4, marginTop: 16 }}>
            ADMIN CREDENTIALS
          </p>

          <FormField label="Admin Name" required>
            <input
              type="text"
              name="admin_name"
              className="form-input"
              placeholder="Full name"
              value={form.admin_name}
              onChange={handleChange}
            />
          </FormField>

          <FormField label="Admin Email" required>
            <input
              type="email"
              name="admin_email"
              className="form-input"
              placeholder="admin@university.edu"
              value={form.admin_email}
              onChange={handleChange}
            />
          </FormField>

          <FormField label="Password" required>
            <input
              type="password"
              name="admin_password"
              className="form-input"
              placeholder="Min. 8 chars, letters + numbers"
              value={form.admin_password}
              onChange={handleChange}
              autoComplete="new-password"
            />
          </FormField>

          <FormField label="Confirm Password" required>
            <input
              type="password"
              name="admin_confirm_password"
              className="form-input"
              placeholder="Repeat password"
              value={form.admin_confirm_password}
              onChange={handleChange}
              autoComplete="new-password"
            />
          </FormField>

          <button
            type="submit"
            className="btn-primary"
            disabled={loading}
            style={{ width: '100%', justifyContent: 'center', marginTop: 12 }}
          >
            {loading ? <Spinner size={14} /> : <UserPlus size={14} />}
            {loading ? 'Registering…' : 'Register University'}
          </button>
        </form>

        <p style={{ marginTop: 20, fontSize: '0.75rem', color: 'var(--text3)', textAlign: 'center' }}>
          Already have an account?{' '}
          <Link to="/login" style={{ color: 'var(--accent)', textDecoration: 'none' }}>
            Sign in
          </Link>
        </p>
      </div>
    </div>
  );
}
