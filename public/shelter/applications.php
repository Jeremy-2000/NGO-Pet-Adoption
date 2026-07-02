<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('shelter');

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);
    $shelter = $shelterRepository->findByUserId((int) currentUser()['id']);

    if (!$shelter) {
        http_response_code(404);
        exit('Shelter profile not found.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $statuses = application_statuses();

        if (isset($_POST['bulk_action'])) {
            $bulkStatus = (string) ($_POST['bulk_status'] ?? '');
            $ids = array_filter(array_map('intval', $_POST['application_ids'] ?? []));

            if ($ids !== [] && in_array($bulkStatus, $statuses, true)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $statement = $pdo->prepare("UPDATE adoption_applications SET status = ? WHERE shelter_id = ? AND id IN ({$placeholders})");
                $statement->execute(array_merge([$bulkStatus, (int) $shelter['id']], $ids));
                audit_log($pdo, 'application.bulk_status_updated', 'shelter', (int) $shelter['id'], ['status' => $bulkStatus, 'count' => count($ids)]);
                flash('success', 'Selected applications updated.');
            } else {
                flash('error', 'Choose applications and a valid status before applying a bulk update.');
            }

            redirect('/shelter/applications.php');
        }

        $applicationId = (int) ($_POST['application_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'reviewing');
        $appointmentAt = trim((string) ($_POST['appointment_at'] ?? '')) ?: null;
        $appointmentAt = $appointmentAt ? str_replace('T', ' ', $appointmentAt) : null;
        $outcomeNotes = trim((string) ($_POST['outcome_notes'] ?? '')) ?: null;

        if (in_array($status, $statuses, true)) {
            $lookup = $pdo->prepare('SELECT * FROM adoption_applications WHERE id = ? AND shelter_id = ? LIMIT 1');
            $lookup->execute([$applicationId, (int) $shelter['id']]);
            $application = $lookup->fetch();

            if ($application) {
                $statement = $pdo->prepare('UPDATE adoption_applications SET status = ?, appointment_at = ?, outcome_notes = ? WHERE id = ? AND shelter_id = ?');
                $statement->execute([$status, $appointmentAt, $outcomeNotes, $applicationId, (int) $shelter['id']]);
                $pdo->prepare('DELETE FROM appointments WHERE adoption_application_id = ?')->execute([$applicationId]);

                if ($appointmentAt !== null) {
                    $appointment = $pdo->prepare(
                        'INSERT INTO appointments (adoption_application_id, shelter_id, animal_id, user_id, title, appointment_at, notes)
                        VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    $appointment->execute([
                        $applicationId,
                        (int) $shelter['id'],
                        (int) $application['animal_id'],
                        (int) $application['user_id'],
                        'Meet-and-greet',
                        $appointmentAt,
                        $outcomeNotes,
                    ]);
                }

                if ($status === 'completed') {
                    $pdo->prepare("UPDATE animals SET status = 'adopted' WHERE id = ? AND shelter_id = ?")->execute([(int) $application['animal_id'], (int) $shelter['id']]);
                }

                audit_log($pdo, 'application.status_updated', 'application', $applicationId, ['status' => $status, 'appointment_at' => $appointmentAt]);
                flash('success', 'Application updated.');
            }
        }

        redirect('/shelter/applications.php');
    }

    $applications = $pdo->prepare(
        'SELECT aa.*, a.name AS animal_name, a.status AS animal_status, u.email AS account_email
        FROM adoption_applications aa
        INNER JOIN animals a ON a.id = aa.animal_id
        INNER JOIN users u ON u.id = aa.user_id
        WHERE aa.shelter_id = ?
        ORDER BY FIELD(aa.status, "new", "reviewing", "contacted", "viewing_scheduled", "approved", "declined", "completed", "cancelled"), aa.created_at DESC'
    );
    $applications->execute([(int) $shelter['id']]);
    $applications = $applications->fetchAll();
} catch (Throwable) {
    http_response_code(500);
    exit('Applications could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Applications | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/shelter/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/shelter/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/shelter/profile.php')); ?>">Profile</a>
        <a href="<?php echo e(url('/shelter/listings.php')); ?>">Listings</a>
        <a href="<?php echo e(url('/shelter/inquiries.php')); ?>">Inquiries</a>
        <a class="active" href="<?php echo e(url('/shelter/applications.php')); ?>">Applications</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Shelter portal</p><h1>Adoption applications</h1></div></header>
      <?php if ($success = flash('success')) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($error = flash('error')) : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

      <section class="card">
        <?php if ($applications === []) : ?>
          <div class="empty-state compact-empty">
            <h2>No adoption applications yet.</h2>
            <p class="muted">Applications submitted by adopter accounts will appear here.</p>
          </div>
        <?php else : ?>
          <form id="bulk-applications-form" method="post" class="bulk-actions">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
            <select class="input compact-input" name="bulk_status">
              <option value="">Bulk status</option>
              <?php foreach (application_statuses() as $status) : ?>
                <option value="<?php echo e($status); ?>"><?php echo e(status_label($status)); ?></option>
              <?php endforeach; ?>
            </select>
            <button class="btn secondary small" type="submit" name="bulk_action" value="status" data-confirm="Update all selected applications?">Apply</button>
          </form>
          <div class="table-wrap">
            <table class="table" data-enhanced-table data-table-key="shelter-applications" data-table-empty="No applications match these filters.">
              <thead><tr><th data-no-filter="true" data-no-sort="true">Select</th><th>Applicant</th><th>Animal</th><th>Status</th><th>Appointment</th><th>Submitted</th><th data-no-filter="true" data-no-sort="true">Action</th></tr></thead>
              <tbody>
                <?php foreach ($applications as $application) : ?>
                  <tr>
                    <td><input form="bulk-applications-form" type="checkbox" name="application_ids[]" value="<?php echo e($application['id']); ?>" aria-label="Select application from <?php echo e($application['name']); ?>"></td>
                    <td><strong><?php echo e($application['name']); ?></strong><br><a href="mailto:<?php echo e($application['email']); ?>"><?php echo e($application['email']); ?></a></td>
                    <td><?php echo e($application['animal_name']); ?><br><span class="muted"><?php echo e(status_label($application['animal_status'])); ?></span></td>
                    <td><span class="badge <?php echo e(status_badge_class($application['status'])); ?>"><?php echo e(status_label($application['status'])); ?></span></td>
                    <td><?php echo e($application['appointment_at'] ?: 'Not scheduled'); ?></td>
                    <td><?php echo e($application['created_at']); ?></td>
                    <td class="table-actions"><button class="btn secondary small" type="button" data-open-dialog="application-dialog-<?php echo e($application['id']); ?>">Open</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <?php foreach ($applications as $application) : ?>
        <dialog class="app-dialog" id="application-dialog-<?php echo e($application['id']); ?>">
          <div class="dialog-shell">
            <header class="dialog-header">
              <div>
                <p class="eyebrow">Adoption application</p>
                <h2><?php echo e($application['animal_name']); ?></h2>
              </div>
              <button class="dialog-close" type="button" data-close-dialog>Close</button>
            </header>
            <div class="detail-list">
              <div><span>Applicant</span><strong><?php echo e($application['name']); ?></strong></div>
              <div><span>Email</span><strong><a href="mailto:<?php echo e($application['email']); ?>"><?php echo e($application['email']); ?></a></strong></div>
              <div><span>Phone</span><strong><?php echo e($application['phone'] ?: 'Not listed'); ?></strong></div>
              <div><span>Status</span><strong><span class="badge <?php echo e(status_badge_class($application['status'])); ?>"><?php echo e(status_label($application['status'])); ?></span></strong></div>
              <div><span>Home</span><strong><?php echo e($application['home_type'] ?: 'Not listed'); ?></strong></div>
              <div><span>Lifestyle</span><strong><?php echo e($application['lifestyle'] ?: 'Not listed'); ?></strong></div>
              <div><span>Children</span><strong><?php echo e(bool_label($application['has_children'])); ?></strong></div>
              <div><span>Other pets</span><strong><?php echo e(bool_label($application['has_pets'])); ?></strong></div>
              <div><span>Appointment</span><strong><?php echo e($application['appointment_at'] ?: 'Not scheduled'); ?></strong></div>
              <div><span>Submitted</span><strong><?php echo e($application['created_at']); ?></strong></div>
            </div>
            <section>
              <h3>Message</h3>
              <p class="dialog-copy"><?php echo nl2br(e($application['message'])); ?></p>
            </section>
            <?php if ($application['experience']) : ?>
              <section>
                <h3>Experience</h3>
                <p class="dialog-copy"><?php echo nl2br(e($application['experience'])); ?></p>
              </section>
            <?php endif; ?>
            <form method="post" class="form">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <input type="hidden" name="application_id" value="<?php echo e($application['id']); ?>">
              <label><span>Status</span><select class="input" name="status">
                <?php foreach (application_statuses() as $status) : ?>
                  <option value="<?php echo e($status); ?>" <?php echo selected($application['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
                <?php endforeach; ?>
              </select></label>
              <label><span>Appointment</span><input class="input" type="datetime-local" name="appointment_at" value="<?php echo e($application['appointment_at'] ? str_replace(' ', 'T', substr((string) $application['appointment_at'], 0, 16)) : ''); ?>"></label>
              <label><span>Outcome notes</span><textarea class="input" name="outcome_notes" rows="3"><?php echo e($application['outcome_notes'] ?? ''); ?></textarea></label>
              <button class="btn green small" type="submit">Save application</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    </main>
  </div>
</body>
</html>
