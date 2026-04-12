import axiosInstance from './axiosInstance';

// ─────────────────────────────────────────────────────────────
//  Certificates API
//
//  listCertificates()              → GET  /certificates
//  createCertificate(data)         → POST /certificates/create
//  verifyCertificate(id, hash?)    → POST /certificates/verify
//  revokeCertificate(id)           → POST /certificates/revoke
//  getCertificateDownloadUrl(id)   → returns a URL string (not an axios call)
// ─────────────────────────────────────────────────────────────

/**
 * Fetch certificates visible to the authenticated user.
 * The backend scopes results automatically by role:
 *   - student     → own certificates only
 *   - university  → all certificates from their institution
 *   - admin       → all certificates in the system
 *
 * @returns {{ success: boolean, certificates: object[] }}
 */
export async function listCertificates() {
  const res = await axiosInstance.get('/certificates');
  return res.data;
}

/**
 * Issue a new certificate on behalf of a university/admin.
 *
 * @param {{
 *   student_id: number,
 *   university_id: number,
 *   course_name: string,
 *   degree_type?: string,
 *   issue_date: string   // YYYY-MM-DD
 * }} data
 * @returns {{ success: boolean, certificate_id: string, certificate_hash: string, tx_hash: string }}
 */
export async function createCertificate(data) {
  // Blockchain + PDF pipeline can exceed the default axios timeout (15s).
  const res = await axiosInstance.post('/certificates/create', data, { timeout: 600000 });
  return res.data;
}

/**
 * Publicly verify a certificate by ID (and optionally its hash).
 * No authentication required — works for any visitor.
 *
 * @param {string} certificateId
 * @param {string|null} certificateHash  — optional SHA-256 hash for deeper verification
 * @returns {{ valid: boolean, status: 'valid'|'invalid'|'revoked'|'not_found', certificate?: object }}
 */
export async function verifyCertificate(certificateId, certificateHash = null) {
  const payload = { certificate_id: certificateId };
  if (certificateHash) payload.certificate_hash = certificateHash;
  const res = await axiosInstance.post('/certificates/verify', payload);
  return res.data;
}

/**
 * Revoke a certificate (admin only).
 *
 * @param {string} certificateId
 * @returns {{ success: boolean }}
 */
export async function revokeCertificate(certificateId) {
  const res = await axiosInstance.post('/certificates/revoke', { certificate_id: certificateId });
  return res.data;
}

/**
 * Build the download URL for a certificate PDF.
 * Returns a plain URL string — use as an <a href> or window.open().
 * The backend sends the PDF as an attachment with Content-Type: application/pdf.
 *
 * @param {string} certificateId
 * @returns {string} full URL with the token embedded as a query param
 */
export function getCertificateDownloadUrl(certificateId) {
  const base = import.meta.env.VITE_API_URL ?? '/api';
  const token = localStorage.getItem('certiledger_token') ?? '';
  return `${base}/certificates/download?certificate_id=${encodeURIComponent(certificateId)}&token=${token}`;
}

/**
 * Map GET /public/verify JSON (certificate_info + conclusion) to the flat shape VerifyPage expects.
 */
function normalizePublicVerifyResponse(data) {
  if (!data || typeof data !== 'object') {
    return { valid: false, status: 'not_found' };
  }

  // Already in flat form
  if (data.certificate && typeof data.status === 'string') {
    return data;
  }

  if (data.success && data.certificate_info?.identity) {
    const { identity, status: st, blockchain } = data.certificate_info;
    const conclusion = data.conclusion || {};
    const vr = data.verification_result || {};
    const overall = conclusion.overall_status ?? vr.status ?? 'not_found';
    const valid = !!(conclusion.is_valid ?? vr.valid ?? false);

    const allowed = new Set(['valid', 'revoked', 'not_found', 'invalid']);
    let status = allowed.has(overall) ? overall : 'not_found';

    return {
      valid: valid && status === 'valid',
      status,
      certificate: {
        certificate_id: identity.certificate_id,
        student_name: identity.student_name,
        university_name: identity.university_name,
        course_name: identity.course_name,
        degree_type: identity.degree_type,
        issue_date: identity.issue_date,
        status: st?.current_status ?? null,
        blockchain_tx_hash: blockchain?.tx_hash ?? null,
      },
    };
  }

  return {
    valid: false,
    status: 'not_found',
    error: data.error || data.message,
  };
}

/**
 * Publicly verify a certificate by ID — no authentication required.
 * Uses the dedicated public endpoint GET /public/verify.
 *
 * @param {string} certificateId
 * @returns {{ valid: boolean, status: string, certificate?: object }}
 */
export async function publicVerify(certificateId) {
  const res = await axiosInstance.get(
    `/public/verify?certificate_id=${encodeURIComponent(certificateId)}`
  );
  return normalizePublicVerifyResponse(res.data);
}

/**
 * Build a public (no-auth) download URL for a certificate PDF.
 * Uses the public endpoint that doesn't require a JWT token.
 *
 * @param {string} certificateId
 * @returns {string}
 */
export function getPublicDownloadUrl(certificateId) {
  const base = import.meta.env.VITE_API_URL ?? '/api';
  return `${base}/public/certificate/download?certificate_id=${encodeURIComponent(certificateId)}`;
}
