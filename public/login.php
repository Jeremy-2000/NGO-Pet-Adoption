<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$error = '';
$success = flash('success');

try {
    $pdo = db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && ($user['status'] ?? '') !== 'suspended' && password_verify($password, (string) $user['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'name' => (string) $user['name'],
                'email' => (string) $user['email'],
                'role' => (string) $user['role'],
                'status' => (string) $user['status'],
            ];
            $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int) $user['id']]);
            audit_log($pdo, 'auth.login', 'user', (int) $user['id']);

            $redirect = match ((string) $user['role']) {
                'admin' => '/admin/dashboard.php',
                'shelter' => '/shelter/dashboard.php',
                default => '/account.php',
            };
            redirect($redirect);
        }

        $error = 'Invalid login details.';
    }
} catch (Throwable) {
    http_response_code(500);
    exit('Database connection failed.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign in | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body class="auth-shell">
  <main class="auth-card">
    <a class="brand auth-brand" href="<?php echo e(url('/')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
    <h1>Welcome back</h1>
    <p class="muted">Access your dashboard and manage adoption activity.</p>
    <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
    <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
    <form method="post" class="form">
      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
      <label>
        <span>Email</span>
        <input class="input" type="email" name="email" autocomplete="email" required>
      </label>
      <label>
        <span>Password</span>
        <input class="input" type="password" name="password" autocomplete="current-password" required>
      </label>
      <button type="submit" class="btn green">Sign in</button>
    </form>
    <p class="muted">New here? <a href="<?php echo e(url('/register.php')); ?>">Create an adopter account</a> or <a href="<?php echo e(url('/register.php?type=shelter')); ?>">apply as a shelter</a>.</p>
  </main>
</body>
</html>
