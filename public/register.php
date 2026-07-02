<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$error = '';
$selectedType = in_array(($_GET['type'] ?? ''), ['visitor', 'shelter'], true) ? (string) $_GET['type'] : 'visitor';

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);
    $rateLimiter = new RateLimiter($pdo);
    $rateConfig = config('rate_limits.signup', ['attempts' => 6, 'decay_seconds' => 3600]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $selectedType = in_array(($_POST['account_type'] ?? ''), ['visitor', 'shelter'], true) ? (string) $_POST['account_type'] : 'visitor';

        if (!$rateLimiter->allow('signup', client_identity_hash(), (int) $rateConfig['attempts'], (int) $rateConfig['decay_seconds'])) {
            $error = 'Too many sign-up attempts. Please try again later.';
        } elseif (trim((string) ($_POST['company'] ?? '')) !== '') {
            $error = 'The form could not be submitted.';
        } elseif (empty($_POST['privacy_consent'])) {
            $error = 'Please confirm consent before submitting.';
        } else {
            $name = substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
            $email = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');
            $shelterName = substr(trim((string) ($_POST['shelter_name'] ?? '')), 0, 180);

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 10 || ($selectedType === 'shelter' && $shelterName === '')) {
                $error = 'Please complete all required fields with a valid email and a 10+ character password.';
            } else {
                $exists = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
                $exists->execute([$email]);

                if ($exists->fetch()) {
                    $error = 'An account already exists for that email address.';
                } else {
                    $pdo->beginTransaction();

                    try {
                        $role = $selectedType === 'shelter' ? 'shelter' : 'visitor';
                        $status = $selectedType === 'shelter' ? 'pending' : 'active';
                        $userStmt = $pdo->prepare('INSERT INTO users (name, email, password_hash, role, status) VALUES (?, ?, ?, ?, ?)');
                        $userStmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, $status]);
                        $userId = (int) $pdo->lastInsertId();

                        if ($selectedType === 'shelter') {
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
                            $message = 'Shelter application submitted. An administrator can move it into review.';
                        } else {
                            audit_log($pdo, 'visitor.registered', 'user', $userId);
                            $message = 'Account created. You can now apply for adoptions and track your status.';
                        }

                        $pdo->commit();
                        flash('success', $message);
                        redirect('/login.php');
                    } catch (Throwable $exception) {
                        $pdo->rollBack();
                        throw $exception;
                    }
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
  <title>Create Account | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body class="auth-shell">
  <main class="auth-card wide">
    <a class="brand auth-brand" href="<?php echo e(url('/')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
    <h1>Create an account</h1>
    <p class="muted">Adopters can apply and track status. Shelters can submit an application for admin review.</p>
    <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
    <form method="post" class="form">
      <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
      <label class="visually-hidden" for="company">Company</label>
      <input id="company" class="honeypot" name="company" tabindex="-1" autocomplete="off">
      <label>
        <span>Account type</span>
        <select class="input" name="account_type" data-account-type>
          <option value="visitor" <?php echo selected($selectedType, 'visitor'); ?>>Adopter account</option>
          <option value="shelter" <?php echo selected($selectedType, 'shelter'); ?>>Shelter application</option>
        </select>
      </label>
      <div class="grid two-up">
        <label><span>Your name</span><input class="input" type="text" name="name" required></label>
        <label><span>Email</span><input class="input" type="email" name="email" required></label>
        <label><span>Password</span><input class="input" type="password" name="password" minlength="10" required></label>
        <label data-shelter-field><span>Shelter name</span><input class="input" type="text" name="shelter_name"></label>
        <label data-shelter-field><span>Phone</span><input class="input" type="text" name="contact_phone"></label>
        <label data-shelter-field><span>City</span><input class="input" type="text" name="city"></label>
        <label data-shelter-field><span>Region</span><input class="input" type="text" name="region"></label>
        <label data-shelter-field><span>Country</span><input class="input" type="text" name="country"></label>
      </div>
      <label data-shelter-field><span>Shelter description</span><textarea class="input" name="description" rows="4"></textarea></label>
      <label class="inline-check"><input type="checkbox" name="privacy_consent" value="1" required> I agree that these details can be stored for adoption platform operations.</label>
      <button type="submit" class="btn green">Submit</button>
    </form>
    <p class="muted">Already have an account? <a href="<?php echo e(url('/login.php')); ?>">Sign in</a>.</p>
  </main>
</body>
</html>
