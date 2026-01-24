import React, { useEffect, useState } from 'react';
import { toast } from 'react-toastify';
import { certificateAPI, studentAPI } from '../services/api';
import Navbar from './Navbar';

const UniversityDashboard = () => {
  const [certificates, setCertificates] = useState([]);
  const [students, setStudents] = useState([]);
  const [activeTab, setActiveTab] = useState('certificates');
  const [newStudent, setNewStudent] = useState({
    username: '',
    email: '',
    password: '',
    full_name: '',
    student_id: '',
    enrollment_date: ''
  });
  const [newCertificate, setNewCertificate] = useState({
    student_id: '',
    course_name: '',
    degree_type: '',
    issue_date: ''
  });

  const user = JSON.parse(localStorage.getItem('user') || '{}');

  useEffect(() => {
    loadData();
  }, []);

  const loadData = async () => {
    try {
      const [certsRes, studRes] = await Promise.all([
        certificateAPI.getAll(),
        studentAPI.getAll()
      ]);

      if (certsRes.data.success) setCertificates(certsRes.data.certificates);
      if (studRes.data.success) setStudents(studRes.data.students);
    } catch (error) {
      toast.error('Failed to load data');
    }
  };

  const handleAddStudent = async (e) => {
    e.preventDefault();
    try {
      await studentAPI.create(newStudent);
      toast.success('Student added');
      setNewStudent({
        username: '',
        email: '',
        password: '',
        full_name: '',
        student_id: '',
        enrollment_date: ''
      });
      loadData();
    } catch (error) {
      toast.error('Failed to add student');
    }
  };

  const handleCreateCertificate = async (e) => {
    e.preventDefault();
    try {
      const response = await certificateAPI.create(newCertificate);
      if (response.data.success) {
        toast.success('Certificate created successfully!');
        setNewCertificate({
          student_id: '',
          course_name: '',
          degree_type: '',
          issue_date: ''
        });
        loadData();
      }
    } catch (error) {
      toast.error('Failed to create certificate');
    }
  };

  return (
    <div>
      <Navbar />
      <div className="container">
        <div className="dashboard-header">
          <h1>University Dashboard</h1>
        </div>

        <div className="stats-grid">
          <div className="stat-card">
            <h3>Total Certificates</h3>
            <div className="value">{certificates.length}</div>
          </div>
          <div className="stat-card">
            <h3>Total Students</h3>
            <div className="value">{students.length}</div>
          </div>
        </div>

        <div className="card">
          <div style={{ display: 'flex', gap: '10px', marginBottom: '20px' }}>
            <button
              className={`btn ${activeTab === 'certificates' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setActiveTab('certificates')}
            >
              Certificates
            </button>
            <button
              className={`btn ${activeTab === 'students' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setActiveTab('students')}
            >
              Students
            </button>
            <button
              className={`btn ${activeTab === 'create-cert' ? 'btn-primary' : 'btn-secondary'}`}
              onClick={() => setActiveTab('create-cert')}
            >
              Create Certificate
            </button>
          </div>

          {activeTab === 'certificates' && (
            <div>
              <h2>Issued Certificates</h2>
              <table className="table">
                <thead>
                  <tr>
                    <th>Certificate ID</th>
                    <th>Student</th>
                    <th>Course</th>
                    <th>Issue Date</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  {certificates.map((cert) => (
                    <tr key={cert.id}>
                      <td>{cert.certificate_id}</td>
                      <td>{cert.student_name}</td>
                      <td>{cert.course_name}</td>
                      <td>{cert.issue_date}</td>
                      <td>{cert.is_revoked ? 'Revoked' : 'Valid'}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {activeTab === 'students' && (
            <div>
              <h2>Students</h2>
              <form onSubmit={handleAddStudent} style={{ marginBottom: '20px' }}>
                <h3>Add New Student</h3>
                <div className="form-group">
                  <label>Username</label>
                  <input
                    type="text"
                    value={newStudent.username}
                    onChange={(e) => setNewStudent({ ...newStudent, username: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Email</label>
                  <input
                    type="email"
                    value={newStudent.email}
                    onChange={(e) => setNewStudent({ ...newStudent, email: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Password</label>
                  <input
                    type="password"
                    value={newStudent.password}
                    onChange={(e) => setNewStudent({ ...newStudent, password: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Full Name</label>
                  <input
                    type="text"
                    value={newStudent.full_name}
                    onChange={(e) => setNewStudent({ ...newStudent, full_name: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Student ID</label>
                  <input
                    type="text"
                    value={newStudent.student_id}
                    onChange={(e) => setNewStudent({ ...newStudent, student_id: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Enrollment Date</label>
                  <input
                    type="date"
                    value={newStudent.enrollment_date}
                    onChange={(e) => setNewStudent({ ...newStudent, enrollment_date: e.target.value })}
                  />
                </div>
                <button type="submit" className="btn btn-primary">Add Student</button>
              </form>

              <table className="table">
                <thead>
                  <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Email</th>
                  </tr>
                </thead>
                <tbody>
                  {students.map((student) => (
                    <tr key={student.id}>
                      <td>{student.student_id}</td>
                      <td>{student.full_name}</td>
                      <td>{student.email}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}

          {activeTab === 'create-cert' && (
            <div>
              <h2>Create Certificate</h2>
              <form onSubmit={handleCreateCertificate}>
                <div className="form-group">
                  <label>Student</label>
                  <select
                    value={newCertificate.student_id}
                    onChange={(e) => setNewCertificate({ ...newCertificate, student_id: e.target.value })}
                    required
                  >
                    <option value="">Select Student</option>
                    {students.map((student) => (
                      <option key={student.id} value={student.id}>
                        {student.full_name} ({student.student_id})
                      </option>
                    ))}
                  </select>
                </div>
                <div className="form-group">
                  <label>Course Name</label>
                  <input
                    type="text"
                    value={newCertificate.course_name}
                    onChange={(e) => setNewCertificate({ ...newCertificate, course_name: e.target.value })}
                    required
                  />
                </div>
                <div className="form-group">
                  <label>Degree Type</label>
                  <input
                    type="text"
                    value={newCertificate.degree_type}
                    onChange={(e) => setNewCertificate({ ...newCertificate, degree_type: e.target.value })}
                    placeholder="e.g., Bachelor's, Master's"
                  />
                </div>
                <div className="form-group">
                  <label>Issue Date</label>
                  <input
                    type="date"
                    value={newCertificate.issue_date}
                    onChange={(e) => setNewCertificate({ ...newCertificate, issue_date: e.target.value })}
                    required
                  />
                </div>
                <button type="submit" className="btn btn-success">Generate Certificate</button>
              </form>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default UniversityDashboard;

