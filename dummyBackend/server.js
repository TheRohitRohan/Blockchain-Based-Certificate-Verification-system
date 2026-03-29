/**
 * CertiLedger Dummy Backend
 * ─────────────────────────
 * Mimics the real PHP API for frontend development.
 * All data is static in-memory. JWT tokens are real (signed) so the
 * AuthContext's JWT decoder works correctly.
 *
 * Runs on port 8000 — matches the Vite proxy target.
 * Start: node server.js  (or: node --watch server.js)
 */

const express = require('express');
const cors    = require('cors');
const jwt     = require('jsonwebtoken');

const app  = express();
const PORT = 8001;
const JWT_SECRET = 'certiledger-dummy-secret';

// ── Middleware ──────────────────────────────────────────────────
app.use(cors({ origin: '*' }));
app.use(express.json());
app.use((req, _res, next) => {
  console.log(`  ${req.method.padEnd(6)} ${req.path}`);
  next();
});

// ── Helpers ─────────────────────────────────────────────────────
function makeToken(user) {
  return jwt.sign(
    { user_id: user.id, email: user.email, role: user.role, exp: Math.floor(Date.now() / 1000) + 86400 * 7 },
    JWT_SECRET
  );
}

function ok(res, data)  { res.json({ success: true, ...data }); }
function err(res, msg, status = 400) { res.status(status).json({ success: false, error: msg }); }

// ═══════════════════════════════════════════════════════════════
//  STATIC DATA
// ═══════════════════════════════════════════════════════════════

// Credentials map  email → { password, user }
const USERS = {
  'admin@certificate-system.com': {
    password: 'admin123',
    user: { id: 1, username: 'admin', email: 'admin@certificate-system.com', role: 'admin', full_name: 'Admin User', university_id: null },
  },
  'rector@mit.edu': {
    password: 'uni123',
    user: { id: 2, username: 'mit_admin', email: 'rector@mit.edu', role: 'university', full_name: 'MIT Registrar', university_id: 1 },
  },
  'rector@stanforduniversity.edu': {
    password: 'uni123',
    user: { id: 3, username: 'stanford_admin', email: 'rector@stanforduniversity.edu', role: 'university', full_name: 'Stanford Registrar', university_id: 2 },
  },
  'jane.smith@student.mit.edu': {
    password: 'student123',
    user: { id: 4, username: 'jane.smith', email: 'jane.smith@student.mit.edu', role: 'student', full_name: 'Jane Smith', university_id: 1 },
  },
};

let universities = [
  { id: 1, name: 'Massachusetts Institute of Technology', code: 'MIT', address: 'Cambridge, MA 02139, USA', contact_email: 'rector@mit.edu', contact_phone: '+1-617-253-1000', is_active: true, wallet_address: '0xabc123def456abc123def456abc123def456abc1' },
  { id: 2, name: 'Stanford University', code: 'STANFORD', address: 'Stanford, CA 94305, USA', contact_email: 'rector@stanforduniversity.edu', contact_phone: '+1-650-723-2300', is_active: true, wallet_address: '0xdef456abc123def456abc123def456abc123def4' },
  { id: 3, name: 'Indian Institute of Technology Bombay', code: 'IIT-B', address: 'Powai, Mumbai 400076, India', contact_email: 'director@iitb.ac.in', contact_phone: '+91-22-2572-2545', is_active: true, wallet_address: '0x111222333444555666777888999aaabbbccc111d' },
  { id: 4, name: 'University of Cambridge', code: 'CAMBRIDGE', address: 'Cambridge CB2 1TN, UK', contact_email: 'registrar@cam.ac.uk', contact_phone: '+44-1223-337733', is_active: false, wallet_address: '0x222333444555666777888999aaabbbccc111d222' },
];

