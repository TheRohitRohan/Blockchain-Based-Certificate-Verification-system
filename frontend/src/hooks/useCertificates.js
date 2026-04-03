import { useCertificatesContext } from '../context/CertificatesContext';

/**
 * useCertificates — access certificate state and actions.
 *
 * @returns {{
 *   certificates: object[],
 *   verifyResult: object|null,
 *   isLoading: boolean,
 *   error: string|null,
 *   fetchCertificates: () => Promise<void>,
 *   issueCertificate: (data: object) => Promise<{success, certificate_id?, error?}>,
 *   verify: (id: string, hash?: string) => Promise<{valid, status, certificate?}>,
 *   revoke: (id: string) => Promise<{success, error?}>,
 *   clearVerifyResult: () => void,
 *   getDownloadUrl: (id: string) => string,
 * }}
 *
 * Usage:
 *   const { certificates, fetchCertificates, verify, verifyResult } = useCertificates();
 */
export function useCertificates() {
  return useCertificatesContext();
}
