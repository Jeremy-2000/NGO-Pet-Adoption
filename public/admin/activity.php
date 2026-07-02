<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

try {
    $pdo = db();
    $activity = $pdo->query(
        'SELECT al.*, u.name AS actor_name, u.email AS actor_email
        FROM activity_log al
        LEFT JOIN users u ON u.id = al.actor_id
        ORDER BY al.created_at DESC
        LIMIT 300'
    )->fetchAll();
} catch (Throwable) {
    http_response_code(500);
    exit('Activity log could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Audit Log | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/admin/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/admin/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/admin/search.php')); ?>">Search</a>
        <a href="<?php echo e(url('/admin/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/admin/animals.php')); ?>">Animals</a>
        <a href="<?php echo e(url('/admin/reports.php')); ?>">Reports</a>
        <a class="active" href="<?php echo e(url('/admin/activity.php')); ?>">Activity</a>
        <a href="<?php echo e(url('/admin/taxonomy.php')); ?>">Taxonomy</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header">
        <div><p class="eyebrow">Audit trail</p><h1>Activity log</h1></div>
        <a class="btn secondary" href="<?php echo e(url('/admin/export.php?type=reports')); ?>">Export reports</a>
      </header>
      <section class="card">
        <?php if ($activity === []) : ?>
          <div class="empty-state compact-empty"><h2>No activity yet.</h2><p class="muted">Platform actions will appear here as they happen.</p></div>
        <?php else : ?>
          <div class="table-wrap">
            <table class="table" data-enhanced-table data-table-key="admin-activity" data-table-empty="No activity matches these filters.">
              <thead><tr><th>Action</th><th>Actor</th><th>Target</th><th>Details</th><th>When</th></tr></thead>
              <tbody>
                <?php foreach ($activity as $row) : ?>
                  <tr>
                    <td><?php echo e($row['action']); ?></td>
                    <td><?php echo e($row['actor_name'] ?: 'System'); ?><br><span class="muted"><?php echo e($row['actor_email'] ?: 'No account'); ?></span></td>
                    <td><?php echo e($row['target_type']); ?> #<?php echo e($row['target_id']); ?></td>
                    <td><?php echo e(excerpt($row['details'], 120)); ?></td>
                    <td><?php echo e($row['created_at']); ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>
    </main>
  </div>
</body>
</html>
