import React from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router';
import { useAuthContext } from '../context/AuthContext';
import { useTheme } from '../context/ThemeContext';
import toast from 'react-hot-toast';
import {
  LayoutDashboard, University, ScrollText, Users,
  FilePlus, User, LogOut, Sun, Moon, ShieldCheck
} from 'lucide-react';

const NAV = {
  admin: [
    { to: '/admin',                label: 'Dashboard',   Icon: LayoutDashboard },
    { to: '/admin/universities',   label: 'Universities', Icon: University },
    { to: '/admin/certificates',   label: 'Certificates', Icon: ScrollText },
    { to: '/profile',              label: 'Profile',      Icon: User },
  ],
  university: [
    { to: '/university',             label: 'Dashboard',        Icon: LayoutDashboard },
    { to: '/university/students',    label: 'Students',         Icon: Users },
    { to: '/university/issue',       label: 'Issue Certificate', Icon: FilePlus },
    { to: '/university/certificates',label: 'Certificates',     Icon: ScrollText },
    { to: '/profile',                label: 'Profile',          Icon: User },
  ],
  student: [
    { to: '/student', label: 'My Certificates', Icon: ScrollText },
    { to: '/profile', label: 'Profile',         Icon: User },
  ],
};

export default function DashboardLayout() {
  const { user, role, logout } = useAuthContext();
  const { theme, toggle } = useTheme();
  const navigate = useNavigate();

  const links = NAV[role] ?? [];

  function handleLogout() {
    logout();
    toast.success('Signed out');
    navigate('/login');
  }

  return (
    <div className="app-shell">
      {/* ── Sidebar ─────────────────────────────────── */}
      <aside className="sidebar">
        <div className="sidebar-logo">
          <ShieldCheck size={18} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
          <span className="logo-text">CertiLedger</span>
        </div>

        <nav className="sidebar-nav">
          {links.map(({ to, label, Icon }) => (
            <NavLink
              key={to}
              to={to}
              end={to === '/admin' || to === '/university' || to === '/student'}
              className={({ isActive }) => `sidebar-link ${isActive ? 'active' : ''}`}
            >
              <Icon size={15} strokeWidth={1.6} className="link-icon" />
              <span>{label}</span>
            </NavLink>
          ))}
        </nav>

        <div className="sidebar-footer">
          <div className="user-chip">
            <span className="user-avatar">
              {(user?.full_name ?? user?.email ?? '?').charAt(0).toUpperCase()}
            </span>
            <div className="user-info">
              <span className="user-name">{user?.full_name ?? user?.email}</span>
              <span className="user-role">{role}</span>
            </div>
          </div>

          {/* Theme toggle */}
          <button className="theme-toggle-btn" onClick={toggle} title="Toggle theme">
            {theme === 'dark'
              ? <><Sun size={13} /><span>Light mode</span></>
              : <><Moon size={13} /><span>Dark mode</span></>
            }
          </button>

          <button className="logout-btn" onClick={handleLogout}>
            <LogOut size={13} />
            Sign out
          </button>
        </div>
      </aside>

      {/* ── Main content ─────────────────────────────── */}
      <main className="content-area">
        <Outlet />
      </main>
    </div>
  );
}
