import { useEffect } from 'react';
import { useAuthContext } from '../../context/AuthContext';
import { useDataContext } from '../../context/DataContext';
import { useCertificatesContext } from '../../context/CertificatesContext';
import { StatCard, PageSpinner, EmptyState, Badge } from '../../components/ui';

export default function UniversityDashboard() {
  const { user } = useAuthContext();
  const { students, fetchStudents } = useDataContext();
  const { certificates, fetchCertificates, isLoading } = useCertificatesContext();

  useEffect(() => { fetchStudents(); fetchCertificates(); }, []);

  if (isLoading && certificates.length === 0) return <PageSpinner />;

  const now = new Date();
  const thisMonth = certificates.filter(c => {
    const d = new Date(c.issue_date);
    return d.getMonth() === now.getMonth() && d.getFullYear() === now.getFullYear();
  }).length;

  const recent = [...certificates]
    .sort((a, b) => new Date(b.issue_date) - new Date(a.issue_date))
    .slice(0, 5);

  return (
    <div>
      <div className="page-header">
        <div>
          <p className="page-title">Dashboard</p>
          <p className="page-sub">Welcome, {user?.full_name ?? user?.email}</p>
        </div>
      </div>

      <div className="stats-grid">
        <StatCard label="My Students" value={students.length} />
        <StatCard label="Certificates Issued" value={certificates.length} />
        <StatCard label="This Month" value={thisMonth} />
      </div>

      <div className="section-card">
        <div className="section-card-header">
          <span className="section-card-title">Recent Certificates</span>
        </div>
        {recent.length === 0 ? (
          <EmptyState icon="○" title="No certificates yet" sub="Issue a certificate to get started" />
        ) : (
          <div className="table-wrap">
            <table className="table">
              <thead>
                <tr><th>Certificate ID</th><th>Student</th><th>Course</th><th>Issue Date</th><th>Status</th></tr>
              </thead>
              <tbody>
                {recent.map(c => (
                  <tr key={c.certificate_id}>
                    <td className="mono-id">{c.certificate_id}</td>
                    <td>{c.student_name ?? '—'}</td>
                    <td>{c.course_name}</td>
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
