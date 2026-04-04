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

// ─────────────────────────────────────────────────────────────
//  University Auth API  (uses university_admins table)
//
//  universityLogin(email, password)  → POST /auth/university/login
//  universityRegister(data)          → POST /auth/university/register
//  verifyToken(token)                → POST /auth/verify-token
// ─────────────────────────────────────────────────────────────

/**
 * Authenticate a university admin.
 * @param {string} email
 * @param {string} password
 * @returns {{ success: boolean, token: string, user: object, university: object, admin: object }}
 */
export async function universityLogin(email, password) {
  const res = await axiosInstance.post('/auth/university/login', { email, password });
  return res.data;
}

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
