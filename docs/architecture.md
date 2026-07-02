# Architecture Documentation

## Goals

- Connect approved shelters with adopters through a fast, accessible public experience.
- Keep PHP/MySQL deployment compatible with Hostinger shared hosting.
- Keep core logic reusable through helpers, services, and repositories instead of page-level SQL.

## Runtime flow

1. Public entrypoints under `public/` require `app/bootstrap.php`.
2. Bootstrap starts secure sessions, sets security headers, loads helpers, services, and repositories.
3. Pages call repositories for data access and services for scoring, uploads, and rate limiting.
4. Views render escaped output with `e()` and protect state-changing forms with CSRF tokens.

## Main layers

- `app/helpers/functions.php`: config, DB connection, URLs, escaping, CSRF, auth, flash messages, audit logging.
- `app/repositories/AnimalRepository.php`: public search, animal detail, shelter listings, listing CRUD, favorites, voting pairs.
- `app/repositories/ShelterRepository.php`: public shelter profiles, admin approval updates, shelter profile edits.
- `app/services/VisibilityService.php`: configurable overlooked-animal scoring.
- `app/services/UploadService.php`: secure image validation, resizing, thumbnails, and storage.
- `app/services/RateLimiter.php`: database-backed action throttling.

## Access model

- Visitors can browse, search, submit inquiries, favorite, report, and vote.
- Shelters can use the portal after registration, but only approved shelters can publish or edit listings.
- Admins can approve/reject shelters, moderate listings, feature animals, review reports, and inspect activity.

## Key decisions

- Vanilla PHP keeps deployment simple for shared hosting.
- PDO prepared statements are used for database access.
- Visibility scoring is configuration-driven via `config/app.php`.
- Uploads are stored under `public/uploads` with generated filenames and database records.
- Rate limits store hashed visitor identity, not raw IP addresses.
