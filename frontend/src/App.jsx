import '@fontsource-variable/mona-sans';
import './index.css';
import './App.css';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router';
import { Toaster } from 'react-hot-toast';

// Guards & Layouts
import PrivateRoute from './guards/PrivateRoute';
import RoleRoute from './guards/RoleRoute';
import DashboardLayout from './layouts/DashboardLayout';
import AuthLayout from './layouts/AuthLayout';

// Public pages
import LoginPage from './pages/LoginPage';
import ForgotPasswordPage from './pages/ForgotPasswordPage';
import ResetPasswordPage from './pages/ResetPasswordPage';
import VerifyPage from './pages/VerifyPage';
import NotFoundPage from './pages/NotFoundPage';

// Admin pages
import AdminDashboard from './pages/admin/AdminDashboard';
import AdminUniversities from './pages/admin/AdminUniversities';
import AdminCertificates from './pages/admin/AdminCertificates';

// University pages
import UniversityDashboard from './pages/university/UniversityDashboard';
import UniversityStudents from './pages/university/UniversityStudents';
import IssueCertificate from './pages/university/IssueCertificate';
import UniversityCertificates from './pages/university/UniversityCertificates';
import UniversityLogin from './pages/university/UniversityLogin';
import UniversityRegister from './pages/university/UniversityRegister';

// Student pages
import StudentDashboard from './pages/student/StudentDashboard';

// Shared
import ProfilePage from './pages/ProfilePage';
import LandingPage from './components/LandingPage';

export default function App() {
  return (
    <BrowserRouter>
      <Toaster
        position="top-right"
        toastOptions={{
          style: {
            background: '#0d0d0d',
            color: '#e8e8e8',
            border: '1px solid #1e1e1e',
            borderRadius: '6px',
            fontSize: '0.8rem',
          },
          success: { iconTheme: { primary: '#22c55e', secondary: '#0d0d0d' } },
          error:   { iconTheme: { primary: '#ef4444', secondary: '#0d0d0d' } },
        }}
      />
      <Routes>
        {/* Landing page */}
        <Route index element={<LandingPage />} />

        {/* Public */}
        <Route path="/login" element={<LoginPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/reset-password" element={<ResetPasswordPage />} />
        <Route path="/university/login" element={<UniversityLogin />} />
        <Route path="/university/register" element={<UniversityRegister />} />
        <Route path="/verify" element={<VerifyPage />} />

        {/* Protected dashboard routes */}
        <Route element={<PrivateRoute><DashboardLayout /></PrivateRoute>}>
          {/* Admin */}
          <Route path="/admin" element={
            <RoleRoute allowedRoles={['admin']}><AdminDashboard /></RoleRoute>
          } />
          <Route path="/admin/universities" element={
            <RoleRoute allowedRoles={['admin']}><AdminUniversities /></RoleRoute>
          } />
          <Route path="/admin/certificates" element={
            <RoleRoute allowedRoles={['admin']}><AdminCertificates /></RoleRoute>
          } />

          {/* University */}
          <Route path="/university" element={
            <RoleRoute allowedRoles={['university', 'admin']}><UniversityDashboard /></RoleRoute>
          } />
          <Route path="/university/students" element={
            <RoleRoute allowedRoles={['university', 'admin']}><UniversityStudents /></RoleRoute>
          } />
          <Route path="/university/issue" element={
            <RoleRoute allowedRoles={['university', 'admin']}><IssueCertificate /></RoleRoute>
          } />
          <Route path="/university/certificates" element={
            <RoleRoute allowedRoles={['university', 'admin']}><UniversityCertificates /></RoleRoute>
          } />

          {/* Student */}
          <Route path="/student" element={
            <RoleRoute allowedRoles={['student']}><StudentDashboard /></RoleRoute>
          } />

          {/* Shared */}
          <Route path="/profile" element={<ProfilePage />} />

          {/* Legacy /dashboard redirect */}
          <Route path="/dashboard" element={<Navigate to="/" replace />} />
        </Route>

        {/* 404 */}
        <Route path="*" element={<NotFoundPage />} />
      </Routes>
    </BrowserRouter>
  );
}
