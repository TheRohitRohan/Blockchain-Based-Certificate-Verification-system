import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { toast } from 'react-toastify';
import { authAPI } from '../services/api';
import './Login.css';

const Login = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);

    try {
      const response = await authAPI.login(email, password);
      if (response.data.success) {
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        
        toast.success('Login successful!');
        
        // Redirect based on role
        const role = response.data.user.role;
        if (role === 'admin') {
          navigate('/admin');
        } else if (role === 'university') {
          navigate('/university');
        } else if (role === 'student') {
          navigate('/student');
        } else {
          navigate('/dashboard');
        }
      }
    } catch (error) {
      toast.error(error.response?.data?.error || 'Login failed');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="login-container">
      <div className="login-card">
        <h2>Certificate Verification System</h2>
        <form onSubmit={handleSubmit}>
          <div className="form-group">
            <label>Email</label>
            <input
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              required
            />
          </div>
          <div className="form-group">
            <label>Password</label>
            <input
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              required
            />
          </div>
          <button type="submit" className="btn btn-primary" disabled={loading}>
            {loading ? 'Logging in...' : 'Login'}
          </button>
        </form>
        <div style={{ marginTop: '20px', textAlign: 'center' }}>
          <a href="/verify" style={{ color: '#007bff', textDecoration: 'none' }}>
            Verify Certificate (Public)
          </a>
        </div>
        <div style={{ marginTop: '10px', fontSize: '12px', color: '#666', textAlign: 'center' }}>
          Demo: admin@certificate-system.com / admin123
        </div>
      </div>
    </div>
  );
};

export default Login;

