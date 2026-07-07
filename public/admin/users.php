<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('admin');

$error = '';

function temporary_password(): string
{
    return 'Reset-' . bin2hex(random_bytes(4)) . '!';
}

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $userId = (int) ($_POST['user_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'reset_password' && $userId > 0) {
            $lookup = $pdo->prepare('SELECT id, name, email FROM users WHERE id = ? LIMIT 1');
            $lookup->execute([$userId]);
            $target = $lookup->fetch();

            if (!$target) {
                $error = 'User not found.';
            } else {
                $temporaryPassword = temporary_password();
                $statement = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
                $statement->execute([password_hash($temporaryPassword, PASSWORD_DEFAULT), $userId]);
                audit_log($pdo, 'user.password_reset', 'user', $userId);
                flash('success', 'Temporary password for ' . $target['email'] . ': ' . $temporaryPassword);
                redirect('/admin/users.php');
            }
        }
    }

    $users = $pdo->query(
        'SELECT u.*, s.name AS shelter_name, s.status AS shelter_status
        FROM users u
        LEFT JOIN shelters s ON s.user_id = u.id
        ORDER BY u.created_at DESC, u.id DESC'
    )->fetchAll();
} catch (Throwable $exception) {
    if ($error === '') {
        $error = $exception->getMessage();
    }
    $users = [];
}

$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Users | <?php echo e(config('app_name')); ?></title>
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
        <a class="active" href="<?php echo e(url('/admin/users.php')); ?>">Users</a>
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
        <div><p class="eyebrow">Identity</p><h1>Users</h1></div>
        <a class="btn secondary" href="<?php echo e(url('/admin/export.php?type=users')); ?>">Export CSV</a>
      </header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

      <section class="card">
        <?php if ($users === []) : ?>
          <div class="empty-state compact-empty"><h2>No users yet.</h2><p class="muted">Registered adopter, shelter, and admin accounts will appear here.</p></div>
        <?php else : ?>
          <div class="table-wrap">
            <table class="table" data-enhanced-table data-table-key="admin-users" data-table-empty="No users match these filters.">
              <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Shelter</th><th>Last login</th><th>Created</th><th data-no-filter="true" data-no-sort="true">Action</th></tr></thead>
              <tbody>
                <?php foreach ($users as $user) : ?>
                  <tr>
                    <td><strong><?php echo e($user['name']); ?></strong><br><span class="muted">#<?php echo e($user['id']); ?></span></td>
                    <td><a href="mailto:<?php echo e($user['email']); ?>"><?php echo e($user['email']); ?></a></td>
                    <td><?php echo e(status_label($user['role'])); ?></td>
                    <td><span class="badge <?php echo e(status_badge_class($user['status'])); ?>"><?php echo e(status_label($user['status'])); ?></span></td>
                    <td><?php echo e($user['shelter_name'] ?: 'None'); ?><?php echo $user['shelter_status'] ? ' - ' . e(status_label($user['shelter_status'])) : ''; ?></td>
                    <td><?php echo e($user['last_login_at'] ?: 'Never'); ?></td>
                    <td><?php echo e($user['created_at']); ?></td>
                    <td class="table-actions"><button class="btn secondary small" type="button" data-open-dialog="user-dialog-<?php echo e($user['id']); ?>">Open</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <?php foreach ($users as $user) : ?>
        <dialog class="app-dialog" id="user-dialog-<?php echo e($user['id']); ?>">
          <div class="dialog-shell">
            <header class="dialog-header">
              <div><p class="eyebrow">User account</p><h2><?php echo e($user['name']); ?></h2></div>
              <button class="dialog-close" type="button" data-close-dialog>Close</button>
            </header>
            <div class="detail-list">
              <div><span>Email</span><strong><?php echo e($user['email']); ?></strong></div>
              <div><span>Role</span><strong><?php echo e(status_label($user['role'])); ?></strong></div>
              <div><span>Status</span><strong><span class="badge <?php echo e(status_badge_class($user['status'])); ?>"><?php echo e(status_label($user['status'])); ?></span></strong></div>
              <div><span>Shelter</span><strong><?php echo e($user['shelter_name'] ?: 'None'); ?></strong></div>
              <div><span>Last login</span><strong><?php echo e($user['last_login_at'] ?: 'Never'); ?></strong></div>
              <div><span>Created</span><strong><?php echo e($user['created_at']); ?></strong></div>
            </div>
            <form method="post" class="dialog-actions" data-confirm="Reset this user's password and show a temporary password?">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <input type="hidden" name="user_id" value="<?php echo e($user['id']); ?>">
              <button class="btn green small" type="submit" name="action" value="reset_password">Reset password</button>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    </main>
  </div>
</body>
</html>
