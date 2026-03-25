import { useDataContext } from '../context/DataContext';

/**
 * useData — access reference data (universities, students) and actions.
 *
 * @returns {{
 *   universities: object[],
 *   students: object[],
 *   isLoading: boolean,
 *   error: string|null,
 *   fetchUniversities: () => Promise<void>,
 *   addUniversity: (data: object) => Promise<{success, error?}>,
 *   fetchStudents: () => Promise<void>,
 *   addStudent: (data: object) => Promise<{success, error?}>,
 * }}
 *
 * Usage:
 *   const { universities, fetchUniversities } = useData();
 */
export function useData() {
  return useDataContext();
}
