import React, { useEffect, useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { certificateAPI } from '../services/api';
import Navbar from './Navbar';

const Dashboard = () => {
  const [certificates, setCertificates] = useState([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();
  const user = JSON.parse(localStorage.getItem('user') || '{}');

  useEffect(() => {
    loadCertificates();
  }, []);

  const loadCertificates = async () => {
    try {
      const response = await certificateAPI.getAll();
      if (response.data.success) {
        setCertificates(response.data.certificates);
      }
    } catch (error) {
      console.error('Failed to load certificates:', error);
    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <Navbar />
      <div className="container">
        <div className="dashboard-header">
          <h1>Dashboard</h1>
          <p>Welcome, {user.full_name || user.email}</p>
        </div>

        <div className="stats-grid">
          <div className="stat-card">
            <h3>Total Certificates</h3>
            <div className="value">{certificates.length}</div>
          </div>
        </div>

        <div className="card">
          <h2>Recent Certificates</h2>
          {loading ? (
            <p>Loading...</p>
          ) : (
            <table className="table">
              <thead>
                <tr>
                  <th>Certificate ID</th>
                  <th>Student</th>
                  <th>Course</th>
                  <th>Issue Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                {certificates.map((cert) => (
                  <tr key={cert.id}>
                    <td>{cert.certificate_id}</td>
                    <td>{cert.student_name}</td>
                    <td>{cert.course_name}</td>
                    <td>{cert.issue_date}</td>
                    <td>{cert.is_revoked ? 'Revoked' : 'Valid'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </div>
      </div>
    </div>
  );
};

export default Dashboard;

