import { useEffect, useState } from 'react';
import { useDataContext } from '../../context/DataContext';
import {
  PageSpinner, EmptyState, Modal, ConfirmModal, FormField, Spinner, Badge
} from '../../components/ui';
import toast from 'react-hot-toast';
import { deleteUniversity, updateUniversity } from '../../api/universities.api';
import { Pencil, Trash2, Plus } from 'lucide-react';

const EMPTY_FORM = { name: '', code: '', address: '', contact_email: '', contact_phone: '' };

/**
 * Must be defined outside AdminUniversities — an inner component is recreated every render,
 * so React remounts inputs on each keystroke and focus / typing breaks.
 */
function AdminUniversityForm({ form, fieldChange, onSubmit, onCancel, saving }) {
  return (
    <form onSubmit={onSubmit} className="form-grid">
      <div className="form-grid-2">
        <FormField label="Name" required>
          <input className="form-input" value={form.name} onChange={fieldChange('name')} placeholder="Tech University" />
        </FormField>
        <FormField label="Code" required>
          <input className="form-input" value={form.code} onChange={fieldChange('code')} placeholder="TECH001" />
        </FormField>
      </div>
      <FormField label="Address">
        <textarea className="form-textarea" value={form.address} onChange={fieldChange('address')} placeholder="123 Main St…" />
      </FormField>
      <div className="form-grid-2">
        <FormField label="Contact Email">
          <input type="email" className="form-input" value={form.contact_email} onChange={fieldChange('contact_email')} />
        </FormField>
        <FormField label="Contact Phone">
          <input className="form-input" value={form.contact_phone} onChange={fieldChange('contact_phone')} />
        </FormField>
      </div>
      <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8, marginTop: 4 }}>
        <button type="button" className="btn-ghost-sm" onClick={onCancel}>Cancel</button>
        <button type="submit" className="btn-primary-sm" disabled={saving}>
          {saving ? <Spinner size={12} /> : null} Save
        </button>
      </div>
    </form>
  );
}

export default function AdminUniversities() {
  const { universities = [], fetchUniversities, addUniversity, isLoading, error: dataError } = useDataContext();

  const [showAdd, setShowAdd] = useState(false);
  const [editTarget, setEditTarget] = useState(null);   // university object
  const [deleteTarget, setDeleteTarget] = useState(null); // university object
  const [form, setForm] = useState(EMPTY_FORM);
  const [saving, setSaving] = useState(false);

  useEffect(() => { fetchUniversities(); }, []);

  const set = k => e => setForm(f => ({ ...f, [k]: e.target.value }));

  function openAdd() { setForm(EMPTY_FORM); setShowAdd(true); }
  function openEdit(u) {
    setForm({ name: u.name, code: u.code, address: u.address ?? '', contact_email: u.contact_email ?? '', contact_phone: u.contact_phone ?? '' });
    setEditTarget(u);
  }

  async function handleAdd(e) {
    e.preventDefault();
    if (!form.name || !form.code) { toast.error('Name and code are required'); return; }
    setSaving(true);
    const res = await addUniversity(form);
    setSaving(false);
    if (res.success) {
      if (res.signing_key_generated === false) {
        toast.success('University added, but signing key generation failed. Fix OpenSSL (OPENSSL_CONF in .env) and run Generate key, or certificates will be unsigned.');
      } else {
        toast.success('University added. Signing key created — new certificates can be signed.');
      }
      setShowAdd(false);
    } else toast.error(res.error ?? 'Failed to add university');
  }

  async function handleEdit(e) {
    e.preventDefault();
    setSaving(true);
    try {
      const res = await updateUniversity(editTarget.id, form);
      if (res.success) { toast.success('Updated'); setEditTarget(null); fetchUniversities(); }
      else toast.error(res.error ?? 'Update failed');
    } catch { toast.error('Update failed'); }
    setSaving(false);
  }

  async function handleDelete() {
    try {
      const res = await deleteUniversity(deleteTarget.id);
      if (res.success) { toast.success('Deleted'); fetchUniversities(); }
      else toast.error(res.error ?? 'Delete failed');
    } catch { toast.error('Delete failed'); }
    setDeleteTarget(null);
  }

  if (isLoading && universities.length === 0) return <PageSpinner />;

  if (dataError && universities.length === 0) {
    return (
      <div>
        <div className="page-header">
          <div>
            <p className="page-title">Universities</p>
          </div>
        </div>
        <div style={{ padding: '20px', color: 'var(--red)', textAlign: 'center' }}>
          Failed to load universities: {dataError}
        </div>
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <div>
          <p className="page-title">Universities</p>
          <p className="page-sub">Manage all registered universities</p>
        </div>
        <button className="btn-primary" onClick={openAdd}><Plus size={14} /> Add University</button>
      </div>

      <div className="table-wrap">
        {universities.length === 0 ? (
          <EmptyState icon="🏛" title="No universities yet" sub="Click Add University to create one" />
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>ID</th><th>Name</th><th>Code</th><th>Contact</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {universities.map(u => (
                <tr key={u.id}>
                  <td className="mono-id">{u.id}</td>
                  <td style={{ color: 'var(--text)' }}>{u.name}</td>
                  <td className="mono-id">{u.code}</td>
                  <td>{u.contact_email ?? '—'}</td>
                  <td><Badge status={u.is_active ? 'active' : 'inactive'} /></td>
                  <td>
                    <span style={{ display: 'flex', gap: 4 }}>
                      <button className="btn-icon" title="Edit" onClick={() => openEdit(u)}><Pencil size={13} /></button>
                      <button className="btn-icon" title="Delete" style={{ color: 'var(--red)' }} onClick={() => setDeleteTarget(u)}><Trash2 size={13} /></button>
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {showAdd && (
        <Modal title="Add University" onClose={() => setShowAdd(false)}>
          <AdminUniversityForm
            form={form}
            fieldChange={set}
            onSubmit={handleAdd}
            onCancel={() => setShowAdd(false)}
            saving={saving}
          />
        </Modal>
      )}

      {editTarget && (
        <Modal title="Edit University" onClose={() => setEditTarget(null)}>
          <AdminUniversityForm
            form={form}
            fieldChange={set}
            onSubmit={handleEdit}
            onCancel={() => setEditTarget(null)}
            saving={saving}
          />
        </Modal>
      )}

      {deleteTarget && (
        <ConfirmModal
          title="Delete University"
          message={`Delete "${deleteTarget.name}"? This action cannot be undone.`}
          confirmLabel="Delete"
          danger
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}
    </div>
  );
}
