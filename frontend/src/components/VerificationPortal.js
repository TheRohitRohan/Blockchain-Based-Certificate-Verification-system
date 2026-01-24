import React, { useState, useEffect } from 'react';
import { useSearchParams } from 'react-router-dom';
import { toast } from 'react-toastify';
import { certificateAPI } from '../services/api';

const VerificationPortal = () => {
  const [searchParams] = useSearchParams();
  const [certificateId, setCertificateId] = useState('');
  const [verificationResult, setVerificationResult] = useState(null);
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    const certId = searchParams.get('certificate_id');
    if (certId) {
      setCertificateId(certId);
      handleVerify(certId);
    }
  }, [searchParams]);

  const handleVerify = async (id = null) => {
    const certId = id || certificateId;
    if (!certId) {
      toast.error('Please enter a certificate ID');
      return;
    }

    setLoading(true);
    setVerificationResult(null);

    try {
      const response = await certificateAPI.verify(certId);
      setVerificationResult(response.data);
    } catch (error) {
      setVerificationResult({
        valid: false,
        status: 'error',
        error: error.response?.data?.error || 'Verification failed'
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div style={{ minHeight: '100vh', background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)', padding: '40px 20px' }}>
      <div className="container" style={{ maxWidth: '800px' }}>
        <div className="card" style={{ marginTop: '50px' }}>
          <h1 style={{ textAlign: 'center', marginBottom: '30px', color: '#333' }}>
            Certificate Verification Portal
          </h1>
          <p style={{ textAlign: 'center', color: '#666', marginBottom: '30px' }}>
            Verify the authenticity of certificates using blockchain technology
          </p>

          <form
            onSubmit={(e) => {
              e.preventDefault();
              handleVerify();
            }}
          >
            <div className="form-group">
              <label>Certificate ID</label>
              <input
                type="text"
                value={certificateId}
                onChange={(e) => setCertificateId(e.target.value)}
                placeholder="Enter Certificate ID"
                required
              />
            </div>
            <button
              type="submit"
              className="btn btn-primary"
              style={{ width: '100%' }}
              disabled={loading}
            >
              {loading ? 'Verifying...' : 'Verify Certificate'}
            </button>
          </form>

          {verificationResult && (
            <div className={`verification-result ${verificationResult.valid ? 'valid' : 'invalid'}`}>
              {verificationResult.valid ? (
                <div>
                  <h2 style={{ marginBottom: '20px' }}>✓ Certificate Verified</h2>
                  {verificationResult.certificate && (
                    <div style={{ textAlign: 'left', marginTop: '20px' }}>
                      <p><strong>Certificate ID:</strong> {verificationResult.certificate.certificate_id}</p>
                      <p><strong>Student Name:</strong> {verificationResult.certificate.student_name}</p>
                      <p><strong>University:</strong> {verificationResult.certificate.university_name}</p>
                      <p><strong>Course:</strong> {verificationResult.certificate.course_name}</p>
                      <p><strong>Issue Date:</strong> {verificationResult.certificate.issue_date}</p>
                    </div>
                  )}
                </div>
              ) : (
                <div>
                  <h2 style={{ marginBottom: '20px' }}>✗ Certificate Invalid</h2>
                  <p>
                    {verificationResult.status === 'revoked' && 'This certificate has been revoked.'}
                    {verificationResult.status === 'not_found' && 'Certificate not found in the system.'}
                    {verificationResult.status === 'invalid' && 'Certificate verification failed. The certificate may be tampered with.'}
                    {verificationResult.error && verificationResult.error}
                  </p>
                </div>
              )}
            </div>
          )}

          <div style={{ marginTop: '30px', textAlign: 'center' }}>
            <a href="/login" style={{ color: '#007bff', textDecoration: 'none' }}>
              Login to System
            </a>
          </div>
        </div>
      </div>
    </div>
  );
};

export default VerificationPortal;

