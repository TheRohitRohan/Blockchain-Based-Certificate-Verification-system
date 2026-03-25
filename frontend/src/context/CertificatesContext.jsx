import React, {
  createContext,
  useContext,
  useReducer,
  useCallback,
} from 'react';
import {
  listCertificates,
  createCertificate,
  verifyCertificate,
  revokeCertificate,
  getCertificateDownloadUrl,
} from '../api/certificates.api';

// ─────────────────────────────────────────────────────────────
//  CertificatesContext
//
//  State:
//    certificates   — array of certificate objects (role-scoped by backend)
//    verifyResult   — result of the last verify call or null
//    isLoading      — true while any API call is in flight
//    error          — last error message or null
//
//  Actions:
//    fetchCertificates()
//    issueCertificate(data)   → creates, then re-fetches list
//    verify(id, hash?)        → sets verifyResult
//    revoke(id)               → revokes, then re-fetches list
//    clearVerifyResult()
//    getDownloadUrl(id)       → returns URL string (no network call)
// ─────────────────────────────────────────────────────────────

const initialState = {
  certificates: [],
  verifyResult: null,
  isLoading: false,
  error: null,
};

function certReducer(state, action) {
  switch (action.type) {
    case 'SET_LOADING':
      return { ...state, isLoading: action.payload, error: null };
    case 'SET_ERROR':
      return { ...state, isLoading: false, error: action.payload };
    case 'SET_CERTIFICATES':
      return { ...state, certificates: action.payload, isLoading: false, error: null };
    case 'SET_VERIFY_RESULT':
      return { ...state, verifyResult: action.payload, isLoading: false, error: null };
    case 'CLEAR_VERIFY':
      return { ...state, verifyResult: null };
    default:
      return state;
  }
}

export const CertificatesContext = createContext(null);

export function CertificatesProvider({ children }) {
  const [state, dispatch] = useReducer(certReducer, initialState);

  // ── Fetch list ──────────────────────────────────────────
  const fetchCertificates = useCallback(async () => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const data = await listCertificates();
      dispatch({ type: 'SET_CERTIFICATES', payload: data.certificates ?? [] });
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Failed to load certificates';
      dispatch({ type: 'SET_ERROR', payload: msg });
    }
  }, []);

  // ── Issue ───────────────────────────────────────────────
  const issueCertificate = useCallback(async (formData) => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const result = await createCertificate(formData);
      if (result.success) {
        // Re-fetch the list so UI stays in sync
        await fetchCertificates();
      } else {
        dispatch({ type: 'SET_ERROR', payload: result.error ?? 'Failed to issue certificate' });
      }
      return result;
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Failed to issue certificate';
      dispatch({ type: 'SET_ERROR', payload: msg });
      return { success: false, error: msg };
    }
  }, [fetchCertificates]);

  // ── Verify (public) ─────────────────────────────────────
  const verify = useCallback(async (certificateId, certificateHash = null) => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const result = await verifyCertificate(certificateId, certificateHash);
      dispatch({ type: 'SET_VERIFY_RESULT', payload: result });
      return result;
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Verification failed';
      dispatch({ type: 'SET_ERROR', payload: msg });
      return { valid: false, status: 'error', error: msg };
    }
  }, []);

  // ── Revoke ──────────────────────────────────────────────
  const revoke = useCallback(async (certificateId) => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const result = await revokeCertificate(certificateId);
      if (result.success) {
        await fetchCertificates();
      } else {
        dispatch({ type: 'SET_ERROR', payload: result.error ?? 'Failed to revoke certificate' });
      }
      return result;
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Failed to revoke certificate';
      dispatch({ type: 'SET_ERROR', payload: msg });
      return { success: false, error: msg };
    }
  }, [fetchCertificates]);

  // ── Clear verify result ─────────────────────────────────
  const clearVerifyResult = useCallback(() => {
    dispatch({ type: 'CLEAR_VERIFY' });
  }, []);

  // ── Download URL helper ─────────────────────────────────
  const getDownloadUrl = useCallback((certificateId) => {
    return getCertificateDownloadUrl(certificateId);
  }, []);

  const value = {
    // State
    certificates: state.certificates,
    verifyResult: state.verifyResult,
    isLoading: state.isLoading,
    error: state.error,
    // Actions
    fetchCertificates,
    issueCertificate,
    verify,
    revoke,
    clearVerifyResult,
    getDownloadUrl,
  };

  return (
    <CertificatesContext.Provider value={value}>
      {children}
    </CertificatesContext.Provider>
  );
}

export function useCertificatesContext() {
  const ctx = useContext(CertificatesContext);
  if (!ctx) throw new Error('useCertificatesContext must be used inside <CertificatesProvider>');
  return ctx;
}
