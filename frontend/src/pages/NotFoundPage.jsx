import { Link } from 'react-router';

export default function NotFoundPage() {
  return (
    <div style={{
      minHeight: '100vh', display: 'flex', flexDirection: 'column',
      alignItems: 'center', justifyContent: 'center', background: 'var(--bg)', gap: 12
    }}>
      <span style={{ fontSize: '3rem', opacity: 0.2 }}>○</span>
      <p style={{ fontSize: '1rem', fontWeight: 600, color: 'var(--text)' }}>Page not found</p>
      <p style={{ fontSize: '0.8rem', color: 'var(--text3)' }}>The page you're looking for doesn't exist.</p>
      <Link to="/login" style={{ marginTop: 8, fontSize: '0.8rem', color: 'var(--text2)', textDecoration: 'none' }}>
        ← Back to login
      </Link>
    </div>
  );
}
