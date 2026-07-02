<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $id = (int) ($_POST['report_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'reviewed');

        if (in_array($status, ['open', 'reviewed', 'resolved'], true)) {
            $pdo->prepare('UPDATE reports SET status = ? WHERE id = ?')->execute([$status, $id]);
            audit_log($pdo, 'report.status_updated', 'report', $id, ['status' => $status]);
            flash('success', 'Report updated.');
        }

        redirect('/admin/reports.php');
    }

    $reports = $pdo->query(
        'SELECT r.*, a.name AS animal_name, s.name AS shelter_name
        FROM reports r
        LEFT JOIN animals a ON a.id = r.animal_id
        LEFT JOIN shelters s ON s.id = r.shelter_id
        ORDER BY FIELD(r.status, "open", "reviewed", "resolved"), r.created_at DESC'
    )->fetchAll();
} catch (Throwable) {
    http_response_code(500);
    exit('Reports could not be loaded.');
}

$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reports | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/admin/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/admin/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/admin/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/admin/animals.php')); ?>">Animals</a>
        <a class="active" href="<?php echo e(url('/admin/reports.php')); ?>">Reports</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Moderation</p><h1>Reports</h1></div></header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <section class="card">
        <div class="table-wrap">
          <table class="table" data-enhanced-table data-table-empty="No reports match these filters.">
            <thead><tr><th>Reporter</th><th>Target</th><th>Reason</th><th>Status</th><th data-no-filter="true" data-no-sort="true">Action</th></tr></thead>
            <tbody>
              <?php foreach ($reports as $report) : ?>
                <tr>
                  <td><strong><?php echo e($report['reporter_name']); ?></strong><br><a href="mailto:<?php echo e($report['reporter_email']); ?>"><?php echo e($report['reporter_email']); ?></a></td>
                  <td><?php echo e($report['animal_name'] ?: 'General'); ?><br><span class="muted"><?php echo e($report['shelter_name'] ?: 'No shelter'); ?></span></td>
                  <td><?php echo e(excerpt($report['reason'], 180)); ?></td>
                  <td><?php echo e(status_label($report['status'])); ?></td>
                  <td class="table-actions">
                    <button class="btn secondary small" type="button" data-open-dialog="report-dialog-<?php echo e($report['id']); ?>">Open</button>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                      <input type="hidden" name="report_id" value="<?php echo e($report['id']); ?>">
                      <select class="input compact-input" name="status">
                        <?php foreach (['open', 'reviewed', 'resolved'] as $status) : ?>
                          <option value="<?php echo e($status); ?>" <?php echo selected($report['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <button type="submit" class="btn secondary small">Save</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>

      <?php foreach ($reports as $report) : ?>
        <dialog class="app-dialog" id="report-dialog-<?php echo e($report['id']); ?>">
          <div class="dialog-shell">
            <header class="dialog-header">
              <div>
                <p class="eyebrow">Moderation report</p>
                <h2><?php echo e($report['animal_name'] ?: 'General report'); ?></h2>
              </div>
              <button class="dialog-close" type="button" data-close-dialog>Close</button>
            </header>
            <div class="detail-list">
              <div><span>Reporter</span><strong><?php echo e($report['reporter_name']); ?></strong></div>
              <div><span>Email</span><strong><a href="mailto:<?php echo e($report['reporter_email']); ?>"><?php echo e($report['reporter_email']); ?></a></strong></div>
              <div><span>Shelter</span><strong><?php echo e($report['shelter_name'] ?: 'No shelter'); ?></strong></div>
              <div><span>Status</span><strong><?php echo e(status_label($report['status'])); ?></strong></div>
              <div><span>Submitted</span><strong><?php echo e($report['created_at']); ?></strong></div>
            </div>
            <section>
              <h3>Reason</h3>
              <p class="dialog-copy"><?php echo nl2br(e($report['reason'])); ?></p>
            </section>
            <form method="post" class="inline-form">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <input type="hidden" name="report_id" value="<?php echo e($report['id']); ?>">
              <select class="input compact-input" name="status">
                <?php foreach (['open', 'reviewed', 'resolved'] as $status) : ?>
                  <option value="<?php echo e($status); ?>" <?php echo selected($report['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
                <?php endforeach; ?>
              </select>
              <button type="submit" class="btn green small">Save status</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    </main>
  </div>
</body>
</html>
