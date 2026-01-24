import React, { useEffect, useState } from 'react';
import { toast } from 'react-toastify';
import { certificateAPI } from '../services/api';
import { QRCodeSVG } from 'qrcode.react';
import Navbar from './Navbar';

const StudentDashboard = () => {
  const [certificates, setCertificates] = useState([]);
  const [loading, setLoading] = useState(true);
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
      toast.error('Failed to load certificates');
    } finally {
      setLoading(false);
    }
  };

  const getVerificationUrl = (certificateId) => {
    return `${window.location.origin}/verify?certificate_id=${certificateId}`;
  };

  const handleDownload = (certificate) => {
    // In a real implementation, you would download the PDF
    toast.info('Download feature coming soon');
  };

  return (
    <div>
      <Navbar />
      <div className="container">
        <div className="dashboard-header">
          <h1>My Certificates</h1>
          <p>Welcome, {user.full_name || user.email}</p>
        </div>

        {loading ? (
          <p>Loading...</p>
        ) : certificates.length === 0 ? (
          <div className="card">
            <p>No certificates found.</p>
          </div>
        ) : (
          certificates.map((cert) => (
            <div key={cert.id} className="certificate-card">
              <h3>{cert.course_name}</h3>
              <p><strong>Certificate ID:</strong> {cert.certificate_id}</p>
              <p><strong>University:</strong> {cert.university_name}</p>
              <p><strong>Issue Date:</strong> {cert.issue_date}</p>
              <p><strong>Status:</strong> {cert.is_revoked ? 'Revoked' : 'Valid'}</p>
              
              {!cert.is_revoked && (
                <div style={{ marginTop: '20px' }}>
                  <div className="qr-code-container">
                    <QRCodeSVG value={getVerificationUrl(cert.certificate_id)} size={200} />
                  </div>
                  <div style={{ textAlign: 'center', marginTop: '10px' }}>
                    <p><strong>Verification Link:</strong></p>
                    <a
                      href={getVerificationUrl(cert.certificate_id)}
                      target="_blank"
                      rel="noopener noreferrer"
                      style={{ wordBreak: 'break-all' }}
                    >
                      {getVerificationUrl(cert.certificate_id)}
                    </a>
                  </div>
                  <div style={{ marginTop: '20px', display: 'flex', gap: '10px' }}>
                    <button
                      className="btn btn-primary"
                      onClick={() => navigator.clipboard.writeText(getVerificationUrl(cert.certificate_id))}
                    >
                      Copy Verification Link
                    </button>
                    <button
                      className="btn btn-success"
                      onClick={() => handleDownload(cert)}
                    >
                      Download Certificate
                    </button>
                  </div>
                </div>
              )}
            </div>
          ))
        )}
      </div>
    </div>
  );
};

export default StudentDashboard;

