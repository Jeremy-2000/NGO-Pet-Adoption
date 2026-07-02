# Security Guide

## Implemented

- Passwords use `password_hash()` and `password_verify()`.
- Sessions are regenerated on login and configured with `HttpOnly`, `SameSite=Lax`, strict mode, and secure cookies on HTTPS.
- CSRF tokens protect state-changing forms.
- Output is escaped with `e()`.
- Database access uses PDO prepared statements.
- Role checks protect shelter and admin portals.
- Uploads validate extension, MIME type, image structure, file size, and use unique filenames.
- Rate limiting protects inquiries, votes, favorites, and reports.
- Audit logging records key actions.
- Security headers include frame, content-type, referrer, and permissions policies.

## Production tasks

- Rotate the seeded admin password immediately.
- Set a unique `APP_KEY` for stable hashing.
- Keep `public/uploads` executable scripts disabled at the web server level.
- Add a CAPTCHA provider to the inquiry/report forms if abuse appears.
- Review PHP upload size limits against `config/app.php`.
