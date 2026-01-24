import React from 'react';
import { BrowserRouter as Router, Routes, Route, Navigate } from 'react-router-dom';
import { ToastContainer } from 'react-toastify';
import 'react-toastify/dist/ReactToastify.css';
import Login from './components/Login';
import Dashboard from './components/Dashboard';
import AdminDashboard from './components/AdminDashboard';
import UniversityDashboard from './components/UniversityDashboard';
import StudentDashboard from './components/StudentDashboard';
import VerificationPortal from './components/VerificationPortal';
import ProtectedRoute from './components/ProtectedRoute';
import './App.css';

function App() {
  return (
    <Router>
      <div className="App">
        <Routes>
          <Route path="/login" element={<Login />} />
          <Route path="/verify" element={<VerificationPortal />} />
          <Route path="/dashboard" element={
            <ProtectedRoute>
              <Dashboard />
            </ProtectedRoute>
          } />
          <Route path="/admin" element={
            <ProtectedRoute allowedRoles={['admin']}>
              <AdminDashboard />
            </ProtectedRoute>
          } />
          <Route path="/university" element={
            <ProtectedRoute allowedRoles={['university', 'admin']}>
              <UniversityDashboard />
            </ProtectedRoute>
          } />
          <Route path="/student" element={
            <ProtectedRoute allowedRoles={['student']}>
              <StudentDashboard />
            </ProtectedRoute>
          } />
          <Route path="/" element={<Navigate to="/login" replace />} />
        </Routes>
        <ToastContainer position="top-right" />
      </div>
    </Router>
  );
}

export default App;

