import React from 'react';
import { useNavigate, Link } from 'react-router-dom';

const Navbar = () => {
  const navigate = useNavigate();
  const user = JSON.parse(localStorage.getItem('user') || '{}');

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/login');
  };

  return (
    <nav className="navbar">
      <h1>Certificate System</h1>
      <ul className="navbar-nav">
        <li>
          <Link to="/dashboard">Dashboard</Link>
        </li>
        {user.role === 'admin' && (
          <li>
            <Link to="/admin">Admin</Link>
          </li>
        )}
        {(user.role === 'university' || user.role === 'admin') && (
          <li>
            <Link to="/university">University</Link>
          </li>
        )}
        {user.role === 'student' && (
          <li>
            <Link to="/student">My Certificates</Link>
          </li>
        )}
        <li>
          <Link to="/verify">Verify</Link>
        </li>
        <li>
          <button onClick={handleLogout}>Logout</button>
        </li>
      </ul>
    </nav>
  );
};

export default Navbar;

