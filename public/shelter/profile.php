<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('shelter');

$error = '';

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

        try {
            $shelterRepository->updateProfile((int) $shelter['id'], $_POST);

            if (!empty($_FILES['logo']['name'])) {
                $uploadService = new UploadService($pdo, config('uploads', []));
                $logoPath = $uploadService->storeShelterLogo($_FILES['logo'], (int) $shelter['id']);

                if ($logoPath !== null) {
                    $shelterRepository->updateLogo((int) $shelter['id'], $logoPath);
                }
            }

            audit_log($pdo, 'shelter.profile_updated', 'shelter', (int) $shelter['id']);
            flash('success', 'Profile updated.');
            redirect('/shelter/profile.php');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    $shelter = $shelterRepository->findByUserId((int) currentUser()['id']);
} catch (Throwable) {
    http_response_code(500);
    exit('Shelter profile could not be loaded.');
}

$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shelter Profile | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/shelter/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/shelter/dashboard.php')); ?>">Dashboard</a>
        <a class="active" href="<?php echo e(url('/shelter/profile.php')); ?>">Profile</a>
        <a href="<?php echo e(url('/shelter/listings.php')); ?>">Listings</a>
        <a href="<?php echo e(url('/shelter/inquiries.php')); ?>">Inquiries</a>
        <a href="<?php echo e(url('/shelter/applications.php')); ?>">Applications</a>
        <a href="<?php echo e(url('/shelter/questions.php')); ?>">Questions</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header">
        <div><p class="eyebrow">Shelter portal</p><h1>Profile</h1></div>
      </header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

      <section class="card">
        <form method="post" enctype="multipart/form-data" class="form">
          <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
          <div class="grid two-up">
            <label><span>Shelter name</span><input class="input" name="name" value="<?php echo e($shelter['name']); ?>" required></label>
            <label><span>Contact email</span><input class="input" type="email" name="contact_email" value="<?php echo e($shelter['contact_email']); ?>"></label>
            <label><span>Phone</span><input class="input" name="contact_phone" value="<?php echo e($shelter['contact_phone']); ?>"></label>
            <label><span>Website</span><input class="input" type="url" name="website" value="<?php echo e($shelter['website']); ?>"></label>
            <label><span>Facebook URL</span><input class="input" type="url" name="facebook_url" value="<?php echo e($shelter['facebook_url']); ?>"></label>
            <label><span>Instagram URL</span><input class="input" type="url" name="instagram_url" value="<?php echo e($shelter['instagram_url']); ?>"></label>
            <label><span>Address</span><input class="input" name="address" value="<?php echo e($shelter['address']); ?>"></label>
            <label><span>City</span><input class="input" name="city" value="<?php echo e($shelter['city']); ?>"></label>
            <label><span>Region</span><input class="input" name="region" value="<?php echo e($shelter['region']); ?>"></label>
            <label><span>Country</span><input class="input" name="country" value="<?php echo e($shelter['country']); ?>"></label>
            <label><span>Logo</span><input class="input" type="file" name="logo" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></label>
          </div>
          <label><span>Description</span><textarea class="input" name="description" rows="6"><?php echo e($shelter['description']); ?></textarea></label>
          <button class="btn green" type="submit">Save profile</button>
        </form>
      </section>
    </main>
  </div>
</body>
</html>
