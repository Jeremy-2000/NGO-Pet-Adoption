# Endpoint Documentation

This is a page-rendered PHP app, not a JSON API. The following endpoints are the supported HTTP workflows.

## Public

- `GET /animals.php`: paginated search with `q`, `species`, `breed`, `age`, `gender`, `size`, `location`, `status`, `special_needs`, `child_friendly`, `page`.
- `GET /animal.php?id={id}`: animal profile and gallery.
- `POST /animal.php`: `action=inquiry`, `action=favorite`, or `action=report`.
- `GET /shelters.php`: approved shelter directory.
- `GET /shelter.php?slug={slug}`: public shelter profile.
- `GET /vote.php`: voting matchup.
- `POST /vote.php`: records a matchup vote.

## Auth

- `GET|POST /login.php`: sign in.
- `GET|POST /register.php`: shelter application.
- `GET /logout.php`: sign out.

## Shelter

- `GET /shelter/dashboard.php`: shelter metrics.
- `GET|POST /shelter/profile.php`: profile and logo update.
- `GET|POST /shelter/listings.php`: create/edit listings and upload images.
- `GET|POST /shelter/inquiries.php`: review inquiry status.

## Admin

- `GET /admin/dashboard.php`: platform metrics and activity.
- `GET|POST /admin/users.php`: user list, account details, and admin password reset.
- `GET|POST /admin/shelters.php`: approve/reject shelter applications.
- `GET|POST /admin/animals.php`: moderate listing status and featured flag.
- `GET|POST /admin/reports.php`: update report status.
