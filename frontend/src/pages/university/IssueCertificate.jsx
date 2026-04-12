import { useEffect, useState } from 'react';
import { createPortal } from 'react-dom';
import { useDataContext } from '../../context/DataContext';
import { useCertificatesContext } from '../../context/CertificatesContext';
import { useAuthContext } from '../../context/AuthContext';
import { FormField, Spinner } from '../../components/ui';
import toast from 'react-hot-toast';
import { BadgeCheck, Copy, Loader2 } from 'lucide-react';

const DEGREE_TYPES = ['Bachelor', 'Master', 'Doctor', 'Diploma', 'Certificate', 'Associate'];
const today = () => new Date().toISOString().split('T')[0];

export default function IssueCertificate() {
  const { user } = useAuthContext();
  const { students, fetchStudents } = useDataContext();
  const { issueCertificate, isLoading } = useCertificatesContext();

  const [form, setForm] = useState({ student_id: '', course_name: '', degree_type: '', issue_date: today() });
  const [result, setResult] = useState(null);
  const set = k => e => setForm(f => ({ ...f, [k]: e.target.value }));

  useEffect(() => { fetchStudents(); }, []);

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form.student_id || !form.course_name || !form.degree_type || !form.issue_date) {
      toast.error('All fields are required'); return;
    }
    const payload = {
      student_id: parseInt(form.student_id),
      university_id: user?.university_id,
      course_name: form.course_name,
      degree_type: form.degree_type,
      issue_date: form.issue_date,
    };
    const res = await issueCertificate(payload);
    if (res.success) {
      setResult(res);
      setForm({ student_id: '', course_name: '', degree_type: '', issue_date: today() });
    } else {
      toast.error(res.error ?? 'Failed to issue certificate');
    }
  }

  function copyShareLink() {
    if (!result?.certificate_id) return;
    navigator.clipboard.writeText(`${window.location.origin}/verify?cert=${result.certificate_id}`);
    toast.success('Link copied');
  }

  const loadingOverlay =
    isLoading &&
    createPortal(
      <div
        className="issue-cert-overlay"
        role="alert"
        aria-busy="true"
        aria-live="polite"
        aria-label="Issuing certificate, please wait"
      >
        <div className="issue-cert-progress-panel">
          <div className="issue-cert-progress-icon">
            <Spinner size={36} />
          </div>
          <p className="issue-cert-progress-title">Issuing certificate…</p>
          <p className="issue-cert-progress-lead">
            Your request is still running. This can take <strong>several minutes</strong> while we build the PDF,
            sign it, and anchor it on the blockchain.
          </p>
          <ul className="issue-cert-progress-steps">
            <li>
              <Loader2 className="issue-cert-step-icon" size={14} aria-hidden />
              Preparing certificate data and PDF
            </li>
            <li>
              <Loader2 className="issue-cert-step-icon" size={14} aria-hidden />
              Waiting for blockchain confirmation (mining)
            </li>
            <li>
              <Loader2 className="issue-cert-step-icon" size={14} aria-hidden />
              Saving and syncing your certificate list
            </li>
          </ul>
          <p className="issue-cert-progress-foot">
            Keep this tab open. Closing or refreshing may interrupt the process.
          </p>
        </div>
      </div>,
      document.body
    );

  return (
    <div>
      {loadingOverlay}
      <div className="page-header">
        <div>
          <p className="page-title">Issue Certificate</p>
          <p className="page-sub">
            Create a new verified certificate for a student. On-chain registration and PDF generation can take several
            minutes — keep this page open until the request completes.
          </p>
        </div>
      </div>

      <div style={{ maxWidth: 520 }}>
        <div className="section-card">
          <div className="section-card-header" style={{ marginBottom: 0 }}>
            <span className="section-card-title">Certificate Details</span>
          </div>
          <div style={{ height: 20 }} />
          <form onSubmit={handleSubmit} className="form-grid" aria-busy={isLoading}>
            <FormField label="Select Student" required>
              <select
                className="form-select"
                value={form.student_id}
                onChange={set('student_id')}
                disabled={isLoading}
              >
                <option value="">Search students…</option>
                {students.map(s => (
                  <option key={s.id} value={s.id}>
                    {s.full_name} ({s.student_id})
                  </option>
                ))}
              </select>
            </FormField>

            <FormField label="Course Name" required>
              <input
                className="form-input"
                value={form.course_name}
                onChange={set('course_name')}
                placeholder="e.g. Computer Science"
                disabled={isLoading}
              />
            </FormField>

            <FormField label="Degree Type" required>
              <select className="form-select" value={form.degree_type} onChange={set('degree_type')} disabled={isLoading}>
                <option value="">Select degree…</option>
                {DEGREE_TYPES.map(d => <option key={d} value={d}>{d}</option>)}
              </select>
            </FormField>

            <FormField label="Issue Date" required>
              <input
                type="date"
                className="form-input"
                value={form.issue_date}
                onChange={set('issue_date')}
                disabled={isLoading}
              />
            </FormField>

            <button type="submit" className="btn-primary" disabled={isLoading} style={{ justifyContent: 'center' }}>
              {isLoading ? <><Spinner size={13} /> Issuing…</> : <><BadgeCheck size={14} /> Issue Certificate</>}
            </button>
          </form>
        </div>

        {/* Success card */}
        {result && (
          <div className="issue-success">
            <p className="issue-success-title">✓ Certificate Issued Successfully</p>
            <div>
              <p style={{ fontSize: '0.72rem', color: 'var(--text3)', marginBottom: 4 }}>Certificate ID</p>
              <span className="issue-cert-id">{result.certificate_id}</span>
            </div>
            <div>
              <p style={{ fontSize: '0.72rem', color: 'var(--text3)', marginBottom: 6 }}>Share Link</p>
              <div className="share-link-row">
                <input
                  className="share-link-input"
                  readOnly
                  value={`${window.location.origin}/verify?cert=${result.certificate_id}`}
                />
                <button className="btn-ghost" onClick={copyShareLink}><Copy size={13} /> Copy</button>
              </div>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
