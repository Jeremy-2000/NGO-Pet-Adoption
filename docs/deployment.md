# Deployment Guide for Hostinger

## Requirements

- PHP 8+
- MySQL 8+ or compatible MariaDB
- GD extension recommended for image resizing and thumbnails

## Steps

1. Upload the project to the hosting account.
2. Point the domain web root to `public/`.
3. Update `config/database.php` or set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS`.
4. Set `APP_ENV=production`.
5. Run `php database/install.php`.
6. Sign in at `/login.php` with the seeded admin account and change credentials after launch.
7. Confirm `public/uploads` is writable by PHP.

## Optional path config

If the site is not hosted from the domain root, set `APP_BASE_PATH` to the public path prefix.

## Launch checks

- Admin login works.
- Shelter application flow works.
- Admin approval changes shelter status.
- Approved shelter can create a listing with images.
- Public animal detail inquiry creates an inquiry in the shelter portal.
- Reports and votes appear in admin/dashboard metrics.
