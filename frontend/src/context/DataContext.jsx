import React, {
  createContext,
  useContext,
  useReducer,
  useCallback,
} from 'react';
import { listUniversities, createUniversity } from '../api/universities.api';
import { listStudents, createStudent } from '../api/students.api';

// ─────────────────────────────────────────────────────────────
//  DataContext — reference / lookup data
//
//  Holds universities and students lists separately from
//  certificates so each can be fetched independently.
//
//  State:
//    universities   — list of active university objects
//    students       — list of student objects (role-scoped)
//    isLoading      — unified loading flag
//    error          — last error message or null
//
//  Actions:
//    fetchUniversities()
//    fetchStudents()
//    addUniversity(data)   → creates + re-fetches
//    addStudent(data)      → creates + re-fetches
// ─────────────────────────────────────────────────────────────

const initialState = {
  universities: [],
  students: [],
  isLoading: false,
  error: null,
};

function dataReducer(state, action) {
  switch (action.type) {
    case 'SET_LOADING':
      return { ...state, isLoading: action.payload, error: null };
    case 'SET_ERROR':
      return { ...state, isLoading: false, error: action.payload };
    case 'SET_UNIVERSITIES':
      return { ...state, universities: action.payload, isLoading: false };
    case 'SET_STUDENTS':
      return { ...state, students: action.payload, isLoading: false };
    default:
      return state;
  }
}

export const DataContext = createContext(null);

export function DataProvider({ children }) {
  const [state, dispatch] = useReducer(dataReducer, initialState);

  // ── Universities ─────────────────────────────────────────
  const fetchUniversities = useCallback(async () => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const data = await listUniversities();
      dispatch({ type: 'SET_UNIVERSITIES', payload: data.universities ?? [] });
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Failed to load universities';
      dispatch({ type: 'SET_ERROR', payload: msg });
    }
  }, []);

  const addUniversity = useCallback(async (formData) => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const result = await createUniversity(formData);
      if (result.success) {
        await fetchUniversities(); // keep list fresh
      } else {
        dispatch({ type: 'SET_ERROR', payload: result.error ?? 'Failed to create university' });
      }
      return result;
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Failed to create university';
      dispatch({ type: 'SET_ERROR', payload: msg });
      return { success: false, error: msg };
    }
  }, [fetchUniversities]);

  // ── Students ─────────────────────────────────────────────
  const fetchStudents = useCallback(async () => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const data = await listStudents();
      dispatch({ type: 'SET_STUDENTS', payload: data.students ?? [] });
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Failed to load students';
      dispatch({ type: 'SET_ERROR', payload: msg });
    }
  }, []);

  const addStudent = useCallback(async (formData) => {
    dispatch({ type: 'SET_LOADING', payload: true });
    try {
      const result = await createStudent(formData);
      if (result.success) {
        await fetchStudents(); // keep list fresh
      } else {
        dispatch({ type: 'SET_ERROR', payload: result.error ?? 'Failed to create student' });
      }
      return result;
    } catch (err) {
      const msg = err.response?.data?.error ?? err.message ?? 'Failed to create student';
      dispatch({ type: 'SET_ERROR', payload: msg });
      return { success: false, error: msg };
    }
  }, [fetchStudents]);

  const value = {
    // State
    universities: state.universities,
    students: state.students,
    isLoading: state.isLoading,
    error: state.error,
    // Actions
    fetchUniversities,
    addUniversity,
    fetchStudents,
    addStudent,
  };

  return <DataContext.Provider value={value}>{children}</DataContext.Provider>;
}

export function useDataContext() {
  const ctx = useContext(DataContext);
  if (!ctx) throw new Error('useDataContext must be used inside <DataProvider>');
  return ctx;
}
