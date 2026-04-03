import { useEffect, useState } from 'react';
import { useAuthContext } from '../../context/AuthContext';
import { useCertificatesContext } from '../../context/CertificatesContext';
import { StatCard, PageSpinner, EmptyState, Badge, Modal } from '../../components/ui';
import { getCertificateDownloadUrl } from '../../api/certificates.api';
import toast from 'react-hot-toast';
import { Download, Share2, Eye, LayoutGrid, List } from 'lucide-react';

export default function StudentDashboard() {
  const { user } = useAuthContext();
  const { certificates, fetchCertificates, isLoading } = useCertificatesContext();
  const [view, setView] = useState('grid'); // 'grid' | 'list'
  const [detailTarget, setDetailTarget] = useState(null);

  useEffect(() => { fetchCertificates(); }, []);

  if (isLoading && certificates.length === 0) return <PageSpinner />;

  const active = certificates.filter(c => c.status === 'active').length;

  function copyShareLink(certId) {
    navigator.clipboard.writeText(`${window.location.origin}/verify?cert=${certId}`);
    toast.success('Link copied');
  }

  return (
    <div>
      {/* Welcome banner */}
      <div className="welcome-banner">
        <div>
          <p className="welcome-name">Welcome, {user?.full_name ?? user?.email}</p>
          {user?.student_id && <p className="welcome-meta">Student ID: {user.student_id} · {user?.university_name}</p>}
        </div>
        <div className="view-toggle">
          <button className={`view-toggle-btn ${view === 'grid' ? 'active' : ''}`} onClick={() => setView('grid')}><LayoutGrid size={13} /> Grid</button>
          <button className={`view-toggle-btn ${view === 'list' ? 'active' : ''}`} onClick={() => setView('list')}><List size={13} /> List</button>
        </div>
      </div>

      {/* Stats */}
      <div className="stats-grid" style={{ marginBottom: 24 }}>
        <StatCard label="Total Certificates" value={certificates.length} />
        <StatCard label="Active" value={active} />
      </div>

      {/* Certificates */}
      {certificates.length === 0 ? (
        <EmptyState icon="○" title="No certificates yet" sub="Your certificates will appear here once issued" />
      ) : view === 'grid' ? (
        <div className="cert-grid">
          {certificates.map(c => (
            <div key={c.certificate_id} className="cert-card" onClick={() => setDetailTarget(c)}>
              <p className="cert-course">{c.course_name}</p>
              <p className="cert-degree">{c.degree_type}</p>
              <p className="cert-meta">Issued {c.issue_date}</p>
              {c.university_name && <p className="cert-meta">{c.university_name}</p>}
              <Badge status={c.status} />
              <div className="cert-actions" onClick={e => e.stopPropagation()}>
                <a className="btn-icon" title="Download" href={getCertificateDownloadUrl(c.certificate_id)} target="_blank" rel="noreferrer"><Download size={13} /></a>
                <button className="btn-icon" title="Share" onClick={() => copyShareLink(c.certificate_id)}><Share2 size={13} /></button>
              </div>
            </div>
          ))}
        </div>
      ) : (
        <div className="table-wrap">
          <table className="table">
            <thead>
              <tr><th>Certificate ID</th><th>Course</th><th>Degree</th><th>Issue Date</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              {certificates.map(c => (
                <tr key={c.certificate_id}>
                  <td className="mono-id">{c.certificate_id}</td>
                  <td>{c.course_name}</td>
                  <td>{c.degree_type}</td>
                  <td>{c.issue_date}</td>
                  <td><Badge status={c.status} /></td>
                  <td>
                    <span style={{ display: 'flex', gap: 4 }}>
                      <button className="btn-icon" onClick={() => setDetailTarget(c)}><Eye size={13} /></button>
                      <a className="btn-icon" href={getCertificateDownloadUrl(c.certificate_id)} target="_blank" rel="noreferrer"><Download size={13} /></a>
                    </span>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {/* Detail modal */}
      {detailTarget && (
        <Modal title="Certificate Details" onClose={() => setDetailTarget(null)}
          footer={
            <>
              <a className="btn-ghost-sm" href={getCertificateDownloadUrl(detailTarget.certificate_id)} target="_blank" rel="noreferrer">↓ Download PDF</a>
              <button className="btn-ghost-sm" onClick={() => copyShareLink(detailTarget.certificate_id)}>Share Link</button>
              <button className="btn-ghost-sm" onClick={() => setDetailTarget(null)}>Close</button>
            </>
          }
        >
          <div className="form-grid">
            {[['Certificate ID', detailTarget.certificate_id], ['Course', detailTarget.course_name],
              ['Degree', detailTarget.degree_type], ['Issue Date', detailTarget.issue_date],
              ['Status', detailTarget.status], ['University', detailTarget.university_name]].map(([k, v]) => (
              <div key={k} className="detail-row"><span className="detail-key">{k}</span><span className="detail-val">{v ?? '—'}</span></div>
            ))}
          </div>
          <div style={{ marginTop: 16 }}>
            <p style={{ fontSize: '0.72rem', color: 'var(--text3)', marginBottom: 6 }}>Verification Link</p>
            <div className="share-link-row">
              <input className="share-link-input" readOnly value={`${window.location.origin}/verify?cert=${detailTarget.certificate_id}`} />
              <button className="btn-ghost" onClick={() => copyShareLink(detailTarget.certificate_id)}>Copy</button>
            </div>
          </div>
        </Modal>
      )}
    </div>
  );
}
