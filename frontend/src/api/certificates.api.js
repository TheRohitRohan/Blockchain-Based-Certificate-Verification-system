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
  const res = await axiosInstance.post('/certificates/create', data);
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
  return res.data;
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
