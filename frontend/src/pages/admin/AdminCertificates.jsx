import { useEffect, useState } from 'react';
import { useCertificatesContext } from '../../context/CertificatesContext';
import { useDataContext } from '../../context/DataContext';
import {
  PageSpinner, EmptyState, Modal, ConfirmModal, Badge, FormField, Spinner
} from '../../components/ui';
import { getCertificateDownloadUrl } from '../../api/certificates.api';
import toast from 'react-hot-toast';
import { Eye, ShieldX, Download } from 'lucide-react';

export default function AdminCertificates() {
  const { certificates, fetchCertificates, revoke, isLoading } = useCertificatesContext();
  const { universities, fetchUniversities } = useDataContext();

  const [search, setSearch] = useState('');
  const [filterUni, setFilterUni] = useState('');
  const [filterStatus, setFilterStatus] = useState('');
  const [detailTarget, setDetailTarget] = useState(null);
  const [revokeTarget, setRevokeTarget] = useState(null);
  const [revoking, setRevoking] = useState(false);

  useEffect(() => { fetchCertificates(); fetchUniversities(); }, []);

  const filtered = certificates.filter(c => {
    const matchSearch = !search ||
      c.certificate_id?.toLowerCase().includes(search.toLowerCase()) ||
      c.student_name?.toLowerCase().includes(search.toLowerCase());
    const matchUni = !filterUni || c.university_name === filterUni;
    const matchStatus = !filterStatus || c.status === filterStatus;
    return matchSearch && matchUni && matchStatus;
  });

  async function handleRevoke() {
    setRevoking(true);
    const res = await revoke(revokeTarget.certificate_id);
    setRevoking(false);
    if (res.success) { toast.success('Certificate revoked'); setRevokeTarget(null); setDetailTarget(null); }
    else toast.error(res.error ?? 'Revoke failed');
  }

  if (isLoading && certificates.length === 0) return <PageSpinner />;

  return (
    <div>
      <div className="page-header">
        <div>
          <p className="page-title">Certificates</p>
          <p className="page-sub">{certificates.length} total certificates</p>
        </div>
      </div>

      <div className="filter-bar">
        <input
          className="form-input"
          placeholder="Search by ID or student…"
          value={search}
          onChange={e => setSearch(e.target.value)}
        />
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
                <tr key={c.certificate_id ?? c.id}>
                  <td className="mono-id">{c.certificate_id}</td>
                  <td>{c.student_name ?? '—'}</td>
                  <td>{c.university_name ?? '—'}</td>
                  <td>{c.course_name}</td>
                  <td>{c.issue_date}</td>
                  <td><Badge status={c.status} /></td>
                  <td>
                    <span style={{ display: 'flex', gap: 4 }}>
                      <button className="btn-icon" title="View" onClick={() => setDetailTarget(c)}><Eye size={13} /></button>
                      {c.status === 'active' && (
                        <button className="btn-icon" title="Revoke" style={{ color: 'var(--red)' }} onClick={() => setRevokeTarget(c)}><ShieldX size={13} /></button>
                      )}
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
              <a className="btn-ghost-sm" href={getCertificateDownloadUrl(detailTarget.certificate_id)} target="_blank" rel="noreferrer"><Download size={12} /> PDF</a>
              {detailTarget.status === 'active' && (
                <button className="btn-danger-sm" onClick={() => { setRevokeTarget(detailTarget); }}>Revoke</button>
              )}
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
          message={`Revoke certificate ${revokeTarget.certificate_id}? This action cannot be undone.`}
          confirmLabel={revoking ? 'Revoking…' : 'Revoke Certificate'}
          danger
          onConfirm={handleRevoke}
          onCancel={() => setRevokeTarget(null)}
        />
      )}
    </div>
  );
}
