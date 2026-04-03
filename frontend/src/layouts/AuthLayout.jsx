import { Outlet } from 'react-router';

export default function AuthLayout() {
  return (
    <div className="auth-shell">
      <div className="auth-brand">
        <span className="logo-mark">⬡</span>
        <span className="logo-text">CertiLedger</span>
      </div>
      <Outlet />
    </div>
  );
}
