import { useState } from 'react';
import { useAuthContext } from '../context/AuthContext';
import { useNavigate } from 'react-router';
import { FormField, Spinner } from '../components/ui';
import axiosInstance from '../api/axiosInstance';
import toast from 'react-hot-toast';
import { User, Lock, LogOut, KeyRound } from 'lucide-react';

export default function ProfilePage() {
  const { user, role, logout } = useAuthContext();
  const navigate = useNavigate();
  const [form, setForm] = useState({ current_password: '', new_password: '', confirm_password: '' });
  const [saving, setSaving] = useState(false);
  const set = k => e => setForm(f => ({ ...f, [k]: e.target.value }));

  async function handlePasswordChange(e) {
    e.preventDefault();
    if (form.new_password !== form.confirm_password) { toast.error('Passwords do not match'); return; }
    if (form.new_password.length < 6) { toast.error('Password must be at least 6 characters'); return; }
    setSaving(true);
    try {
      await axiosInstance.post('/auth/change-password', {
        current_password: form.current_password,
        new_password: form.new_password,
      });
      toast.success('Password changed');
      setForm({ current_password: '', new_password: '', confirm_password: '' });
    } catch (err) {
      toast.error(err.response?.data?.error ?? 'Failed to change password');
    }
    setSaving(false);
  }

  function handleLogout() {
    logout();
    toast.success('Signed out');
    navigate('/login');
  }

  return (
    <div className="profile-wrap">
      <div className="page-header">
        <div>
          <p className="page-title">Profile</p>
          <p className="page-sub">Manage your account settings</p>
        </div>
      </div>

      {/* Profile info */}
      <div className="section-card">
        <div className="section-card-header"><span className="section-card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}><User size={15} /> Account Information</span></div>
        <div className="form-grid">
          {[
            ['Email', user?.email],
            ['Role', role],
            ['Name', user?.full_name ?? user?.name ?? user?.admin_name],
          ].map(([label, val]) => (
            <div key={label} className="detail-row">
              <span className="detail-key">{label}</span>
              <span className="detail-val" style={{ textAlign: 'right' }}>{val ?? '—'}</span>
            </div>
          ))}
        </div>
      </div>

      {/* Change password */}
      <div className="section-card">
        <div className="section-card-header"><span className="section-card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}><KeyRound size={15} /> Change Password</span></div>
        <form onSubmit={handlePasswordChange} className="form-grid">
          <FormField label="Current Password" required>
            <input type="password" className="form-input" value={form.current_password} onChange={set('current_password')} />
          </FormField>
          <FormField label="New Password" required>
            <input type="password" className="form-input" value={form.new_password} onChange={set('new_password')} placeholder="Min. 6 characters" />
          </FormField>
          <FormField label="Confirm Password" required>
            <input type="password" className="form-input" value={form.confirm_password} onChange={set('confirm_password')} />
          </FormField>
          <div>
            <button type="submit" className="btn-primary" disabled={saving}>
            {saving ? <Spinner size={13} /> : <Lock size={13} />} Update Password
          </button>
          </div>
        </form>
      </div>

      {/* Logout */}
      <button className="btn-danger" style={{ width: '100%', justifyContent: 'center' }} onClick={handleLogout}>
        <LogOut size={14} /> Sign out of account
      </button>
    </div>
  );
}
