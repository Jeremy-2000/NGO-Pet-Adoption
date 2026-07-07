<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

try {
    $pdo = db();
    $stats = [
        'Users' => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'Shelters' => (int) $pdo->query('SELECT COUNT(*) FROM shelters')->fetchColumn(),
        'Pending review' => (int) $pdo->query("SELECT COUNT(*) FROM shelters WHERE status IN ('applied','pending_review')")->fetchColumn(),
        'Animals' => (int) $pdo->query('SELECT COUNT(*) FROM animals')->fetchColumn(),
        'Open reports' => (int) $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'open'")->fetchColumn(),
        'Inquiries' => (int) $pdo->query('SELECT COUNT(*) FROM inquiries')->fetchColumn(),
        'Applications' => (int) $pdo->query('SELECT COUNT(*) FROM adoption_applications')->fetchColumn(),
        'Votes' => (int) $pdo->query('SELECT COUNT(*) FROM votes')->fetchColumn(),
    ];
    $pendingShelters = $pdo->query(
        "SELECT s.*, u.email
        FROM shelters s
        LEFT JOIN users u ON u.id = s.user_id
        WHERE s.status IN ('applied','pending_review')
        ORDER BY s.created_at DESC
        LIMIT 6"
    )->fetchAll();
    $recentActivity = $pdo->query('SELECT * FROM activity_log ORDER BY created_at DESC LIMIT 10')->fetchAll();
    $weights = config('visibility.weights', []);
} catch (Throwable) {
    http_response_code(500);
    exit('Admin dashboard could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/admin/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a class="active" href="<?php echo e(url('/admin/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/admin/search.php')); ?>">Search</a>
        <a href="<?php echo e(url('/admin/users.php')); ?>">Users</a>
        <a href="<?php echo e(url('/admin/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/admin/animals.php')); ?>">Animals</a>
        <a href="<?php echo e(url('/admin/reports.php')); ?>">Reports</a>
        <a href="<?php echo e(url('/admin/activity.php')); ?>">Activity</a>
        <a href="<?php echo e(url('/admin/taxonomy.php')); ?>">Taxonomy</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header">
        <div>
          <p class="eyebrow">Operations</p>
          <h1>Admin dashboard</h1>
          <p class="muted">Approve shelters, moderate listings, review reports, and monitor platform health.</p>
        </div>
      </header>

      <section class="stats-grid">
        <?php foreach ($stats as $label => $value) : ?>
          <article class="card stat-card">
            <span class="muted"><?php echo e($label); ?></span>
            <b><?php echo e(number_format($value)); ?></b>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="card">
        <form class="searchbox admin-search" method="get" action="<?php echo e(url('/admin/search.php')); ?>">
          <label class="field"><span>Global search</span><input name="q" placeholder="Name, email, listing, application"></label>
          <button class="btn green" type="submit">Search</button>
        </form>
        <div class="button-row export-row">
          <?php foreach (['users', 'shelters', 'animals', 'inquiries', 'applications', 'reports'] as $type) : ?>
            <a class="btn secondary small" href="<?php echo e(url('/admin/export.php?type=' . $type)); ?>">Export <?php echo e($type); ?></a>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="grid two-up">
        <article class="card">
          <h2>Shelter approval queue</h2>
          <ul class="list">
            <?php foreach ($pendingShelters as $shelter) : ?>
              <li>
                <strong><?php echo e($shelter['name']); ?></strong>
                <span class="muted"><?php echo e($shelter['email']); ?> - <span class="badge <?php echo e(status_badge_class($shelter['status'])); ?>"><?php echo e(status_label($shelter['status'])); ?></span></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <a class="btn secondary small" href="<?php echo e(url('/admin/shelters.php')); ?>">Review shelters</a>
        </article>
        <article class="card">
          <h2>Visibility weights</h2>
          <div class="weight-list">
            <?php foreach ($weights as $key => $value) : ?>
              <div><span><?php echo e(status_label($key)); ?></span><strong><?php echo e((string) $value); ?></strong></div>
            <?php endforeach; ?>
          </div>
          <p class="muted">Weights are stored in configuration so the scoring service stays reusable and auditable.</p>
        </article>
      </section>

      <section class="card">
        <h2>Recent activity</h2>
        <div class="table-wrap">
          <table class="table" data-enhanced-table data-table-key="admin-dashboard-activity" data-table-empty="No activity matches these filters.">
            <thead><tr><th>Action</th><th>Target</th><th>When</th></tr></thead>
            <tbody>
              <?php foreach ($recentActivity as $activity) : ?>
                <tr>
                  <td><?php echo e($activity['action']); ?></td>
                  <td><?php echo e($activity['target_type']); ?> #<?php echo e($activity['target_id']); ?></td>
                  <td><?php echo e($activity['created_at']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
