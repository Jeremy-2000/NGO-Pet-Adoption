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
        $status = (string) ($_POST['status'] ?? 'reviewed');
        $id = (int) ($_POST['inquiry_id'] ?? 0);

        if (in_array($status, ['new', 'reviewed', 'closed'], true)) {
            $statement = $pdo->prepare('UPDATE inquiries SET status = ? WHERE id = ? AND shelter_id = ?');
            $statement->execute([$status, $id, (int) $shelter['id']]);
            audit_log($pdo, 'inquiry.status_updated', 'inquiry', $id, ['status' => $status]);
        }

        redirect('/shelter/inquiries.php');
    }

    $inquiries = $pdo->prepare(
        'SELECT i.*, a.name AS animal_name
        FROM inquiries i
        LEFT JOIN animals a ON a.id = i.animal_id
        WHERE i.shelter_id = ?
        ORDER BY FIELD(i.status, "new", "reviewed", "closed"), i.created_at DESC'
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
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Shelter portal</p><h1>Inquiries</h1></div></header>
      <section class="card">
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>From</th><th>Animal</th><th>Message</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($inquiries as $inquiry) : ?>
                <tr>
                  <td><strong><?php echo e($inquiry['name']); ?></strong><br><a href="mailto:<?php echo e($inquiry['email']); ?>"><?php echo e($inquiry['email']); ?></a></td>
                  <td><?php echo e($inquiry['animal_name'] ?: 'General'); ?></td>
                  <td><?php echo e(excerpt($inquiry['message'], 180)); ?></td>
                  <td><?php echo e(status_label($inquiry['status'])); ?></td>
                  <td>
                    <form method="post" class="inline-form">
                      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                      <input type="hidden" name="inquiry_id" value="<?php echo e($inquiry['id']); ?>">
                      <select class="input compact-input" name="status">
                        <?php foreach (['new', 'reviewed', 'closed'] as $status) : ?>
                          <option value="<?php echo e($status); ?>" <?php echo selected($inquiry['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
                        <?php endforeach; ?>
                      </select>
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
