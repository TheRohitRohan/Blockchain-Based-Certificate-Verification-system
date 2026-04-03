import { useEffect, useState } from 'react';
import { useCertificatesContext } from '../../context/CertificatesContext';
import { PageSpinner, EmptyState, Modal, ConfirmModal, Badge } from '../../components/ui';
import { getCertificateDownloadUrl } from '../../api/certificates.api';
import toast from 'react-hot-toast';
import { Eye, ShieldX, Download } from 'lucide-react';

export default function UniversityCertificates() {
  const { certificates, fetchCertificates, revoke, isLoading } = useCertificatesContext();
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [detailTarget, setDetailTarget] = useState(null);
  const [revokeTarget, setRevokeTarget] = useState(null);
  const [revoking, setRevoking] = useState(false);

  useEffect(() => { fetchCertificates(); }, []);

  const filtered = certificates.filter(c => {
    const matchSearch = !search ||
      c.certificate_id?.toLowerCase().includes(search.toLowerCase()) ||
      c.student_name?.toLowerCase().includes(search.toLowerCase());
    const matchStatus = !filterStatus || c.status === filterStatus;
    return matchSearch && matchStatus;
  });

  async function handleRevoke() {
    setRevoking(true);
    const res = await revoke(revokeTarget?.certificate_id);
    setRevoking(false);
    if (res.success) { toast.success('Certificate revoked'); setRevokeTarget(null); setDetailTarget(null); }
    else toast.error(res.error ?? 'Revoke failed');
  }

  if (isLoading && certificates.length === 0) return <PageSpinner />;

  return (
    <div>
      <div className="page-header">
        <div>
          <p className="page-title">Issued Certificates</p>
          <p className="page-sub">{certificates.length} certificates issued</p>
        </div>
      </div>

      <div className="filter-bar">
        <input className="form-input" placeholder="Search by ID or student…" value={search} onChange={e => setSearch(e.target.value)} />
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
              <tr><th>Certificate ID</th><th>Student</th><th>Course</th><th>Degree</th><th>Issue Date</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              {filtered.map(c => (
                <tr key={c.certificate_id}>
                  <td className="mono-id">{c?.certificate_id}</td>
                  <td>{c.student_name ?? '—'}</td>
                  <td>{c.course_name}</td>
                  <td>{c.degree_type}</td>
                  <td>{c.issue_date}</td>
                  <td><Badge status={c.status} /></td>
                  <td>
                    <span style={{ display: 'flex', gap: 4 }}>
                      <button className="btn-icon" onClick={() => setDetailTarget(c)}><Eye size={13} /></button>
                      <a className="btn-icon" href={getCertificateDownloadUrl(c.certificate_id)} target="_blank" rel="noreferrer" title="Download PDF"><Download size={13} /></a>
                      {c.status === 'active' && (
                        <button className="btn-icon" style={{ color: 'var(--red)' }} onClick={() => setRevokeTarget(c)}><ShieldX size={13} /></button>
                      )}
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        )}
      </div>

      {detailTarget && (
        <Modal title="Certificate Details" onClose={() => setDetailTarget(null)}
          footer={
            <>
              <a className="btn-ghost-sm" href={getCertificateDownloadUrl(detailTarget.certificate_id)} target="_blank" rel="noreferrer">↓ PDF</a>
              {detailTarget.status === 'active' && (<button className="btn-danger-sm" onClick={() => setRevokeTarget(detailTarget)}>Revoke</button>)}
              <button className="btn-ghost-sm" onClick={() => setDetailTarget(null)}>Close</button>
            </>
          }
        >
          <div className="form-grid">
            {[['Certificate ID', detailTarget.certificate_id], ['Student', detailTarget.student_name], ['Course', detailTarget.course_name],
              ['Degree', detailTarget.degree_type], ['Issue Date', detailTarget.issue_date], ['Status', detailTarget.status]].map(([k, v]) => (
              <div key={k} className="detail-row"><span className="detail-key">{k}</span><span className="detail-val">{v ?? '—'}</span></div>
            ))}
          </div>
        </Modal>
      )}

      {revokeTarget && (
        <ConfirmModal
          title="Revoke Certificate"
          message={`Revoke certificate ${revokeTarget.certificate_id}? This cannot be undone.`}
          confirmLabel={revoking ? 'Revoking…' : 'Revoke Certificate'}
          danger
          onConfirm={handleRevoke}
          onCancel={() => setRevokeTarget(null)}
        />
      )}
    </div>
  );
}