let students = [
  { id: 1, user_id: 4,  student_id: 'STU-2022-001', university_id: 1, full_name: 'Jane Smith',      email: 'jane.smith@student.mit.edu',     enrollment_date: '2022-08-01', university_name: 'Massachusetts Institute of Technology' },
  { id: 2, user_id: 5,  student_id: 'STU-2022-002', university_id: 1, full_name: 'Liam Johnson',    email: 'liam.johnson@student.mit.edu',   enrollment_date: '2022-08-01', university_name: 'Massachusetts Institute of Technology' },
  { id: 3, user_id: 6,  student_id: 'STU-2021-003', university_id: 1, full_name: 'Priya Patel',     email: 'priya.patel@student.mit.edu',    enrollment_date: '2021-09-01', university_name: 'Massachusetts Institute of Technology' },
  { id: 4, user_id: 7,  student_id: 'STU-2023-004', university_id: 2, full_name: 'Carlos Rivera',   email: 'carlos.r@student.stanford.edu',  enrollment_date: '2023-01-10', university_name: 'Stanford University' },
  { id: 5, user_id: 8,  student_id: 'STU-2022-005', university_id: 2, full_name: 'Emily Chen',      email: 'emily.chen@student.stanford.edu', enrollment_date: '2022-09-01', university_name: 'Stanford University' },
  { id: 6, user_id: 9,  student_id: 'STU-2020-006', university_id: 3, full_name: 'Rohit Sharma',    email: 'rohit.sharma@student.iitb.ac.in', enrollment_date: '2020-07-01', university_name: 'Indian Institute of Technology Bombay' },
  { id: 7, user_id: 10, student_id: 'STU-2021-007', university_id: 3, full_name: 'Ananya Krishnan', email: 'ananya.k@student.iitb.ac.in',    enrollment_date: '2021-07-01', university_name: 'Indian Institute of Technology Bombay' },
];

let nextStudentId = 8;
let nextUniversityId = 5;

let certificates = [
  {
    id: 1, certificate_id: 'CERT-A1B2C3D4',
    student_id: 1, university_id: 1,
    course_name: 'Computer Science & Engineering', degree_type: 'Bachelor',
    issue_date: '2024-06-15',
    certificate_hash: '0x1a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b',
    blockchain_tx_hash: '0xabcdef1234567890abcdef1234567890abcdef12',
    pdf_hash: '0xaabbcc112233445566778899aabbcc112233445',
    metadata_hash: '0x99aabb112233445566778899aabbcc112233446',
    onchain_hash: '0x88aabb112233445566778899aabbcc112233447',
    status: 'active', is_revoked: false,
    block_number: 19847203, chain_id: 1337,
    student_name: 'Jane Smith', university_name: 'Massachusetts Institute of Technology',
    student_email: 'jane.smith@student.mit.edu', student_id_code: 'STU-2022-001',
    created_at: '2024-06-15T10:30:00Z',
  },
  {
    id: 2, certificate_id: 'CERT-E5F6G7H8',
    student_id: 2, university_id: 1,
    course_name: 'Artificial Intelligence', degree_type: 'Master',
    issue_date: '2024-05-20',
    certificate_hash: '0x2a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b',
    blockchain_tx_hash: '0xbbcdef1234567890abcdef1234567890abcdef13',
    pdf_hash: '0xbbbbcc112233445566778899aabbcc112233445',
    metadata_hash: '0x89aabb112233445566778899aabbcc112233446',
    onchain_hash: '0x78aabb112233445566778899aabbcc112233447',
    status: 'active', is_revoked: false,
    block_number: 19847100, chain_id: 1337,
    student_name: 'Liam Johnson', university_name: 'Massachusetts Institute of Technology',
    student_email: 'liam.johnson@student.mit.edu', student_id_code: 'STU-2022-002',
    created_at: '2024-05-20T09:15:00Z',
  },
  {
    id: 3, certificate_id: 'CERT-I9J0K1L2',
    student_id: 4, university_id: 2,
    course_name: 'Data Science & Analytics', degree_type: 'Master',
    issue_date: '2024-07-01',
    certificate_hash: '0x3a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b',
    blockchain_tx_hash: '0xccdef1234567890abcdef1234567890abcdef14',
    pdf_hash: '0xccbbcc112233445566778899aabbcc112233445',
    metadata_hash: '0x79aabb112233445566778899aabbcc112233446',
    onchain_hash: '0x68aabb112233445566778899aabbcc112233447',
    status: 'revoked', is_revoked: true,
    block_number: 19847300, chain_id: 1337,
    student_name: 'Carlos Rivera', university_name: 'Stanford University',
    student_email: 'carlos.r@student.stanford.edu', student_id_code: 'STU-2023-004',
    created_at: '2024-07-01T08:00:00Z',
  },
  {
    id: 4, certificate_id: 'CERT-M3N4O5P6',
    student_id: 5, university_id: 2,
    course_name: 'Human-Computer Interaction', degree_type: 'Bachelor',
    issue_date: '2024-04-10',
    certificate_hash: '0x4a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b',
    blockchain_tx_hash: '0xdcdef1234567890abcdef1234567890abcdef15',
    pdf_hash: '0xddbbcc112233445566778899aabbcc112233445',
    metadata_hash: '0x69aabb112233445566778899aabbcc112233446',
    onchain_hash: '0x58aabb112233445566778899aabbcc112233447',
    status: 'active', is_revoked: false,
    block_number: 19846000, chain_id: 1337,
    student_name: 'Emily Chen', university_name: 'Stanford University',
    student_email: 'emily.chen@student.stanford.edu', student_id_code: 'STU-2022-005',
    created_at: '2024-04-10T11:00:00Z',
  },
  {
    id: 5, certificate_id: 'CERT-Q7R8S9T0',
    student_id: 6, university_id: 3,
    course_name: 'Electrical Engineering', degree_type: 'Bachelor',
    issue_date: '2024-03-25',
    certificate_hash: '0x5a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b',
    blockchain_tx_hash: '0xecdef1234567890abcdef1234567890abcdef16',
    pdf_hash: '0xeabbcc112233445566778899aabbcc112233445',
    metadata_hash: '0x59aabb112233445566778899aabbcc112233446',
    onchain_hash: '0x48aabb112233445566778899aabbcc112233447',
    status: 'active', is_revoked: false,
    block_number: 19845500, chain_id: 1337,
    student_name: 'Rohit Sharma', university_name: 'Indian Institute of Technology Bombay',
    student_email: 'rohit.sharma@student.iitb.ac.in', student_id_code: 'STU-2020-006',
    created_at: '2024-03-25T07:30:00Z',
  },
  {
    id: 6, certificate_id: 'CERT-U1V2W3X4',
    student_id: 3, university_id: 1,
    course_name: 'Quantum Computing', degree_type: 'Doctor',
    issue_date: '2024-01-08',
    certificate_hash: '0x6a2b3c4d5e6f7a8b9c0d1e2f3a4b5c6d7e8f9a0b',
    blockchain_tx_hash: '0xfcdef1234567890abcdef1234567890abcdef17',
    pdf_hash: '0xfabbcc112233445566778899aabbcc112233445',
    metadata_hash: '0x49aabb112233445566778899aabbcc112233446',
    onchain_hash: '0x38aabb112233445566778899aabbcc112233447',
    status: 'active', is_revoked: false,
    block_number: 19840000, chain_id: 1337,
    student_name: 'Priya Patel', university_name: 'Massachusetts Institute of Technology',
    student_email: 'priya.patel@student.mit.edu', student_id_code: 'STU-2021-003',
    created_at: '2024-01-08T13:00:00Z',
  },
];

