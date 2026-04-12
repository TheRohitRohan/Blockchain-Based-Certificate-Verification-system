import axiosInstance from './axiosInstance';

// ─────────────────────────────────────────────────────────────
//  Auth API
//
//  login(email, password)  → POST /auth/login
//  register(data)          → POST /auth/register
// ─────────────────────────────────────────────────────────────

/**
 * Authenticate a user (works for all roles: admin, university, student).
 * For university role users, response also includes university and admin detail objects.
 * @param {string} email
 * @param {string} password
 * @returns {{ success: boolean, token: string, user: object, university?: object, admin?: object }}
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

/**
 * Request a password-reset email for the given address.
 * Always returns success (prevents user enumeration).
 * @param {string} email
 * @returns {{ success: boolean, message: string }}
 */
export async function forgotPassword(email) {
  const res = await axiosInstance.post('/auth/forgot-password', { email });
  return res.data;
}

/**
 * Reset a user's password using an email token.
 * @param {string} token  — reset token from the email link
 * @param {string} newPassword
 * @returns {{ success: boolean, message: string }}
 */
export async function resetPassword(token, newPassword) {
  const res = await axiosInstance.post('/auth/reset-password', {
    token,
    new_password: newPassword,
  });
  return res.data;
}

// ─────────────────────────────────────────────────────────────
//  University management
//
//  universityRegister(data)  → POST /auth/university/register
//  verifyToken(token)        → POST /auth/verify-token
// ─────────────────────────────────────────────────────────────

/**
 * Register a new university with an admin account.
 * @param {{
 *   university_name: string,
 *   university_email: string,
 *   university_phone: string,
 *   university_address: string,
 *   admin_name: string,
 *   admin_email: string,
 *   admin_password: string
 * }} data
 * @returns {{ success: boolean, university_id: number, message: string }}
 */
export async function universityRegister(data) {
  const res = await axiosInstance.post('/auth/university/register', data);
  return res.data;
}

/**
 * Verify a JWT token and return its payload.
 * @param {string} token
 * @returns {{ valid: boolean, user_id: number, email: string, role: string, ... }}
 */
export async function verifyToken(token) {
  const res = await axiosInstance.post('/auth/verify-token', { token });
  return res.data;
}
