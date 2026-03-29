import { useState, useRef } from 'react';
import { Link, useSearchParams } from 'react-router';
import { publicVerify, getPublicDownloadUrl } from '../api/certificates.api';
import { Spinner, FormField } from '../components/ui';
import toast from 'react-hot-toast';
import { ShieldCheck, ShieldX, AlertTriangle, Download, Share2, Search } from 'lucide-react';

const STATUS_META = {
  valid:     { Icon: ShieldCheck, label: 'VERIFIED',   cls: 'valid' },
  revoked:   { Icon: ShieldX,    label: 'REVOKED',    cls: 'revoked' },
  not_found: { Icon: AlertTriangle, label: 'NOT FOUND', cls: 'not_found' },
};

export default function VerifyPage() {
  const [searchParams] = useSearchParams();
  const [certId, setCertId] = useState(searchParams.get('cert') ?? '');
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState(null);
  const fileRef = useRef(null);

  async function handleVerify(e) {
    e?.preventDefault();
    if (!certId.trim()) { toast.error('Enter a certificate ID'); return; }
    setLoading(true);
    setResult(null);
    try {
      const data = await publicVerify(certId.trim());
      setResult(data);
    } catch {
      // fallback: API may return 404 as error
      setResult({ valid: false, status: 'not_found' });
    }
    setLoading(false);
  }

  // auto-verify if cert param in URL
  useState(() => {
    if (certId) handleVerify();
  }, []);

  const cert = result?.certificate;
  const meta = STATUS_META[result?.status] ?? STATUS_META.not_found;

  function copyShareLink() {
    navigator.clipboard.writeText(`${window.location.origin}/verify?cert=${certId}`);
    toast.success('Link copied');
  }

  return (
    <div className="verify-shell">
      {/* Top bar */}
      <div className="verify-topbar">
        <div className="verify-topbar-brand">
          <ShieldCheck size={18} strokeWidth={1.5} style={{ color: 'var(--text)' }} />
          <span className="logo-text">CertiLedger</span>
        </div>
        <Link to="/login" className="verify-topbar-link">Sign in →</Link>
      </div>

      <div className="verify-content">
        <div className="verify-card">
          <p className="verify-title">Verify Certificate</p>
          <p className="verify-sub">Enter a certificate ID to verify its authenticity on the blockchain.</p>

          <form onSubmit={handleVerify} style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
            <FormField label="Certificate ID">
              <input
                className="form-input"
                placeholder="CERT-XXXXXX"
                value={certId}
                onChange={e => setCertId(e.target.value)}
              />
            </FormField>
            <button type="submit" className="btn-primary" disabled={loading} style={{ alignSelf: 'stretch', justifyContent: 'center' }}>
            {loading ? <><Spinner size={13} /> Verifying…</> : <><Search size={14} /> Verify Certificate</>}
          </button>
          </form>

          {/* Result */}
          {result && (
            <div className="result-box">
              <div className={`result-banner ${meta.cls}`}>
            {meta.Icon && <meta.Icon size={16} strokeWidth={2} />}
            <span>{meta.label}</span>
          </div>

              {cert && (
                <>
                  <div className="result-details">
                    {[
                      ['Certificate ID', cert.certificate_id],
                      ['Student', cert.student_name],
                      ['University', cert.university_name],
                      ['Course', cert.course_name],
                      ['Degree', cert.degree_type],
                      ['Issue Date', cert.issue_date],
                      ['Status', cert.status],
                      cert.blockchain_tx_hash && ['Blockchain TX', cert.blockchain_tx_hash],
                    ].filter(Boolean).map(([k, v]) => (
                      <div key={k} className="result-row">
                        <span className="result-key">{k}</span>
                        <span className={`result-val ${k === 'Blockchain TX' ? 'mono' : ''}`}>{v ?? '—'}</span>
                      </div>
                    ))}
                  </div>

                  <div className="result-actions">
                    <a
                      href={getPublicDownloadUrl(cert.certificate_id)}
                      className="btn-ghost"
                      target="_blank"
                      rel="noreferrer"
                    >
                      <Download size={14} /> Download PDF
                    </a>
                    <button className="btn-ghost" onClick={copyShareLink}>
                      <Share2 size={14} /> Share Link
                    </button>
                  </div>
                </>
              )}

              {result.status === 'revoked' && cert && (
                <div className="result-details">
                  <div className="result-row">
                    <span className="result-key">Certificate ID</span>
                    <span className="result-val">{cert.certificate_id}</span>
                  </div>
                  <div className="result-row">
                    <span className="result-key">Student</span>
                    <span className="result-val">{cert.student_name ?? '—'}</span>
                  </div>
                </div>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
