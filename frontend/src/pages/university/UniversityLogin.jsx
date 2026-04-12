import { Navigate } from 'react-router';

/** @deprecated Use `/login` — all roles sign in there. */
export default function UniversityLogin() {
  return <Navigate to="/login" replace />;
}
