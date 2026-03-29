import { useEffect } from 'react';
import { useAuthContext } from '../../context/AuthContext';
import { useDataContext } from '../../context/DataContext';
import { useCertificatesContext } from '../../context/CertificatesContext';
import { StatCard, PageSpinner, EmptyState } from '../../components/ui';
import { Badge } from '../../components/ui';
import { LayoutDashboard, University, ScrollText, CheckCircle, XCircle } from 'lucide-react';

export default function AdminDashboard() {
  const { user } = useAuthContext();
  const { universities, fetchUniversities } = useDataContext();
  const { certificates, fetchCertificates, isLoading } = useCertificatesContext();

  useEffect(() => {
    fetchUniversities();
    fetchCertificates();
  }, []);

  if (isLoading && certificates.length === 0) return <PageSpinner />;

  const active = certificates.filter(c => c.status === 'active').length;
  const revoked = certificates.filter(c => c.status === 'revoked').length;
  const recent = [...certificates]
    .sort((a, b) => new Date(b.created_at ?? b.issue_date) - new Date(a.created_at ?? a.issue_date))
    .slice(0, 10);

  return (
    <div>
      <div className="page-header">
        <div>
          <p className="page-title">Dashboard</p>
          <p className="page-sub">Welcome back, {user?.full_name ?? user?.email}</p>
        </div>
      </div>

      <div className="stats-grid">
        <StatCard label="Universities" value={universities.length} />
        <StatCard label="Total Certificates" value={certificates.length} />
        <StatCard label="Active" value={active} />
        <StatCard label="Revoked" value={revoked} />
      </div>

      <div className="section-card">
        <div className="section-card-header">
          <span className="section-card-title">Recent Certificates</span>
        </div>
        {recent.length === 0 ? (
          <EmptyState icon="○" title="No certificates yet" />
        ) : (
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr>
                  <th>Certificate ID</th>
                  <th>Student</th>
                  <th>University</th>
                  <th>Issue Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {recent.map(c => (
                  <tr key={c.certificate_id ?? c.id}>
                    <td className="mono-id">{c.certificate_id}</td>
                    <td>{c.student_name ?? '—'}</td>
                    <td>{c.university_name ?? '—'}</td>
                    <td>{c.issue_date}</td>
                    <td><Badge status={c.status} /></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </div>
    </div>
  );
}
