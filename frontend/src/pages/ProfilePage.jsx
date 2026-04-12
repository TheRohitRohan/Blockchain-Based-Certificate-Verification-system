import { useState, useRef } from 'react';
import { useAuthContext } from '../context/AuthContext';
import { useNavigate } from 'react-router';
import { FormField, Spinner } from '../components/ui';
import axiosInstance from '../api/axiosInstance';
import toast from 'react-hot-toast';
import { User, Lock, LogOut, KeyRound, Camera } from 'lucide-react';

const API_BASE = import.meta.env.VITE_API_URL ?? '/api';

export default function ProfilePage() {
  const { user, role, logout } = useAuthContext();
  const navigate = useNavigate();
  const [form, setForm] = useState({ current_password: '', new_password: '', confirm_password: '' });
  const [saving, setSaving] = useState(false);
  const [avatarPath, setAvatarPath] = useState(user?.avatar_path ?? null);
  const [uploadingAvatar, setUploadingAvatar] = useState(false);
  const avatarInputRef = useRef(null);
  const set = k => e => setForm(f => ({ ...f, [k]: e.target.value }));

  // Derive the display name and initials
  const displayName = user?.full_name ?? user?.name ?? user?.admin_name ?? user?.email ?? '';
  const initials = displayName
    .split(' ')
    .map(w => w[0])
    .join('')
    .toUpperCase()
    .slice(0, 2) || '?';

  // Build avatar src from avatar_path
  const avatarSrc = avatarPath
    ? `${API_BASE.replace('/api', '')}/${avatarPath}`
    : null;

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

  function handleAvatarClick() {
    avatarInputRef.current?.click();
  }

  async function handleAvatarChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;

    // Client-side validation
    const allowed = ['image/jpeg', 'image/png'];
    if (!allowed.includes(file.type)) {
      toast.error('Only JPG and PNG files are allowed');
      e.target.value = '';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      toast.error('File size exceeds 2MB limit');
      e.target.value = '';
      return;
    }

    const formData = new FormData();
    formData.append('avatar', file);

    setUploadingAvatar(true);
    try {
      const res = await axiosInstance.put('/auth/profile/avatar', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      if (res.data?.success && res.data?.data?.avatar_path) {
        setAvatarPath(res.data.data.avatar_path);
        toast.success('Avatar updated');
      } else {
        toast.error(res.data?.error ?? 'Failed to upload avatar');
      }
    } catch (err) {
      toast.error(err.response?.data?.error ?? 'Failed to upload avatar');
    }
    setUploadingAvatar(false);
    e.target.value = ''; // reset so same file can be re-selected
  }

  return (
    <div className="profile-wrap">
      <div className="page-header">
        <div>
          <p className="page-title">Profile</p>
          <p className="page-sub">Manage your account settings</p>
        </div>
      </div>

      {/* Avatar + info */}
      <div className="section-card">
        <div className="section-card-header"><span className="section-card-title" style={{ display: 'flex', alignItems: 'center', gap: 8 }}><User size={15} /> Account Information</span></div>

        {/* Avatar */}
        <div className="profile-avatar-section">
          <div className="profile-avatar-wrap" onClick={handleAvatarClick} title="Click to change avatar">
            {avatarSrc ? (
              <img src={avatarSrc} alt="Avatar" className="profile-avatar-img" />
            ) : (
              <span className="profile-avatar-initials">{initials}</span>
            )}
            <div className="profile-avatar-overlay">
              {uploadingAvatar ? <Spinner size={16} /> : <Camera size={16} />}
            </div>
            <input
              ref={avatarInputRef}
              type="file"
              accept="image/jpeg,image/png,.jpg,.jpeg,.png"
              onChange={handleAvatarChange}
              style={{ display: 'none' }}
            />
          </div>
          <div className="profile-avatar-hint">Click to upload • JPG, PNG • Max 2MB</div>
        </div>

        <div className="form-grid" style={{ marginTop: 16 }}>
          {[
            ['Email', user?.email],
            ['Role', role],
            ['Name', displayName],
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
