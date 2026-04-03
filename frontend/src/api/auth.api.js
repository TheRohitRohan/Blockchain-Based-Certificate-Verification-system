import axiosInstance from './axiosInstance';

// ─────────────────────────────────────────────────────────────
//  Auth API
//
//  login(email, password)  → POST /auth/login
//  register(data)          → POST /auth/register
// ─────────────────────────────────────────────────────────────

/**
 * Authenticate a user.
 * @param {string} email
 * @param {string} password
 * @returns {{ success: boolean, token: string, user: object }}
 */
export async function login(email, password) {
  const res = await axiosInstance.post('/auth/login', { email, password });
  return res.data;
}

/**
 * Register a new user account.
 * @param {{ username, email, password, role, full_name, university_id? }} data
 * @returns {{ success: boolean, message: string }}
 */
export async function register(data) {
  const res = await axiosInstance.post('/auth/register', data);
  return res.data;
}
