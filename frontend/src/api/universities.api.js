import axiosInstance from './axiosInstance';

// ─────────────────────────────────────────────────────────────
//  Universities API
//
//  listUniversities()    → GET  /universities  (public, no auth)
//  createUniversity(d)   → POST /universities  (auth: admin)
// ─────────────────────────────────────────────────────────────

/**
 * Fetch all active universities.
 * This endpoint is public — no auth header required.
 *
 * @returns {{ success: boolean, universities: object[] }}
 */
export async function listUniversities() {
  const res = await axiosInstance.get('/universities');
  return res.data;
}

/**
 * Create a new university (admin only).
 *
 * @param {{
 *   name: string,
 *   code: string,
 *   address?: string,
 *   contact_email?: string,
 *   contact_phone?: string
 * }} data
 * @returns {{ success: boolean }}
 */
export async function createUniversity(data) {
  const res = await axiosInstance.post('/universities', data);
  return res.data;
}