let nextCertId = 7;
function genCertId() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let s = '';
  for (let i = 0; i < 8; i++) s += chars[Math.floor(Math.random() * chars.length)];
  return `CERT-${s}`;
}

// ═══════════════════════════════════════════════════════════════
//  ROUTES  —  all prefixed /api/...
// ═══════════════════════════════════════════════════════════════

const api = express.Router();
app.use('/api', api);

// ── Auth ────────────────────────────────────────────────────────
api.post('/auth/login', (req, res) => {
  const { email, password } = req.body ?? {};
  const entry = USERS[email?.toLowerCase()];
  if (!entry || entry.password !== password) {
    return err(res, 'Invalid email or password', 401);
  }
  const token = makeToken(entry.user);
  ok(res, { token, user: entry.user });
});

api.post('/auth/register', (req, res) => {
  ok(res, { message: 'Registration is handled by the university admin. Contact your registrar.' });
});

api.post('/auth/change-password', (req, res) => {
  ok(res, { message: 'Password changed successfully.' });
});

// ── Universities ─────────────────────────────────────────────────
api.get('/universities', (_req, res) => {
  ok(res, { universities });
});

api.post('/universities', (req, res) => {
  const { name, code, address = '', contact_email = '', contact_phone = '' } = req.body ?? {};
  if (!name || !code) return err(res, 'Name and code are required');
  const u = { id: nextUniversityId++, name, code, address, contact_email, contact_phone, is_active: true, wallet_address: '0x0000000000000000000000000000000000000000' };
  universities.push(u);
  ok(res, { message: 'University created successfully', university: u });
});

api.put('/universities/:id', (req, res) => {
  const id = parseInt(req.params.id);
  const idx = universities.findIndex(u => u.id === id);
  if (idx === -1) return err(res, 'University not found', 404);
  universities[idx] = { ...universities[idx], ...req.body };
  ok(res, { message: 'University updated' });
});

api.delete('/universities/:id', (req, res) => {
  const id = parseInt(req.params.id);
  universities = universities.filter(u => u.id !== id);
  ok(res, { message: 'University deleted' });
});

