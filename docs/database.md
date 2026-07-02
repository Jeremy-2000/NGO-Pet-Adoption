# Database Documentation

## ERD

```text
users 1--1 shelters
shelters 1--many animals
animals 1--many animal_images
animals 1--many inquiries
shelters 1--many inquiries
animals 1--many favorites
animals 1--many reports
shelters 1--many reports
animals many--many votes through matchup rows
users 1--many activity_log
```

## Tables

- `users`: authentication, role, account status, last login.
- `shelters`: public profile, contact details, approval status.
- `animals`: listing details, adoption status, personality, medical data, engagement counts.
- `animal_images`: original image and thumbnail paths for each listing.
- `inquiries`: visitor messages to shelters with spam/rate-limit metadata.
- `votes`: `Animal A vs Animal B` matchup votes using hashed visitor identity.
- `favorites`: user or session saved animals.
- `reports`: visitor moderation reports.
- `rate_limits`: rolling action windows for spam-sensitive workflows.
- `activity_log`: audit trail for auth, moderation, listings, inquiries, votes, uploads.

## Indexing Strategy

- Role/status index on `users`.
- Approval and location indexes on `shelters`.
- Status, species/breed, featured/status, shelter/status, and fulltext search indexes on `animals`.
- Animal/sort index on `animal_images`.
- Shelter/status, animal, created, and identity indexes on `inquiries`.
- Matchup/voter unique key and winner/created index on `votes`.
- Session/user/animal indexes on `favorites`.
- Status and target indexes on `reports`.
- Expiry index on `rate_limits`.
- Actor, target, and created indexes on `activity_log`.

## Migration

The canonical schema is `database/schema.sql`. The initial migration marker is `database/migrations/001_initial_schema.sql`, and `database/install.php` creates the database if needed before applying the schema.
