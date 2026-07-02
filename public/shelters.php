<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $shelters = (new ShelterRepository(db()))->publicList();
} catch (Throwable) {
    http_response_code(500);
    exit('Database connection failed. Run the installer first.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shelters | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body>
  <header class="topbar">
    <div class="wrap nav">
      <a class="brand" href="<?php echo e(url('/')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav class="links" aria-label="Primary navigation">
        <a href="<?php echo e(url('/')); ?>">Home</a>
        <a href="<?php echo e(url('/animals.php')); ?>">Browse</a>
        <a class="active" href="<?php echo e(url('/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/vote.php')); ?>">Vote</a>
      </nav>
      <div class="actions">
        <?php if (isLoggedIn()) : ?>
          <a class="btn secondary" href="<?php echo e(url(currentUser()['role'] === 'visitor' ? '/account.php' : (currentUser()['role'] === 'shelter' ? '/shelter/dashboard.php' : '/admin/dashboard.php'))); ?>">Dashboard</a>
        <?php else : ?>
          <a class="btn secondary" href="<?php echo e(url('/login.php')); ?>">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <section class="page-title">
      <div class="wrap">
        <h1>Approved shelters</h1>
        <p class="lead muted">Browse trusted shelters and rescue centers with active listings.</p>
      </div>
    </section>

    <section class="section">
      <div class="wrap grid cards">
        <?php if ($shelters === []) : ?>
          <div class="empty-state">
            <h2>No approved shelters yet.</h2>
            <p class="muted">Approved shelter applications will appear here automatically.</p>
          </div>
        <?php endif; ?>
        <?php foreach ($shelters as $shelter) : ?>
          <article class="panel shelter-card" data-animate>
            <div class="shelter-card-head">
              <?php if ($shelter['logo_path']) : ?>
                <img class="shelter-logo" src="<?php echo e(uploaded_url($shelter['logo_path'])); ?>" alt="<?php echo e($shelter['name']); ?>">
              <?php else : ?>
                <div class="shelter-logo placeholder"><?php echo e(strtoupper(substr($shelter['name'], 0, 2))); ?></div>
              <?php endif; ?>
              <div>
                <h2><?php echo e($shelter['name']); ?></h2>
                <p class="muted"><?php echo e($shelter['city'] ?: $shelter['country'] ?: 'Location not listed'); ?></p>
              </div>
            </div>
            <p class="muted"><?php echo e(excerpt($shelter['description'] ?? '', 150) ?: 'Shelter profile details coming soon.'); ?></p>
            <div class="meta">
              <span class="pill"><?php echo e((int) $shelter['active_animals']); ?> active listings</span>
              <span class="pill"><?php echo e((int) $shelter['adopted_animals']); ?> adopted</span>
            </div>
            <a class="btn secondary" href="<?php echo e(url('/shelter.php?slug=' . rawurlencode($shelter['slug']))); ?>">View shelter</a>
          </article>
        <?php endforeach; ?>
      </div>
    </section>
  </main>
</body>
</html>