// ── Students ──────────────────────────────────────────────────────
api.get('/students', (_req, res) => {
  ok(res, { students });
});

api.post('/students', (req, res) => {
  const { username, email, full_name, student_id, enrollment_date, university_id = 1 } = req.body ?? {};
  if (!username || !email || !full_name || !student_id) return err(res, 'Required fields missing');
  const s = {
    id: nextStudentId++, user_id: 100 + nextStudentId,
    student_id, university_id: parseInt(university_id),
    full_name, email, enrollment_date: enrollment_date ?? new Date().toISOString().split('T')[0],
    university_name: universities.find(u => u.id === parseInt(university_id))?.name ?? '—',
  };
  students.push(s);
  ok(res, { message: 'Student created successfully', student: s });
});

api.put('/students/:id', (req, res) => {
  const id = parseInt(req.params.id);
  const idx = students.findIndex(s => s.id === id);
  if (idx === -1) return err(res, 'Student not found', 404);
  students[idx] = { ...students[idx], ...req.body };
  ok(res, { message: 'Student updated' });
});

api.delete('/students/:id', (req, res) => {
  const id = parseInt(req.params.id);
  students = students.filter(s => s.id !== id);
  ok(res, { message: 'Student deleted' });
});

// ── Certificates ─────────────────────────────────────────────────
api.get('/certificates', (req, res) => {
  // Role-scoped by reading JWT (loose – no verification for dummy)
  const auth = req.headers.authorization ?? '';
  let role = 'admin', university_id = null, user_id = null;
  try {
    const payload = jwt.verify(auth.replace('Bearer ', ''), JWT_SECRET);
    role = payload.role;
    const u = Object.values(USERS).find(u => u.user.email === payload.email);
    university_id = u?.user?.university_id ?? null;
    user_id = payload.user_id;
  } catch { /* unauthenticated – return all */ }

  let result = certificates;
  if (role === 'university') result = certificates.filter(c => c.university_id === university_id);
  if (role === 'student')    result = certificates.filter(c => c.student_id === 1); // Jane Smith
  ok(res, { certificates: result });
});

api.post('/certificates/create', (req, res) => {
  const { student_id, university_id, course_name, degree_type, issue_date } = req.body ?? {};
  const student = students.find(s => s.id === parseInt(student_id));
  const university = universities.find(u => u.id === parseInt(university_id));
  const cert_id = genCertId();
  const tx = '0x' + Array.from({ length: 40 }, () => Math.floor(Math.random() * 16).toString(16)).join('');
  const newCert = {
    id: nextCertId++, certificate_id: cert_id,
    student_id: parseInt(student_id), university_id: parseInt(university_id),
    course_name, degree_type: degree_type ?? 'Certificate',
    issue_date: issue_date ?? new Date().toISOString().split('T')[0],
    certificate_hash: '0x' + Math.random().toString(16).slice(2).padEnd(40, '0'),
    blockchain_tx_hash: tx,
    pdf_hash: '0x' + Math.random().toString(16).slice(2).padEnd(40, '0'),
    metadata_hash: '0x' + Math.random().toString(16).slice(2).padEnd(40, '0'),
    onchain_hash: tx,
    status: 'active', is_revoked: false,
    block_number: 19847200 + nextCertId, chain_id: 1337,
    student_name: student?.full_name ?? 'Unknown',
    university_name: university?.name ?? 'Unknown',
    student_email: student?.email ?? '',
    student_id_code: student?.student_id ?? '',
    created_at: new Date().toISOString(),
  };
  certificates.push(newCert);
  ok(res, { certificate_id: cert_id, blockchain_tx_hash: tx, certificate: newCert });
});

api.post('/certificates/revoke', (req, res) => {
  const { certificate_id } = req.body ?? {};
  const idx = certificates.findIndex(c => c.certificate_id === certificate_id);
  if (idx === -1) return err(res, 'Certificate not found', 404);
  certificates[idx].status = 'revoked';
  certificates[idx].is_revoked = true;
  ok(res, { message: 'Certificate revoked successfully' });
});

api.post('/certificates/verify', (req, res) => {
  const { certificate_id } = req.body ?? {};
  const cert = certificates.find(c => c.certificate_id === certificate_id);
  if (!cert) return res.json({ success: false, valid: false, status: 'not_found', message: 'Certificate not found' });
  ok(res, {
    valid: cert.status === 'active',
    status: cert.status === 'active' ? 'valid' : 'revoked',
    message: cert.status === 'active' ? 'Certificate is valid and verified on blockchain' : 'Certificate has been revoked',
    certificate: cert,
    verification_details: { hash_match: true, blockchain_verified: true, signature_valid: true, not_revoked: !cert.is_revoked },
  });
});

