<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

$types = ['species', 'breed', 'size', 'animal_status', 'application_status'];
$error = '';

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = (string) ($_POST['action'] ?? 'save');
        $type = (string) ($_POST['type'] ?? '');

        if (!in_array($type, $types, true)) {
            $error = 'Invalid taxonomy type.';
        } elseif ($action === 'save') {
            $id = (int) ($_POST['taxonomy_id'] ?? 0);
            $value = substr(trim((string) ($_POST['value'] ?? '')), 0, 120);
            $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 100));
            $isActive = !empty($_POST['is_active']) ? 1 : 0;

            if ($value === '') {
                $error = 'Value is required.';
            } elseif ($id > 0) {
                $statement = $pdo->prepare('UPDATE taxonomies SET type = ?, value = ?, sort_order = ?, is_active = ? WHERE id = ?');
                $statement->execute([$type, $value, $sortOrder, $isActive, $id]);
                flash('success', 'Taxonomy value updated.');
                redirect('/admin/taxonomy.php');
            } else {
                $statement = $pdo->prepare('INSERT INTO taxonomies (type, value, sort_order, is_active) VALUES (?, ?, ?, ?)');
                $statement->execute([$type, $value, $sortOrder, $isActive]);
                flash('success', 'Taxonomy value added.');
                redirect('/admin/taxonomy.php');
            }
        }
    }

    $rows = $pdo->query('SELECT * FROM taxonomies ORDER BY type ASC, sort_order ASC, value ASC')->fetchAll();
} catch (Throwable $exception) {
    if ($error === '') {
        $error = $exception->getMessage();
    }
    $rows = [];
}

$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Taxonomy | <?php echo e(config('app_name')); ?></title>
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
        <a href="<?php echo e(url('/admin/users.php')); ?>">Users</a>
        <a href="<?php echo e(url('/admin/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/admin/animals.php')); ?>">Animals</a>
        <a href="<?php echo e(url('/admin/reports.php')); ?>">Reports</a>
        <a href="<?php echo e(url('/admin/activity.php')); ?>">Activity</a>
        <a class="active" href="<?php echo e(url('/admin/taxonomy.php')); ?>">Taxonomy</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Configuration</p><h1>Dropdown values</h1></div></header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

      <section class="grid two-up">
        <article class="card">
          <h2>Add value</h2>
          <form method="post" class="form">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
            <input type="hidden" name="action" value="save">
            <label><span>Type</span><select class="input" name="type">
              <?php foreach ($types as $type) : ?>
                <option value="<?php echo e($type); ?>"><?php echo e(status_label($type)); ?></option>
              <?php endforeach; ?>
            </select></label>
            <label><span>Value</span><input class="input" name="value" required></label>
            <label><span>Sort order</span><input class="input" type="number" min="0" name="sort_order" value="100"></label>
            <label class="inline-check"><input type="checkbox" name="is_active" value="1" checked> Active</label>
            <button class="btn green" type="submit">Add value</button>
          </form>
        </article>
        <article class="card">
          <h2>Used by forms</h2>
          <p class="muted">Species and size feed the shelter listing editor and adopter matching questionnaire. Status values are visible here for admin consistency.</p>
        </article>
      </section>

      <section class="card">
        <h2>Existing values</h2>
        <div class="table-wrap">
          <table class="table" data-enhanced-table data-table-key="admin-taxonomy" data-table-empty="No taxonomy values match these filters.">
            <thead><tr><th>Type</th><th>Value</th><th>Order</th><th>Status</th><th data-no-filter="true" data-no-sort="true">Action</th></tr></thead>
            <tbody>
              <?php foreach ($rows as $row) : ?>
                <tr>
                  <td><?php echo e(status_label($row['type'])); ?></td>
                  <td><strong><?php echo e($row['value']); ?></strong></td>
                  <td><?php echo e($row['sort_order']); ?></td>
                  <td><span class="badge <?php echo (int) $row['is_active'] === 1 ? 'approved' : 'archived'; ?>"><?php echo (int) $row['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                  <td class="table-actions">
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                      <input type="hidden" name="action" value="save">
                      <input type="hidden" name="taxonomy_id" value="<?php echo e($row['id']); ?>">
                      <select class="input compact-input" name="type">
                        <?php foreach ($types as $type) : ?>
                          <option value="<?php echo e($type); ?>" <?php echo selected($row['type'], $type); ?>><?php echo e(status_label($type)); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <input class="input compact-input" name="value" value="<?php echo e($row['value']); ?>" required>
                      <input class="input compact-input number-input" type="number" min="0" name="sort_order" value="<?php echo e($row['sort_order']); ?>">
                      <label class="inline-check"><input type="checkbox" name="is_active" value="1" <?php echo checked($row['is_active']); ?>> Active</label>
                      <button class="btn secondary small" type="submit">Save</button>
                    </form>
                  </td>
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
