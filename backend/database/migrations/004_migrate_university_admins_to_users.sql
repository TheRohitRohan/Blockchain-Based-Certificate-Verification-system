-- Migration 004: University portal credentials live in `users` (role = 'university') only.
-- Run AFTER deploying backend code that no longer reads `university_admins`.
--
-- If you never had `university_admins` (only `users`), skip the INSERT and run only:
--   DROP TABLE IF EXISTS university_admins;
--
-- 1) Copy each active university_admins row into users when that email is not already taken.
-- 2) Drop university_admins (passwords no longer stored there).

INSERT INTO users (username, email, password_hash, role, full_name, university_id)
SELECT
    CONCAT('univ_admin_', ua.id) AS username,
    LOWER(TRIM(ua.email)) AS email,
    ua.password_hash,
    'university' AS role,
    ua.name AS full_name,
    ua.university_id
FROM university_admins ua
WHERE COALESCE(ua.is_active, 1) = 1
  AND NOT EXISTS (
      SELECT 1 FROM users u WHERE LOWER(TRIM(u.email)) = LOWER(TRIM(ua.email))
  );

DROP TABLE IF EXISTS university_admins;