api.get('/certificates/download', (req, res) => {
  // Return a minimal valid PDF bytes response
  const cert_id = req.query.certificate_id ?? 'UNKNOWN';
  res.setHeader('Content-Type', 'application/pdf');
  res.setHeader('Content-Disposition', `attachment; filename="${cert_id}.pdf"`);
  // Minimal valid 1-page PDF
  const pdf = `%PDF-1.4
1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj
2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj
3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj
4 0 obj<</Length 120>>stream
BT /F1 24 Tf 180 700 Td (CertiLedger Certificate) Tj 0 -40 Td /F1 14 Tf (ID: ${cert_id}) Tj 0 -30 Td (This is a dummy PDF for development.) Tj ET
endstream endobj
5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj
xref 0 6
0000000000 65535 f\r
trailer<</Size 6/Root 1 0 R>>
startxref 9
%%EOF`;
  res.end(pdf);
});

// ── Public Verification ──────────────────────────────────────────
api.get('/public/verify', (req, res) => {
  const { certificate_id } = req.query;
  if (!certificate_id) return err(res, 'certificate_id is required');
  const cert = certificates.find(c => c.certificate_id === certificate_id);
  if (!cert) {
    return res.json({ success: false, valid: false, status: 'not_found', message: 'Certificate not found in system' });
  }
  ok(res, {
    valid: cert.status === 'active',
    status: cert.status === 'active' ? 'valid' : 'revoked',
    message: cert.status === 'active' ? 'Certificate is valid and verified on blockchain' : 'Certificate has been revoked',
    certificate: cert,
    verification_details: { hash_match: true, blockchain_verified: true, signature_valid: true, not_revoked: !cert.is_revoked },
  });
});

api.post('/public/verify', (req, res) => {
  const { certificate_id } = req.body ?? {};
  const cert = certificates.find(c => c.certificate_id === certificate_id);
  if (!cert) return res.json({ success: false, valid: false, status: 'not_found' });
  ok(res, { valid: cert.status === 'active', status: cert.status === 'active' ? 'valid' : 'revoked', certificate: cert });
});

api.get('/public/certificate/download', (req, res) => {
  const cert_id = req.query.certificate_id ?? 'UNKNOWN';
  res.setHeader('Content-Type', 'application/pdf');
  res.setHeader('Content-Disposition', `attachment; filename="${cert_id}.pdf"`);
  const pdf = `%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/MediaBox[0 0 612 792]/Parent 2 0 R/Contents 4 0 R/Resources<</Font<</F1 5 0 R>>>>>>endobj\n4 0 obj<</Length 80>>stream\nBT /F1 20 Tf 180 700 Td (CertiLedger) Tj 0 -40 Td (${cert_id}) Tj ET\nendstream endobj\n5 0 obj<</Type/Font/Subtype/Type1/BaseFont/Helvetica>>endobj\nxref 0 6\n0000000000 65535 f\r\ntrailer<</Size 6/Root 1 0 R>>\nstartxref 9\n%%EOF`;
  res.end(pdf);
});

// ── Health check ─────────────────────────────────────────────────
api.get('/health', (_req, res) => res.json({ status: 'ok', time: new Date().toISOString() }));

// ── 404 catch-all ────────────────────────────────────────────────
api.use((req, res) => {
  console.warn(`  ⚠ 404  ${req.method} ${req.path}`);
  res.status(404).json({ error: 'Endpoint not found' });
});

// ─────────────────────────────────────────────────────────────────
app.listen(PORT, () => {
  console.log(`\n  ┌─────────────────────────────────────────────┐`);
  console.log(`  │  CertiLedger Dummy Backend                  │`);
  console.log(`  │  http://localhost:${PORT}/api                  │`);
  console.log(`  └─────────────────────────────────────────────┘\n`);
  console.log(`  Test accounts:`);
  console.log(`  ┌──────────────────────────────────────────────────────┐`);
  console.log(`  │ Admin      admin@certificate-system.com / admin123    │`);
  console.log(`  │ University rector@mit.edu / uni123                    │`);
  console.log(`  │ University rector@stanforduniversity.edu / uni123     │`);
  console.log(`  │ Student    jane.smith@student.mit.edu / student123   │`);
  console.log(`  └──────────────────────────────────────────────────────┘\n`);
});
