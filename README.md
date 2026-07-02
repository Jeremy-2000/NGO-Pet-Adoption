# NGO Pet Adoption Platform

Production-oriented PHP 8 + MySQL animal adoption platform for visitors, shelters, and administrators.

## What is included

- Public discovery: homepage, paginated animal search, filters, shelter directory, shelter profiles, animal details, adopter accounts, applications, favourites, reports, and voting.
- Shelter portal: approval-aware dashboard, task queue, profile management, listing create/edit/preview, photo ordering/crop focus, inquiry pipeline, adoption applications, and appointment tracking.
- Admin console: shelter approval queue, listing moderation, featured listings, report management, global search, CSV exports, taxonomy management, audit log, and platform metrics.
- Backend foundation: shared bootstrap, secure sessions, security headers, CSRF protection, prepared statements, rate limiting, audit logging, repository/service layers, and configurable visibility scoring.
- Upload foundation: JPG/PNG/WebP validation, MIME checks, unique filenames, resizing/compression when GD is available, and thumbnails.

## Install

1. Create or configure MySQL credentials in `config/database.php`.
2. Run `php database/install.php` from the project root.
3. Point the web root at `public/`.
4. Sign in with `admin@petadoption.local` / `Admin123!`.

For an existing local database, run `php database/upgrade.php` after pulling updates.

## Main paths

- Public: `/`, `/animals.php`, `/animal.php?id=1`, `/account.php`, `/shelters.php`, `/shelter.php?slug=...`, `/vote.php`
- Shelter: `/shelter/dashboard.php`, `/shelter/profile.php`, `/shelter/listings.php`, `/shelter/inquiries.php`, `/shelter/applications.php`
- Admin: `/admin/dashboard.php`, `/admin/search.php`, `/admin/shelters.php`, `/admin/animals.php`, `/admin/reports.php`, `/admin/activity.php`, `/admin/taxonomy.php`

## Documentation

- `docs/architecture.md`
- `docs/database.md`
- `docs/api.md`
- `docs/deployment.md`
- `docs/security.md`
- `docs/maintenance.md`
- `docs/phase-plan.md`
