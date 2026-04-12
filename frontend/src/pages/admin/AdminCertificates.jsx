import { useEffect, useState, useRef } from 'react';
import { useCertificatesContext } from '../../context/CertificatesContext';
import { useDataContext } from '../../context/DataContext';
import {
  PageSpinner, EmptyState, Modal, ConfirmModal, Badge, FormField, Spinner
} from '../../components/ui';
import { getCertificateDownloadUrl } from '../../api/certificates.api';
import toast from 'react-hot-toast';
import { Eye, ShieldX, Download, Upload, FileUp, X, Pencil, Trash2 } from 'lucide-react';

const DEGREE_TYPES = ['Bachelor', 'Master', 'Doctor', 'Diploma', 'Certificate', 'Associate'];

export default function AdminCertificates() {
  const {
    certificates = [], fetchCertificates, revoke, uploadCert,
    updateCert, deleteCert, isLoading, error: certError,
  } = useCertificatesContext();
  const { universities = [], students = [], fetchUniversities, fetchStudents } = useDataContext();

  const [search, setSearch] = useState('');
  const [filterUni, setFilterUni] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [detailTarget, setDetailTarget] = useState(null);
  const [revokeTarget, setRevokeTarget] = useState(null);
  const [revoking, setRevoking] = useState(false);

  // Upload modal state
  const [showUpload, setShowUpload] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [uploadForm, setUploadForm] = useState({
    university_id: '', student_id: '', course_name: '', degree_type: '', issue_date: '',
  });
  const [uploadFile, setUploadFile] = useState(null);
  const fileInputRef = useRef(null);

  // Edit modal state
  const [editTarget, setEditTarget] = useState(null);
  const [editForm, setEditForm] = useState({ course_name: '', degree_type: '', issue_date: '' });
  const [editing, setEditing] = useState(false);

  // Delete modal state
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleting, setDeleting] = useState(false);

  const setField = (k) => (e) => setUploadForm((f) => ({ ...f, [k]: e.target.value }));
  const setEditField = (k) => (e) => setEditForm((f) => ({ ...f, [k]: e.target.value }));

  useEffect(() => { fetchCertificates(); fetchUniversities(); }, []);

  useEffect(() => {
    if (showUpload) fetchStudents();
  }, [showUpload]);

  const filteredStudentsForUpload = uploadForm.university_id
    ? students.filter(s => String(s.university_id) === String(uploadForm.university_id))
    : students;

  const filtered = (Array.isArray(certificates) ? certificates : []).filter(c => {
    if (!c) return false;
    const matchSearch = !search ||
      c.certificate_id?.toLowerCase().includes(search.toLowerCase()) ||
      c.student_name?.toLowerCase().includes(search.toLowerCase());
    const matchUni = !filterUni || c.university_name === filterUni;
    const matchStatus = !filterStatus || c.status === filterStatus;
    return matchSearch && matchUni && matchStatus;
  });

  // ── Revoke ──────────────────────────────────────────────────
  async function handleRevoke() {
    if (!revokeTarget) { toast.error('No certificate selected'); return; }
    setRevoking(true);
    const res = await revoke(revokeTarget.certificate_id);
    setRevoking(false);
    if (res.success) { toast.success('Certificate revoked'); setRevokeTarget(null); setDetailTarget(null); }
    else toast.error(res.error ?? 'Revoke failed');
  }

  // ── Edit ────────────────────────────────────────────────────
  function openEdit(cert) {
    setEditTarget(cert);
    setEditForm({
      course_name: cert.course_name ?? '',
      degree_type: cert.degree_type ?? '',
      issue_date: cert.issue_date ?? '',
    });
  }

  async function handleEditSubmit(e) {
    e.preventDefault();
    if (!editTarget) return;
    const changes = {};
    if (editForm.course_name && editForm.course_name !== editTarget.course_name) changes.course_name = editForm.course_name;
    if (editForm.degree_type !== editTarget.degree_type) changes.degree_type = editForm.degree_type;
    if (editForm.issue_date && editForm.issue_date !== editTarget.issue_date) changes.issue_date = editForm.issue_date;

    if (Object.keys(changes).length === 0) {
      toast.error('No changes to save');
      return;
    }
    setEditing(true);
    const res = await updateCert(editTarget.certificate_id, changes);
    setEditing(false);
    if (res.success) {
      toast.success('Certificate updated');
      setEditTarget(null);
      setDetailTarget(null);
    } else {
      toast.error(res.error ?? 'Update failed');
    }
  }

  // ── Delete ──────────────────────────────────────────────────
  async function handleDelete() {
    if (!deleteTarget) return;
    setDeleting(true);
    const res = await deleteCert(deleteTarget.certificate_id);
    setDeleting(false);
    if (res.success) {
      toast.success('Certificate deleted');
      setDeleteTarget(null);
      setDetailTarget(null);
    } else {
      toast.error(res.error ?? 'Delete failed');
    }
  }

  // ── Upload ──────────────────────────────────────────────────
  function handleFileChange(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.type !== 'application/pdf') { toast.error('Only PDF files are allowed'); e.target.value = ''; return; }
    if (file.size > 10 * 1024 * 1024) { toast.error('File size exceeds 10MB limit'); e.target.value = ''; return; }
    setUploadFile(file);
  }

  function clearFile() {
    setUploadFile(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  }

  function resetUploadForm() {
    setUploadForm({ university_id: '', student_id: '', course_name: '', degree_type: '', issue_date: '' });
    setUploadFile(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  }

  async function handleUploadSubmit(e) {
    e.preventDefault();
    if (!uploadFile) { toast.error('Please select a PDF file'); return; }
    if (!uploadForm.university_id) { toast.error('Please select a university'); return; }
    if (!uploadForm.student_id || !uploadForm.course_name || !uploadForm.issue_date) {
      toast.error('Student, course name, and issue date are required');
      return;
    }
    setUploading(true);
    const res = await uploadCert({
      certificate: uploadFile,
      student_id: parseInt(uploadForm.student_id),
      university_id: parseInt(uploadForm.university_id),
      course_name: uploadForm.course_name,
      degree_type: uploadForm.degree_type || undefined,
      issue_date: uploadForm.issue_date,
    });
    setUploading(false);
    if (res.success) {
      toast.success(`Certificate uploaded: ${res.certificate_id}`);
      setShowUpload(false);
      resetUploadForm();
    } else {
      toast.error(res.error ?? 'Upload failed');
    }
  }

  if (isLoading && certificates.length === 0) return <PageSpinner />;

  if (certError && certificates.length === 0) {
    return (
      <div>
        <div className="page-header"><div><p className="page-title">Certificates</p></div></div>
        <div style={{ padding: '20px', color: 'var(--red)', textAlign: 'center' }}>
          Failed to load certificates: {certError}
        </div>
      </div>
    );
  }

  return (
    <div>
      <div className="page-header">
        <div>
          <p className="page-title">Certificates</p>
          <p className="page-sub">{certificates.length} total certificates</p>
        </div>
        <button className="btn-primary" onClick={() => setShowUpload(true)}>
          <Upload size={14} /> Upload Certificate
        </button>
      </div>

      <div className="filter-bar">
        <input className="form-input" placeholder="Search by ID or student…" value={search} onChange={e => setSearch(e.target.value)} />
        <select className="form-select" value={filterUni} onChange={e => setFilterUni(e.target.value)}>
          <option value="">All Universities</option>
          {universities.map(u => <option key={u.id} value={u.name}>{u.name}</option>)}
        </select>
        <select className="form-select" value={filterStatus} onChange={e => setFilterStatus(e.target.value)}>
          <option value="">All Status</option>
          <option value="active">Active</option>
          <option value="revoked">Revoked</option>
        </select>
      </div>

      <div className="table-wrap">
        {filtered.length === 0 ? (
          <EmptyState icon="○" title="No certificates found" />
        ) : (
          <table className="table">
            <thead>
              <tr>
                <th>Certificate ID</th><th>Student</th><th>University</th>
                <th>Course</th><th>Issue Date</th><th>Status</th><th>Actions</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map(c => (
                <tr key={c?.certificate_id ?? c?.id}>
                  <td className="mono-id">{c?.certificate_id}</td>
                  <td>{c.student_name ?? '—'}</td>
                  <td>{c.university_name ?? '—'}</td>
                  <td>{c.course_name}</td>
                  <td>{c.issue_date}</td>
                  <td><Badge status={c.status} /></td>
                  <td>
                    <span style={{ display: 'flex', gap: 4 }}>
                      <button className="btn-icon" title="View" onClick={() => setDetailTarget(c)}><Eye size={13} /></button>
                      {c.status === 'active' && (
                        <button className="btn-icon" title="Edit" onClick={() => openEdit(c)}><Pencil size={13} /></button>
                      )}
                      {c.status === 'active' && (
                        <button className="btn-icon" title="Revoke" style={{ color: 'var(--red)' }} onClick={() => setRevokeTarget(c)}><ShieldX size={13} /></button>
                      )}
                      {/* Delete — admin only (this page is already admin-only) */}
                      <button className="btn-icon" title="Delete" style={{ color: 'var(--red)' }} onClick={() => setDeleteTarget(c)}><Trash2 size={13} /></button>
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {/* Detail Modal */}
      {detailTarget && (
        <Modal
          title="Certificate Details"
          onClose={() => setDetailTarget(null)}
          footer={
            <>
              <a className="btn-ghost-sm" href={getCertificateDownloadUrl(detailTarget?.certificate_id)} target="_blank" rel="noreferrer"><Download size={12} /> PDF</a>
              {detailTarget.status === 'active' && (
                <button className="btn-ghost-sm" onClick={() => openEdit(detailTarget)}>Edit</button>
              )}
              {detailTarget.status === 'active' && (
                <button className="btn-danger-sm" onClick={() => setRevokeTarget(detailTarget)}>Revoke</button>
              )}
              <button className="btn-danger-sm" onClick={() => setDeleteTarget(detailTarget)}>Delete</button>
              <button className="btn-ghost-sm" onClick={() => setDetailTarget(null)}>Close</button>
            </>
          }
        >
          <div className="detail-group">
            <p className="detail-group-title">Student</p>
            {[['Name', detailTarget.student_name], ['Email', detailTarget.student_email], ['Student ID', detailTarget.student_id_code]].map(([k,v]) => (
              <div key={k} className="detail-row"><span className="detail-key">{k}</span><span className="detail-val">{v ?? '—'}</span></div>
            ))}
          </div>
          <div className="detail-group">
            <p className="detail-group-title">Certificate</p>
            {[['Course', detailTarget.course_name], ['Degree', detailTarget.degree_type], ['Issue Date', detailTarget.issue_date], ['Status', detailTarget.status]].map(([k,v]) => (
              <div key={k} className="detail-row"><span className="detail-key">{k}</span><span className="detail-val">{v ?? '—'}</span></div>
            ))}
          </div>
          <div className="detail-group">
            <p className="detail-group-title">Blockchain</p>
            {[['TX Hash', detailTarget.blockchain_tx_hash], ['Block', detailTarget.block_number], ['Chain ID', detailTarget.chain_id]].map(([k,v]) => (
              <div key={k} className="detail-row"><span className="detail-key">{k}</span><span className={`detail-val ${k === 'TX Hash' ? 'mono' : ''}`}>{v ?? '—'}</span></div>
            ))}
          </div>
        </Modal>
      )}

      {/* Revoke Modal */}
      {revokeTarget && (
        <ConfirmModal
          title="Revoke Certificate"
          message={`Revoke certificate ${revokeTarget?.certificate_id}? This action cannot be undone.`}
          confirmLabel={revoking ? 'Revoking…' : 'Revoke Certificate'}
          danger
          onConfirm={handleRevoke}
          onCancel={() => setRevokeTarget(null)}
        />
      )}

      {/* Delete Modal — admin only */}
      {deleteTarget && (
        <ConfirmModal
          title="Delete Certificate"
          message={`Permanently delete certificate ${deleteTarget?.certificate_id}? This removes it from the database entirely and cannot be undone. This does NOT revoke it on the blockchain.`}
          confirmLabel={deleting ? 'Deleting…' : 'Delete Certificate'}
          danger
          onConfirm={handleDelete}
          onCancel={() => setDeleteTarget(null)}
        />
      )}

      {/* ── Edit Certificate Modal ───────────────────────────── */}
      {editTarget && (
        <Modal
          title="Edit Certificate"
          onClose={() => setEditTarget(null)}
          footer={
            <>
              <button className="btn-ghost-sm" onClick={() => setEditTarget(null)}>Cancel</button>
              <button className="btn-primary" disabled={editing} onClick={handleEditSubmit}>
                {editing ? <><Spinner size={12} /> Saving…</> : 'Save Changes'}
              </button>
            </>
          }
        >
          <p style={{ fontSize: '0.72rem', color: 'var(--text3)', marginBottom: 16 }}>
            Editing <strong style={{ color: 'var(--text)' }}>{editTarget?.certificate_id}</strong> — {editTarget?.student_name}
          </p>
          <form onSubmit={handleEditSubmit} className="form-grid">
            <FormField label="Course Name">
              <input className="form-input" value={editForm.course_name} onChange={setEditField('course_name')} disabled={editing} />
            </FormField>
            <FormField label="Degree Type">
              <select className="form-select" value={editForm.degree_type} onChange={setEditField('degree_type')} disabled={editing}>
                <option value="">None</option>
                {DEGREE_TYPES.map(d => <option key={d} value={d}>{d}</option>)}
              </select>
            </FormField>
            <FormField label="Issue Date">
              <input type="date" className="form-input" value={editForm.issue_date} onChange={setEditField('issue_date')} disabled={editing} />
            </FormField>
          </form>
        </Modal>
      )}

      {/* ── Upload Certificate Modal ─────────────────────────── */}
      {showUpload && (
        <Modal
          title="Upload Existing Certificate"
          onClose={() => { setShowUpload(false); resetUploadForm(); }}
          footer={
            <>
              <button className="btn-ghost-sm" onClick={() => { setShowUpload(false); resetUploadForm(); }}>Cancel</button>
              <button className="btn-primary" disabled={uploading} onClick={handleUploadSubmit}>
                {uploading ? <><Spinner size={12} /> Uploading…</> : <><FileUp size={12} /> Upload & Anchor</>}
              </button>
            </>
          }
        >
          <form onSubmit={handleUploadSubmit} className="form-grid">
            <FormField label="Certificate PDF" required>
              <div className="upload-file-zone">
                {uploadFile ? (
                  <div className="upload-file-selected">
                    <span className="upload-file-name">{uploadFile.name}</span>
                    <span className="upload-file-size">({(uploadFile.size / 1024 / 1024).toFixed(2)} MB)</span>
                    <button type="button" className="btn-icon" onClick={clearFile} title="Remove file"><X size={14} /></button>
                  </div>
                ) : (
                  <label className="upload-file-label">
                    <Upload size={18} style={{ opacity: 0.5 }} />
                    <span>Click to select a PDF file</span>
                    <span style={{ fontSize: '0.68rem', color: 'var(--text3)' }}>Max 10MB • PDF only</span>
                    <input ref={fileInputRef} type="file" accept=".pdf,application/pdf" onChange={handleFileChange} style={{ display: 'none' }} />
                  </label>
                )}
              </div>
            </FormField>
            <FormField label="University" required>
              <select className="form-select" value={uploadForm.university_id} onChange={(e) => setUploadForm(f => ({ ...f, university_id: e.target.value, student_id: '' }))} disabled={uploading}>
                <option value="">Select university…</option>
                {universities.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
              </select>
            </FormField>
            <FormField label="Student" required>
              <select className="form-select" value={uploadForm.student_id} onChange={setField('student_id')} disabled={uploading || !uploadForm.university_id}>
                <option value="">{uploadForm.university_id ? 'Select student…' : 'Select a university first'}</option>
                {filteredStudentsForUpload.map(s => <option key={s.id} value={s.id}>{s.full_name} ({s.student_id})</option>)}
              </select>
            </FormField>
            <FormField label="Course Name" required>
              <input className="form-input" value={uploadForm.course_name} onChange={setField('course_name')} placeholder="e.g. Computer Science" disabled={uploading} />
            </FormField>
            <FormField label="Degree Type">
              <select className="form-select" value={uploadForm.degree_type} onChange={setField('degree_type')} disabled={uploading}>
                <option value="">Select degree (optional)…</option>
                {DEGREE_TYPES.map(d => <option key={d} value={d}>{d}</option>)}
              </select>
            </FormField>
            <FormField label="Issue Date" required>
              <input type="date" className="form-input" value={uploadForm.issue_date} onChange={setField('issue_date')} disabled={uploading} />
            </FormField>
          </form>
        </Modal>
      )}
    </div>
  );
}
