<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

$q = trim((string) ($_GET['q'] ?? ''));
$results = [];

try {
    $pdo = db();

    if ($q !== '') {
        $needle = '%' . $q . '%';
        $queries = [
            'Shelters' => ["SELECT id, name AS title, status, created_at, 'Shelter application' AS kind FROM shelters WHERE name LIKE ? OR contact_email LIKE ? OR city LIKE ? ORDER BY created_at DESC LIMIT 20", [$needle, $needle, $needle]],
            'Animals' => ["SELECT id, name AS title, status, created_at, 'Animal listing' AS kind FROM animals WHERE name LIKE ? OR species LIKE ? OR breed LIKE ? ORDER BY created_at DESC LIMIT 20", [$needle, $needle, $needle]],
            'Inquiries' => ["SELECT id, name AS title, status, created_at, 'Inquiry' AS kind FROM inquiries WHERE name LIKE ? OR email LIKE ? OR message LIKE ? ORDER BY created_at DESC LIMIT 20", [$needle, $needle, $needle]],
            'Applications' => ["SELECT id, name AS title, status, created_at, 'Adoption application' AS kind FROM adoption_applications WHERE name LIKE ? OR email LIKE ? OR message LIKE ? ORDER BY created_at DESC LIMIT 20", [$needle, $needle, $needle]],
            'Reports' => ["SELECT id, reporter_name AS title, status, created_at, 'Report' AS kind FROM reports WHERE reporter_name LIKE ? OR reporter_email LIKE ? OR reason LIKE ? ORDER BY created_at DESC LIMIT 20", [$needle, $needle, $needle]],
        ];

        foreach ($queries as $label => [$sql, $params]) {
            $statement = $pdo->prepare($sql);
            $statement->execute($params);
            $results[$label] = $statement->fetchAll();
        }
    }
} catch (Throwable) {
    http_response_code(500);
    exit('Admin search could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Search | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/admin/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/admin/dashboard.php')); ?>">Dashboard</a>
        <a class="active" href="<?php echo e(url('/admin/search.php')); ?>">Search</a>
        <a href="<?php echo e(url('/admin/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/admin/animals.php')); ?>">Animals</a>
        <a href="<?php echo e(url('/admin/reports.php')); ?>">Reports</a>
        <a href="<?php echo e(url('/admin/activity.php')); ?>">Activity</a>
        <a href="<?php echo e(url('/admin/taxonomy.php')); ?>">Taxonomy</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Operations</p><h1>Global search</h1></div></header>
      <section class="card">
        <form class="searchbox admin-search" method="get">
          <label class="field"><span>Search</span><input name="q" value="<?php echo e($q); ?>" placeholder="Name, email, listing, report text"></label>
          <button class="btn green" type="submit">Search</button>
        </form>
      </section>

      <?php if ($q === '') : ?>
        <section class="empty-state"><h2>Search across the platform.</h2><p class="muted">Results are grouped by shelter, listing, inquiry, application, and report.</p></section>
      <?php else : ?>
        <?php foreach ($results as $group => $items) : ?>
          <section class="card">
            <h2><?php echo e($group); ?></h2>
            <?php if ($items === []) : ?>
              <div class="empty-state compact-empty"><h3>No <?php echo e(strtolower($group)); ?> found.</h3></div>
            <?php else : ?>
              <div class="table-wrap">
                <table class="table" data-enhanced-table data-table-key="admin-search-<?php echo e(strtolower($group)); ?>" data-table-empty="No search rows match these filters.">
                  <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>Created</th></tr></thead>
                  <tbody>
                    <?php foreach ($items as $item) : ?>
                      <tr>
                        <td><strong><?php echo e($item['title']); ?></strong><br><span class="muted">#<?php echo e($item['id']); ?></span></td>
                        <td><?php echo e($item['kind']); ?></td>
                        <td><span class="badge <?php echo e(status_badge_class($item['status'])); ?>"><?php echo e(status_label($item['status'])); ?></span></td>
                        <td><?php echo e($item['created_at']); ?></td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </section>
        <?php endforeach; ?>
      <?php endif; ?>
    </main>
  </div>
</body>
</html>
