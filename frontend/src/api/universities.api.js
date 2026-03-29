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

/**
 * Update a university (admin only).
 * @param {number} id
 * @param {{ name?, code?, address?, contact_email?, contact_phone? }} data
 * @returns {{ success: boolean }}
 */
export async function updateUniversity(id, data) {
  const res = await axiosInstance.put(`/universities/${id}`, data);
  return res.data;
}

/**
 * Delete a university (admin only).
 * @param {number} id
 * @returns {{ success: boolean }}
 */
export async function deleteUniversity(id) {
  const res = await axiosInstance.delete(`/universities/${id}`);
  return res.data;
}
