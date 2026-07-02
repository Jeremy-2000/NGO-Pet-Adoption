<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$error = '';

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $name = substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $shelterName = substr(trim((string) ($_POST['shelter_name'] ?? '')), 0, 180);

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || $shelterName === '') {
            $error = 'Please complete all required fields with a valid email and a 10+ character password.';
        } else {
            $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
            $exists->execute([$email]);

            if ($exists->fetch()) {
                $error = 'An account already exists for that email address.';
            } else {
                $pdo->beginTransaction();

                try {
                    $userStmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
                    $userStmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), 'shelter', 'pending']);
                    $userId = (int) $pdo->lastInsertId();
                    $slug = $shelterRepository->uniqueSlug($shelterName);
                    $shelterStmt = $pdo->prepare(
                        'INSERT INTO shelters
                            (user_id, name, slug, description, contact_email, contact_phone, city, region, country, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    $shelterStmt->execute([
                        $userId,
                        $shelterName,
                        $slug,
                        trim((string) ($_POST['description'] ?? '')) ?: null,
                        $email,
                        substr(trim((string) ($_POST['contact_phone'] ?? '')), 0, 50) ?: null,
                        substr(trim((string) ($_POST['city'] ?? '')), 0, 120) ?: null,
                        substr(trim((string) ($_POST['region'] ?? '')), 0, 120) ?: null,
                        substr(trim((string) ($_POST['country'] ?? '')), 0, 120) ?: null,
                        'applied',
                    ]);
                    audit_log($pdo, 'shelter.applied', 'shelter', (int) $pdo->lastInsertId());
                    $pdo->commit();
                    flash('success', 'Application submitted. An administrator can move it into review.');
                    redirect('/login.php');
                } catch (Throwable $exception) {
                    $pdo->rollBack();
                    throw $exception;
                }
            }
        }
    }
} catch (Throwable) {
    http_response_code(500);
    exit('Registration could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Register a Shelter | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body class="auth-shell">
  <main class="auth-card wide">
    <a class="brand auth-brand" href="<?php echo e(url('/')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
    <h1>Register a shelter</h1>
    <p class="muted">Submit an application for admin review before publishing listings.</p>
    <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
    <form method="post" class="form">
      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
      <div class="grid two-up">
        <label><span>Your name</span><input class="input" type="text" name="name" required></label>
        <label><span>Email</span><input class="input" type="email" name="email" required></label>
        <label><span>Password</span><input class="input" type="password" name="password" minlength="10" required></label>
        <label><span>Shelter name</span><input class="input" type="text" name="shelter_name" required></label>
        <label><span>Phone</span><input class="input" type="text" name="contact_phone"></label>
        <label><span>City</span><input class="input" type="text" name="city"></label>
        <label><span>Region</span><input class="input" type="text" name="region"></label>
        <label><span>Country</span><input class="input" type="text" name="country"></label>
      </div>
      <label><span>Shelter description</span><textarea class="input" name="description" rows="4"></textarea></label>
      <button type="submit" class="btn green">Submit application</button>
    </form>
  </main>
</body>
</html>
