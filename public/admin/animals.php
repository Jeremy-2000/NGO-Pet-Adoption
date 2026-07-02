<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $id = (int) ($_POST['animal_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'available');
        $featured = isset($_POST['is_featured']) ? 1 : 0;

        if (in_array($status, ['available', 'reserved', 'adopted', 'medical_hold'], true)) {
            $pdo->prepare('UPDATE animals SET status = ?, is_featured = ? WHERE id = ?')->execute([$status, $featured, $id]);
            audit_log($pdo, 'animal.moderated', 'animal', $id, ['status' => $status, 'is_featured' => $featured]);
            flash('success', 'Animal listing updated.');
        }

        redirect('/admin/animals.php');
    }

    $animals = $pdo->query(
        "SELECT a.*, s.name AS shelter_name
        FROM animals a
        INNER JOIN shelters s ON s.id = a.shelter_id
        ORDER BY a.created_at DESC, a.id DESC"
    )->fetchAll();
} catch (Throwable) {
    http_response_code(500);
    exit('Animal moderation could not be loaded.');
}

$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Animal Moderation | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/admin/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/admin/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/admin/shelters.php')); ?>">Shelters</a>
        <a class="active" href="<?php echo e(url('/admin/animals.php')); ?>">Animals</a>
        <a href="<?php echo e(url('/admin/reports.php')); ?>">Reports</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Moderation</p><h1>Animal listings</h1></div></header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <section class="card">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Name</th><th>Shelter</th><th>Status</th><th>Engagement</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($animals as $animal) : ?>
                <tr>
                  <td><strong><?php echo e($animal['name']); ?></strong><br><span class="muted"><?php echo e($animal['species']); ?> - <?php echo e($animal['breed'] ?: 'Mixed breed'); ?></span></td>
                  <td><?php echo e($animal['shelter_name']); ?></td>
                  <td><?php echo e(status_label($animal['status'])); ?><?php echo (int) $animal['is_featured'] === 1 ? ' / Featured' : ''; ?></td>
                  <td><?php echo e($animal['views_count']); ?> views<br><?php echo e($animal['favorites_count']); ?> favorites</td>
                  <td>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                      <input type="hidden" name="animal_id" value="<?php echo e($animal['id']); ?>">
                      <select class="input compact-input" name="status">
                        <?php foreach (['available', 'reserved', 'adopted', 'medical_hold'] as $status) : ?>
                          <option value="<?php echo e($status); ?>" <?php echo selected($animal['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
                        <?php endforeach; ?>
                      </select>
                      <label class="inline-check"><input type="checkbox" name="is_featured" value="1" <?php echo checked($animal['is_featured']); ?>> Feature</label>
                      <button type="submit" class="btn secondary small">Save</button>
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
