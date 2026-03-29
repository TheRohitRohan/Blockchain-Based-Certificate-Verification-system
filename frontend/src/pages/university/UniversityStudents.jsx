import { useEffect, useState } from 'react';
import { useDataContext } from '../../context/DataContext';
import {
  PageSpinner, EmptyState, Modal, ConfirmModal, FormField, Spinner
} from '../../components/ui';
import toast from 'react-hot-toast';
import { deleteStudent, updateStudent } from '../../api/students.api';
import { Pencil, Trash2, Plus } from 'lucide-react';

const EMPTY = { username: '', email: '', password: '', full_name: '', student_id: '', enrollment_date: '' };
const today = () => new Date().toISOString().split('T')[0];

export default function UniversityStudents() {
  const { students, fetchStudents, addStudent, isLoading } = useDataContext();
  const [showAdd, setShowAdd] = useState(false);
  const [editTarget, setEditTarget] = useState(null);
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [form, setForm] = useState({ ...EMPTY, enrollment_date: today() });
  const [search, setSearch] = useState('');
  const [saving, setSaving] = useState(false);

  useEffect(() => { fetchStudents(); }, []);

  const set = k => e => setForm(f => ({ ...f, [k]: e.target.value }));
  const filtered = students.filter(s =>
    !search ||
    s.full_name?.toLowerCase().includes(search.toLowerCase()) ||
    s.email?.toLowerCase().includes(search.toLowerCase()) ||
    s.student_id?.toLowerCase().includes(search.toLowerCase())
  );

  function openAdd() { setForm({ ...EMPTY, enrollment_date: today() }); setShowAdd(true); }
  function openEdit(s) {
    setForm({ username: '', email: s.email ?? '', password: '', full_name: s.full_name ?? '', student_id: s.student_id ?? '', enrollment_date: s.enrollment_date ?? today() });
    setEditTarget(s);
  }

  async function handleAdd(e) {
    e.preventDefault();
    if (!form.username || !form.email || !form.password || !form.full_name || !form.student_id) {
      toast.error('All required fields must be filled'); return;
    }
    setSaving(true);
    const res = await addStudent(form);
    setSaving(false);
    if (res.success) { toast.success('Student added'); setShowAdd(false); }
    else toast.error(res.error ?? 'Failed');
  }

  async function handleEdit(e) {
    e.preventDefault();
    setSaving(true);
    try {
      const res = await updateStudent(editTarget.id, { full_name: form.full_name, enrollment_date: form.enrollment_date });
      if (res.success) { toast.success('Updated'); setEditTarget(null); fetchStudents(); }
      else toast.error(res.error ?? 'Update failed');
    } catch { toast.error('Update failed'); }
    setSaving(false);
  }

  async function handleDelete() {
    try {
      const res = await deleteStudent(deleteTarget.id);
      if (res.success) { toast.success('Deleted'); fetchStudents(); }
      else toast.error(res.error ?? 'Delete failed');
    } catch { toast.error('Delete failed'); }
    setDeleteTarget(null);
  }

  if (isLoading && students.length === 0) return <PageSpinner />;

  return (
    <div>
      <div className="page-header">
        <div>
          <p className="page-title">Students</p>
          <p className="page-sub">{students.length} enrolled</p>
        </div>
        <button className="btn-primary" onClick={openAdd}><Plus size={14} /> Add Student</button>
      </div>

      <div style={{ marginBottom: 16 }}>
        <input className="form-input" placeholder="Search students…" value={search} onChange={e => setSearch(e.target.value)} style={{ maxWidth: 320 }} />
      </div>

      <div className="table-wrap">
        {filtered.length === 0 ? (
          <EmptyState icon="○" title="No students found" />
        ) : (
          <table className="table">
            <thead>
              <tr><th>Student ID</th><th>Full Name</th><th>Email</th><th>Enrolled</th><th>Actions</th></tr>
            </thead>
            <tbody>
              {filtered.map(s => (
                <tr key={s.id}>
                  <td className="mono-id">{s.student_id}</td>
                  <td style={{ color: 'var(--text)' }}>{s.full_name}</td>
                  <td>{s.email}</td>
                  <td>{s.enrollment_date ?? '—'}</td>
                  <td>
                    <span style={{ display: 'flex', gap: 4 }}>
                      <button className="btn-icon" onClick={() => openEdit(s)}><Pencil size={13} /></button>
                      <button className="btn-icon" style={{ color: 'var(--red)' }} onClick={() => setDeleteTarget(s)}><Trash2 size={13} /></button>
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {showAdd && (
        <Modal title="Add Student" onClose={() => setShowAdd(false)}>
          <form onSubmit={handleAdd} className="form-grid">
            <div className="form-grid-2">
              <FormField label="Username" required>
                <input className="form-input" value={form.username} onChange={set('username')} placeholder="jdoe" />
              </FormField>
              <FormField label="Student ID" required>
                <input className="form-input" value={form.student_id} onChange={set('student_id')} placeholder="STU001" />
              </FormField>
            </div>
            <FormField label="Full Name" required>
              <input className="form-input" value={form.full_name} onChange={set('full_name')} placeholder="John Doe" />
            </FormField>
            <FormField label="Email" required>
              <input type="email" className="form-input" value={form.email} onChange={set('email')} />
            </FormField>
            <FormField label="Password" required>
              <input type="password" className="form-input" value={form.password} onChange={set('password')} placeholder="Min. 6 characters" />
            </FormField>
            <FormField label="Enrollment Date">
              <input type="date" className="form-input" value={form.enrollment_date} onChange={set('enrollment_date')} />
            </FormField>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              <button type="button" className="btn-ghost-sm" onClick={() => setShowAdd(false)}>Cancel</button>
              <button type="submit" className="btn-primary-sm" disabled={saving}>{saving ? <Spinner size={12} /> : null} Add Student</button>
            </div>
          </form>
        </Modal>
      )}

      {editTarget && (
        <Modal title="Edit Student" onClose={() => setEditTarget(null)}>
          <form onSubmit={handleEdit} className="form-grid">
            <FormField label="Full Name">
              <input className="form-input" value={form.full_name} onChange={set('full_name')} />
            </FormField>
            <FormField label="Enrollment Date">
              <input type="date" className="form-input" value={form.enrollment_date} onChange={set('enrollment_date')} />
            </FormField>
            <div style={{ display: 'flex', justifyContent: 'flex-end', gap: 8 }}>
              <button type="button" className="btn-ghost-sm" onClick={() => setEditTarget(null)}>Cancel</button>
              <button type="submit" className="btn-primary-sm" disabled={saving}>{saving ? <Spinner size={12} /> : null} Save</button>
            </div>
          </form>
        </Modal>
      )}

      {deleteTarget && (
        <ConfirmModal
          title="Remove Student"
          message={`Remove ${deleteTarget.full_name} from your university?`}
          confirmLabel="Remove"
          danger
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}
    </div>
  );
}
