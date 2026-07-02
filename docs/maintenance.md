# Maintenance Guide

## Routine checks

- Review admin reports and shelter applications daily.
- Monitor `activity_log` for failed or unusual workflows.
- Check `public/uploads` storage growth monthly.
- Back up MySQL and uploaded images together.

## Updating visibility

Visibility weights live in `config/app.php` under `visibility.weights`. Keep total weights easy to reason about and document changes before launch.

## Upload care

The upload service creates original-sized optimized images and thumbnails when GD is available. If GD is missing, files are still validated and stored, but resizing falls back to copying.

## Database care

The schema is optimized for the current page workflows. Add migrations for future structural changes instead of editing live databases manually.
