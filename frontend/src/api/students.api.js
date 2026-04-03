import axiosInstance from './axiosInstance';

// ─────────────────────────────────────────────────────────────
//  Students API
//
//  listStudents()    → GET  /students   (auth: university | admin)
//  createStudent(d)  → POST /students   (auth: university | admin)
// ─────────────────────────────────────────────────────────────

/**
 * Fetch students visible to the authenticated user.
 * - university → own university's students
 * - admin      → all students (with university_name included)
 *
 * @returns {{ success: boolean, students: object[] }}
 */
export async function listStudents() {
  const res = await axiosInstance.get('/students');
  return res.data;
}

/**
 * Register a new student (creates user account + student record).
 *
 * @param {{
 *   username: string,
 *   email: string,
 *   password: string,
 *   full_name: string,
 *   student_id: string,
 *   university_id?: number,   // required if caller is admin
 *   enrollment_date?: string  // YYYY-MM-DD, defaults to today
 * }} data
 * @returns {{ success: boolean }}
 */
export async function createStudent(data) {
  const res = await axiosInstance.post('/students', data);
  return res.data;
}

/**
 * Update a student record.
 * @param {number} id  — student DB record id (not student_id string)
 * @param {{ full_name?, enrollment_date? }} data
 * @returns {{ success: boolean }}
 */
export async function updateStudent(id, data) {
  const res = await axiosInstance.put(`/students/${id}`, data);
  return res.data;
}

/**
 * Delete a student (admin or owning university only).
 * @param {number} id
 * @returns {{ success: boolean }}
 */
export async function deleteStudent(id) {
  const res = await axiosInstance.delete(`/students/${id}`);
  return res.data;
}
