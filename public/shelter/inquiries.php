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
        $statuses = inquiry_statuses();

        if (isset($_POST['bulk_action'])) {
            $bulkStatus = (string) ($_POST['bulk_status'] ?? '');
            $ids = array_filter(array_map('intval', $_POST['inquiry_ids'] ?? []));

            if ($ids !== [] && in_array($bulkStatus, $statuses, true)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $statement = $pdo->prepare("UPDATE inquiries SET status = ? WHERE shelter_id = ? AND id IN ({$placeholders})");
                $statement->execute(array_merge([$bulkStatus, (int) $shelter['id']], $ids));
                audit_log($pdo, 'inquiry.bulk_status_updated', 'shelter', (int) $shelter['id'], ['status' => $bulkStatus, 'count' => count($ids)]);
                flash('success', 'Selected inquiries updated.');
            } else {
                flash('error', 'Choose inquiries and a valid status before applying a bulk update.');
            }

            redirect('/shelter/inquiries.php');
        }

        $status = (string) ($_POST['status'] ?? 'contacted');
        $id = (int) ($_POST['inquiry_id'] ?? 0);
        $appointmentAt = trim((string) ($_POST['appointment_at'] ?? '')) ?: null;
        $appointmentAt = $appointmentAt ? str_replace('T', ' ', $appointmentAt) : null;
        $notes = trim((string) ($_POST['internal_notes'] ?? '')) ?: null;

        if (in_array($status, $statuses, true)) {
            $lookup = $pdo->prepare('SELECT * FROM inquiries WHERE id = ? AND shelter_id = ? LIMIT 1');
            $lookup->execute([$id, (int) $shelter['id']]);
            $inquiry = $lookup->fetch();
            $statement = $pdo->prepare('UPDATE inquiries SET status = ?, appointment_at = ?, internal_notes = ? WHERE id = ? AND shelter_id = ?');
            $statement->execute([$status, $appointmentAt, $notes, $id, (int) $shelter['id']]);

            if ($inquiry) {
                $pdo->prepare('DELETE FROM appointments WHERE inquiry_id = ?')->execute([$id]);
            }

            if ($inquiry && $appointmentAt !== null) {
                $appointment = $pdo->prepare(
                    'INSERT INTO appointments (inquiry_id, shelter_id, animal_id, user_id, title, appointment_at, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $appointment->execute([
                    $id,
                    (int) $shelter['id'],
                    $inquiry['animal_id'] ? (int) $inquiry['animal_id'] : null,
                    $inquiry['user_id'] ? (int) $inquiry['user_id'] : null,
                    'Inquiry follow-up',
                    $appointmentAt,
                    $notes,
                ]);
            }

            audit_log($pdo, 'inquiry.status_updated', 'inquiry', $id, ['status' => $status, 'appointment_at' => $appointmentAt]);
        }

        redirect('/shelter/inquiries.php');
    }

    $inquiries = $pdo->prepare(
        'SELECT i.*, a.name AS animal_name
        FROM inquiries i
        LEFT JOIN animals a ON a.id = i.animal_id
        WHERE i.shelter_id = ?
        ORDER BY FIELD(i.status, "new", "contacted", "viewing_scheduled", "approved", "declined", "completed", "closed"), i.created_at DESC'
    );
    $inquiries->execute([(int) $shelter['id']]);
    $inquiries = $inquiries->fetchAll();
} catch (Throwable) {
    http_response_code(500);
    exit('Inquiries could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Inquiries | <?php echo e(config('app_name')); ?></title>
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
        <a class="active" href="<?php echo e(url('/shelter/inquiries.php')); ?>">Inquiries</a>
        <a href="<?php echo e(url('/shelter/applications.php')); ?>">Applications</a>
        <a href="<?php echo e(url('/shelter/questions.php')); ?>">Questions</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Shelter portal</p><h1>Inquiries</h1></div></header>
      <?php if ($success = flash('success')) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($error = flash('error')) : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
      <section class="card">
        <?php if ($inquiries === []) : ?>
          <div class="empty-state compact-empty">
            <h2>No inquiries yet.</h2>
            <p class="muted">Questions from public listing pages will appear here.</p>
          </div>
        <?php else : ?>
        <form id="bulk-inquiries-form" method="post" class="bulk-actions">
          <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
          <select class="input compact-input" name="bulk_status">
            <option value="">Bulk status</option>
            <?php foreach (inquiry_statuses() as $status) : ?>
              <option value="<?php echo e($status); ?>"><?php echo e(status_label($status)); ?></option>
            <?php endforeach; ?>
          </select>
          <button class="btn secondary small" type="submit" name="bulk_action" value="status" data-confirm="Update all selected inquiries?">Apply</button>
        </form>
        <div class="table-wrap">
          <table class="table" data-enhanced-table data-table-key="shelter-inquiries" data-table-empty="No inquiries match these filters.">
            <thead><tr><th data-no-filter="true" data-no-sort="true">Select</th><th>From</th><th>Animal</th><th>Message</th><th>Status</th><th>Appointment</th><th data-no-filter="true" data-no-sort="true">Action</th></tr></thead>
            <tbody>
              <?php foreach ($inquiries as $inquiry) : ?>
                <tr>
                  <td><input form="bulk-inquiries-form" type="checkbox" name="inquiry_ids[]" value="<?php echo e($inquiry['id']); ?>" aria-label="Select inquiry from <?php echo e($inquiry['name']); ?>"></td>
                  <td><strong><?php echo e($inquiry['name']); ?></strong><br><a href="mailto:<?php echo e($inquiry['email']); ?>"><?php echo e($inquiry['email']); ?></a></td>
                  <td><?php echo e($inquiry['animal_name'] ?: 'General'); ?></td>
                  <td><?php echo e(excerpt($inquiry['message'], 180)); ?></td>
                  <td><span class="badge <?php echo e(status_badge_class($inquiry['status'])); ?>"><?php echo e(status_label($inquiry['status'])); ?></span></td>
                  <td><?php echo e($inquiry['appointment_at'] ?: 'Not scheduled'); ?></td>
                  <td class="table-actions">
                    <button class="btn secondary small" type="button" data-open-dialog="inquiry-dialog-<?php echo e($inquiry['id']); ?>">Open</button>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                      <input type="hidden" name="inquiry_id" value="<?php echo e($inquiry['id']); ?>">
                      <select class="input compact-input" name="status">
                        <?php foreach (inquiry_statuses() as $status) : ?>
                          <option value="<?php echo e($status); ?>" <?php echo selected($inquiry['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input type="hidden" name="appointment_at" value="<?php echo e($inquiry['appointment_at'] ?? ''); ?>">
                      <input type="hidden" name="internal_notes" value="<?php echo e($inquiry['internal_notes'] ?? ''); ?>">
                      <button class="btn secondary small" type="submit">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </section>

      <?php foreach ($inquiries as $inquiry) : ?>
        <dialog class="app-dialog" id="inquiry-dialog-<?php echo e($inquiry['id']); ?>">
          <div class="dialog-shell">
            <header class="dialog-header">
              <div>
                <p class="eyebrow">Adoption inquiry</p>
                <h2><?php echo e($inquiry['animal_name'] ?: 'General inquiry'); ?></h2>
              </div>
              <button class="dialog-close" type="button" data-close-dialog>Close</button>
            </header>
            <div class="detail-list">
              <div><span>Name</span><strong><?php echo e($inquiry['name']); ?></strong></div>
              <div><span>Email</span><strong><a href="mailto:<?php echo e($inquiry['email']); ?>"><?php echo e($inquiry['email']); ?></a></strong></div>
              <div><span>Phone</span><strong><?php echo e($inquiry['phone'] ?: 'Not listed'); ?></strong></div>
              <div><span>Status</span><strong><span class="badge <?php echo e(status_badge_class($inquiry['status'])); ?>"><?php echo e(status_label($inquiry['status'])); ?></span></strong></div>
              <div><span>Submitted</span><strong><?php echo e($inquiry['created_at']); ?></strong></div>
              <div><span>Appointment</span><strong><?php echo e($inquiry['appointment_at'] ?: 'Not scheduled'); ?></strong></div>
            </div>
            <section>
              <h3>Message</h3>
              <p class="dialog-copy"><?php echo nl2br(e($inquiry['message'])); ?></p>
            </section>
            <form method="post" class="form">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <input type="hidden" name="inquiry_id" value="<?php echo e($inquiry['id']); ?>">
              <label><span>Status</span><select class="input" name="status">
                <?php foreach (inquiry_statuses() as $status) : ?>
                  <option value="<?php echo e($status); ?>" <?php echo selected($inquiry['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
                <?php endforeach; ?>
              </select></label>
              <label><span>Appointment</span><input class="input" type="datetime-local" name="appointment_at" value="<?php echo e($inquiry['appointment_at'] ? str_replace(' ', 'T', substr((string) $inquiry['appointment_at'], 0, 16)) : ''); ?>"></label>
              <label><span>Internal notes</span><textarea class="input" name="internal_notes" rows="3"><?php echo e($inquiry['internal_notes'] ?? ''); ?></textarea></label>
              <button class="btn green small" type="submit">Save status</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    </main>
  </div>
</body>
</html>
