import React, { useEffect, useState } from 'react';
import { toast } from 'react-toastify';
import { certificateAPI, universityAPI, studentAPI } from '../services/api';
import Navbar from './Navbar';

const AdminDashboard = () => {
  const [certificates, setCertificates] = useState([]);
  const [universities, setUniversities] = useState([]);
  const [students, setStudents] = useState([]);
  const [activeTab, setActiveTab] = useState('certificates');
  const [newUniversity, setNewUniversity] = useState({
    name: '',
    code: '',
    address: '',
    contact_email: '',
    contact_phone: ''
  });

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [certsRes, univRes, studRes] = await Promise.all([
        certificateAPI.getAll(),
        universityAPI.getAll(),
        studentAPI.getAll()
      ]);

      if (certsRes.data.success) setCertificates(certsRes.data.certificates);
      if (univRes.data.success) setUniversities(univRes.data.universities);
      if (studRes.data.success) setStudents(studRes.data.students);
    } catch (error) {
      toast.error('Failed to load data');
    }
  };

  const handleRevokeCertificate = async (certificateId) => {
    if (!window.confirm('Are you sure you want to revoke this certificate?')) return;

    try {
      await certificateAPI.revoke(certificateId);
      toast.success('Certificate revoked');
      loadData();
    } catch (error) {
      toast.error('Failed to revoke certificate');
    }
  };

  const handleAddUniversity = async (e) => {
    e.preventDefault();
    try {
      await universityAPI.create(newUniversity);
      toast.success('University added');
      setNewUniversity({ name: '', code: '', address: '', contact_email: '', contact_phone: '' });
      loadData();
    } catch (error) {
      toast.error('Failed to add university');
    }
  };

  return (
    <div>
      <Navbar />
      <div className="container">
        <div className="dashboard-header">
          <h1>Admin Dashboard</h1>
        </div>

        <div className="stats-grid">
          <div className="stat-card">
            <h3>Total Certificates</h3>
            <div className="value">{certificates.length}</div>
          </div>
          <div className="stat-card">
            <h3>Universities</h3>
            <div className="value">{universities.length}</div>
          </div>
          <div className="stat-card">
            <h3>Students</h3>
            <div className="value">{students.length}</div>
          </div>
        </div>

        <div className="card">
          <div style={{ display: 'flex', gap: '10px', marginBottom: '20px' }}>
            <button
              className={`btn ${activeTab === 'certificates' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setActiveTab('certificates')}
            >
              Certificates
            </button>
            <button
              className={`btn ${activeTab === 'universities' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setActiveTab('universities')}
            >
              Universities
            </button>
            <button
              className={`btn ${activeTab === 'students' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setActiveTab('students')}
            >
              Students
            </button>
          </div>

          {activeTab === 'certificates' && (
            <div>
              <h2>All Certificates</h2>
              <table className="table">
                <thead>
                  <tr>
                    <th>Certificate ID</th>
                    <th>Student</th>
                    <th>University</th>
                    <th>Course</th>
                    <th>Issue Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {certificates.map((cert) => (
                    <tr key={cert.id}>
                      <td>{cert.certificate_id}</td>
                      <td>{cert.student_name}</td>
                      <td>{cert.university_name}</td>
                      <td>{cert.course_name}</td>
                      <td>{cert.issue_date}</td>
                      <td>{cert.is_revoked ? 'Revoked' : 'Valid'}</td>
                      <td>
                        {!cert.is_revoked && (
                          <button
                            className="btn btn-danger"
                            onClick={() => handleRevokeCertificate(cert.certificate_id)}
                          >
                            Revoke
                          </button>
                        )}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {activeTab === 'universities' && (
            <div>
              <h2>Universities</h2>
              <form onSubmit={handleAddUniversity} style={{ marginBottom: '20px' }}>
                <h3>Add New University</h3>
                <div className="form-group">
                  <label>Name</label>
                  <input
                    type="text"
                    value={newUniversity.name}
                    onChange={(e) => setNewUniversity({ ...newUniversity, name: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Code</label>
                  <input
                    type="text"
                    value={newUniversity.code}
                    onChange={(e) => setNewUniversity({ ...newUniversity, code: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Address</label>
                  <textarea
                    value={newUniversity.address}
                    onChange={(e) => setNewUniversity({ ...newUniversity, address: e.target.value })}
                  />
                </div>
                <div className="form-group">
                  <label>Contact Email</label>
                  <input
                    type="email"
                    value={newUniversity.contact_email}
                    onChange={(e) => setNewUniversity({ ...newUniversity, contact_email: e.target.value })}
                  />
                </div>
                <div className="form-group">
                  <label>Contact Phone</label>
                  <input
                    type="text"
                    value={newUniversity.contact_phone}
                    onChange={(e) => setNewUniversity({ ...newUniversity, contact_phone: e.target.value })}
                  />
                </div>
                <button type="submit" className="btn btn-primary">Add University</button>
              </form>

              <table className="table">
                <thead>
                  <tr>
                    <th>Name</th>
                    <th>Code</th>
                    <th>Email</th>
                    <th>Phone</th>
                  </tr>
                </thead>
                <tbody>
                  {universities.map((univ) => (
                    <tr key={univ.id}>
                      <td>{univ.name}</td>
                      <td>{univ.code}</td>
                      <td>{univ.contact_email}</td>
                      <td>{univ.contact_phone}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {activeTab === 'students' && (
            <div>
              <h2>All Students</h2>
              <table className="table">
                <thead>
                  <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>University</th>
                  </tr>
                </thead>
                <tbody>
                  {students.map((student) => (
                    <tr key={student.id}>
                      <td>{student.student_id}</td>
                      <td>{student.full_name}</td>
                      <td>{student.email}</td>
                      <td>{student.university_name}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default AdminDashboard;

