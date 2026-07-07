<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $animalRepository = new AnimalRepository($pdo);
    $shelterRepository = new ShelterRepository($pdo);
    $visibilityService = new VisibilityService(config('visibility.weights', []), config('visibility.limits', []));
    $animals = $animalRepository->featuredForHome(9);

    foreach ($animals as $index => $animal) {
        $animals[$index]['visibility_score'] = $visibilityService->score($animal);
    }

    usort($animals, static fn (array $a, array $b): int => ($b['is_featured'] <=> $a['is_featured']) ?: ($b['visibility_score'] <=> $a['visibility_score']));
    $heroAnimal = $animals[0] ?? null;
    $visibleAnimals = array_slice($animals, 0, 6);
    $shelterCount = (int) $pdo->query("SELECT COUNT(*) FROM shelters WHERE status = 'approved'")->fetchColumn();
    $animalCount = (int) $pdo->query("SELECT COUNT(*) FROM animals a INNER JOIN shelters s ON s.id = a.shelter_id WHERE s.status = 'approved' AND a.status IN ('available','reserved','medical_hold')")->fetchColumn();
    $inquiryCount = (int) $pdo->query('SELECT COUNT(*) FROM inquiries')->fetchColumn();
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
  <title><?php echo e(config('app_name')); ?></title>
  <meta name="description" content="A fair, accessible animal adoption platform connecting approved shelters with adopters.">
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <header class="topbar">
    <div class="wrap nav">
      <a class="brand" href="<?php echo e(url('/')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav class="links" aria-label="Primary navigation">
        <a class="active" href="<?php echo e(url('/')); ?>">Home</a>
        <a href="<?php echo e(url('/animals.php')); ?>">Browse</a>
        <a href="<?php echo e(url('/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/vote.php')); ?>">Vote</a>
      </nav>
      <div class="actions">
        <?php if (isLoggedIn()) : ?>
          <a class="btn secondary" href="<?php echo e(url(user_home_path())); ?>"><?php echo e(user_home_label()); ?></a>
        <?php else : ?>
          <a class="btn secondary" href="<?php echo e(url('/login.php')); ?>">Sign in</a>
        <?php endif; ?>
        <a class="btn green" href="<?php echo e(url('/animals.php')); ?>">Find a pet</a>
      </div>
    </div>
  </header>

  <main>
    <section class="hero">
      <div class="wrap hero-grid">
        <div>
          <span class="eyebrow">Fair visibility for overlooked animals</span>
          <h1>Help every animal get discovered.</h1>
          <p class="lead">A modern adoption platform for shelters, adopters, and animals who need a second chance.</p>
          <form class="searchbox" action="<?php echo e(url('/animals.php')); ?>" method="get">
            <label class="field">
              <span>Search</span>
              <input name="q" placeholder="Name, breed, shelter">
            </label>
            <label class="field">
              <span>Species</span>
              <input name="species" placeholder="Dog, cat">
            </label>
            <label class="field">
              <span>Location</span>
              <input name="location" placeholder="City or region">
            </label>
            <button class="btn green" type="submit">Search</button>
          </form>
          <div class="statrow" aria-label="Platform statistics">
            <div class="stat"><b><?php echo e(number_format($animalCount)); ?></b><br><span class="muted">active listings</span></div>
            <div class="stat"><b><?php echo e(number_format($shelterCount)); ?></b><br><span class="muted">approved shelters</span></div>
            <div class="stat"><b><?php echo e(number_format($inquiryCount)); ?></b><br><span class="muted">adoption inquiries</span></div>
          </div>
        </div>
        <div class="hero-card" data-animate>
          <?php if ($heroAnimal && ($heroAnimal['image_path'] ?? '')) : ?>
            <img src="<?php echo e(uploaded_url($heroAnimal['image_path'])); ?>" alt="<?php echo e($heroAnimal['name']); ?>" loading="eager" style="object-position: <?php echo e($heroAnimal['image_crop_focus'] ?? 'center'); ?>;">
          <?php else : ?>
            <div class="image-placeholder large">No image yet</div>
          <?php endif; ?>
          <div class="glass">
            <div>
              <b><?php echo e($heroAnimal['name'] ?? 'Visibility engine ready'); ?></b><br>
              <span class="muted">
                <?php echo $heroAnimal ? 'Score ' . e($heroAnimal['visibility_score']) . ' - ' . e($heroAnimal['shelter_name']) : 'Approved listings will appear here automatically.'; ?>
              </span>
            </div>
            <?php if ($heroAnimal) : ?>
              <a class="btn small" href="<?php echo e(url('/animal.php?id=' . $heroAnimal['id'])); ?>">View</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <div class="section-head">
          <div>
            <span class="eyebrow">Visibility engine</span>
            <h2>Promoted because they need it most.</h2>
          </div>
          <a class="btn secondary" href="<?php echo e(url('/animals.php')); ?>">View all</a>
        </div>
        <div class="grid cards">
          <?php foreach ($visibleAnimals as $animal) : ?>
            <article class="animal-card" data-animate>
              <a class="media" href="<?php echo e(url('/animal.php?id=' . $animal['id'])); ?>" aria-label="View <?php echo e($animal['name']); ?>">
                <?php if ((int) $animal['is_featured'] === 1 || (float) $animal['visibility_score'] >= 0.6) : ?>
                  <span class="badge promoted">Promoted</span>
                <?php endif; ?>
                <?php if ($animal['thumbnail_path'] ?? $animal['image_path'] ?? '') : ?>
                  <img src="<?php echo e(uploaded_url($animal['thumbnail_path'] ?: $animal['image_path'])); ?>" alt="<?php echo e($animal['name']); ?>" loading="lazy" style="object-position: <?php echo e($animal['image_crop_focus'] ?? 'center'); ?>;">
                <?php else : ?>
                  <div class="image-placeholder"><?php echo e($animal['species']); ?></div>
                <?php endif; ?>
              </a>
              <div class="card-body">
                <div class="card-title">
                  <h3><?php echo e($animal['name']); ?></h3>
                  <span class="badge <?php echo e(status_badge_class($animal['status'])); ?>"><?php echo e(status_label($animal['status'])); ?></span>
                </div>
                <p class="muted"><?php echo e($animal['species']); ?> - <?php echo e($animal['breed'] ?: 'Mixed breed'); ?> - <?php echo e($animal['shelter_name']); ?></p>
                <div class="meta">
                  <?php if ($animal['age']) : ?><span class="pill"><?php echo e($animal['age']); ?></span><?php endif; ?>
                  <?php if ($animal['temperament']) : ?><span class="pill"><?php echo e($animal['temperament']); ?></span><?php endif; ?>
                  <?php if ((int) $animal['good_with_children'] === 1) : ?><span class="pill">Child friendly</span><?php endif; ?>
                </div>
                <small class="muted">Visibility score <?php echo e($animal['visibility_score']); ?></small>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <section class="section compact">
      <div class="wrap grid three-up">
        <article class="panel">
          <h3>Detailed profiles</h3>
          <p class="muted">Photos, temperament, medical notes, adoption status, and shelter context.</p>
        </article>
        <article class="panel">
          <h3>Fair discovery</h3>
          <p class="muted">Older, overlooked, and low-engagement animals receive configured visibility boosts.</p>
        </article>
        <article class="panel">
          <h3>Trusted shelters</h3>
          <p class="muted">Only approved shelters can publish animals after admin review.</p>
        </article>
      </div>
    </section>
  </main>

  <footer class="footer">
    <div class="wrap"><b>Pet Adoption</b><span>Built for shelters, adopters, and administrators.</span></div>
  </footer>
</body>
</html>
