<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);
    $animalRepository = new AnimalRepository($pdo);
    $slug = trim((string) ($_GET['slug'] ?? ''));
    $shelter = $shelterRepository->findPublicBySlug($slug);

    if (!$shelter) {
        http_response_code(404);
        exit('Shelter not found.');
    }

    $animals = $animalRepository->publicForShelter((int) $shelter['id']);
} catch (Throwable) {
    http_response_code(500);
    exit('The shelter page could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($shelter['name']); ?> | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
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
      <div class="actions"><a class="btn secondary" href="<?php echo e(url('/login.php')); ?>">Sign in</a></div>
    </div>
  </header>

  <main>
    <section class="page-title">
      <div class="wrap shelter-hero">
        <div class="logo-card">
          <?php if ($shelter['logo_path']) : ?>
            <img class="shelter-logo xl" src="<?php echo e(uploaded_url($shelter['logo_path'])); ?>" alt="<?php echo e($shelter['name']); ?>">
          <?php else : ?>
            <div class="avatar"><?php echo e(strtoupper(substr($shelter['name'], 0, 2))); ?></div>
          <?php endif; ?>
        </div>
        <div>
          <span class="eyebrow">Approved shelter</span>
          <h1><?php echo e($shelter['name']); ?></h1>
          <p class="lead muted"><?php echo e($shelter['description'] ?: 'This shelter has been approved to publish animal listings.'); ?></p>
          <div class="meta">
            <span class="pill"><?php echo e((int) $shelter['active_animals']); ?> active listings</span>
            <span class="pill"><?php echo e($shelter['city'] ?: $shelter['country'] ?: 'Location not listed'); ?></span>
          </div>
          <div class="button-row">
            <?php if ($shelter['website']) : ?><a class="btn secondary" href="<?php echo e($shelter['website']); ?>" rel="noopener" target="_blank">Website</a><?php endif; ?>
            <?php if ($shelter['contact_email']) : ?><a class="btn green" href="mailto:<?php echo e($shelter['contact_email']); ?>">Contact</a><?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <div class="section-head">
          <div>
            <span class="eyebrow">Active listings</span>
            <h2>Animals from <?php echo e($shelter['name']); ?></h2>
          </div>
        </div>
        <div class="grid cards">
          <?php foreach ($animals as $animal) : ?>
            <article class="animal-card" data-animate>
              <a class="media" href="<?php echo e(url('/animal.php?id=' . $animal['id'])); ?>">
                <?php if ($animal['thumbnail_path'] ?? $animal['image_path'] ?? '') : ?>
                  <img src="<?php echo e(uploaded_url($animal['thumbnail_path'] ?: $animal['image_path'])); ?>" alt="<?php echo e($animal['name']); ?>" loading="lazy">
                <?php else : ?>
                  <div class="image-placeholder"><?php echo e($animal['species']); ?></div>
                <?php endif; ?>
              </a>
              <div class="card-body">
                <div class="card-title">
                  <h3><?php echo e($animal['name']); ?></h3>
                  <span class="badge <?php echo e($animal['status'] === 'medical_hold' ? 'hold' : 'available'); ?>"><?php echo e(status_label($animal['status'])); ?></span>
                </div>
                <p class="muted"><?php echo e($animal['species']); ?> - <?php echo e($animal['breed'] ?: 'Mixed breed'); ?></p>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>
  </main>
</body>
</html>
