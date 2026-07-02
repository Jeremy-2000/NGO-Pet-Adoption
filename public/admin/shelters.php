<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

$error = '';

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $id = (int) ($_POST['shelter_id'] ?? 0);
        $status = (string) ($_POST['status'] ?? 'pending_review');

        try {
            $shelterRepository->updateStatus($id, $status);
            audit_log($pdo, 'shelter.status_updated', 'shelter', $id, ['status' => $status]);
            flash('success', 'Shelter status updated.');
            redirect('/admin/shelters.php');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    $shelters = $shelterRepository->allForAdmin();
} catch (Throwable) {
    http_response_code(500);
    exit('Shelter moderation could not be loaded.');
}

$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shelter Review | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/admin/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/admin/dashboard.php')); ?>">Dashboard</a>
        <a class="active" href="<?php echo e(url('/admin/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/admin/animals.php')); ?>">Animals</a>
        <a href="<?php echo e(url('/admin/reports.php')); ?>">Reports</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Moderation</p><h1>Shelter applications</h1></div></header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
      <section class="card">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Name</th><th>Email</th><th>Status</th><th>Location</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($shelters as $shelter) : ?>
                <tr>
                  <td><strong><?php echo e($shelter['name']); ?></strong><br><span class="muted"><?php echo e(excerpt($shelter['description'], 90)); ?></span></td>
                  <td><?php echo e($shelter['email']); ?></td>
                  <td><?php echo e(status_label($shelter['status'])); ?></td>
                  <td><?php echo e($shelter['city'] ?: $shelter['country'] ?: 'Not listed'); ?></td>
                  <td>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                      <input type="hidden" name="shelter_id" value="<?php echo e($shelter['id']); ?>">
                      <select class="input compact-input" name="status">
                        <?php foreach (['applied', 'pending_review', 'approved', 'rejected'] as $status) : ?>
                          <option value="<?php echo e($status); ?>" <?php echo selected($shelter['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
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
    </main>
  </div>
</body>
</html>
