<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $animalRepository = new AnimalRepository($pdo);
    $rateLimiter = new RateLimiter($pdo);
    $rateConfig = config('rate_limits.vote', ['attempts' => 20, 'decay_seconds' => 3600]);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $animalAId = (int) ($_POST['animal_a_id'] ?? 0);
        $animalBId = (int) ($_POST['animal_b_id'] ?? 0);
        $winnerId = (int) ($_POST['winner_animal_id'] ?? 0);

        if ($animalAId > 0 && $animalBId > 0 && in_array($winnerId, [$animalAId, $animalBId], true)) {
            if ($rateLimiter->allow('vote', client_identity_hash(), (int) $rateConfig['attempts'], (int) $rateConfig['decay_seconds'])) {
                $ids = [$animalAId, $animalBId];
                sort($ids);
                $matchupKey = hash('sha256', implode(':', $ids));
                $statement = $pdo->prepare(
                    'INSERT IGNORE INTO votes (matchup_key, animal_a_id, animal_b_id, winner_animal_id, voter_hash)
                    VALUES (?, ?, ?, ?, ?)'
                );
                $statement->execute([$matchupKey, $animalAId, $animalBId, $winnerId, client_identity_hash()]);
                audit_log($pdo, 'vote.created', 'animal', $winnerId, ['animal_a_id' => $animalAId, 'animal_b_id' => $animalBId]);
                flash('success', 'Vote recorded. Winning animals receive an engagement signal for visibility.');
            } else {
                flash('error', 'Too many votes were submitted from this browser. Please try again later.');
            }
        }

        redirect('/vote.php');
    }

    $pair = $animalRepository->votePair();
} catch (Throwable) {
    http_response_code(500);
    exit('Voting could not be loaded.');
}

$success = flash('success');
$error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Vote | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body>
  <header class="topbar">
    <div class="wrap nav">
      <a class="brand" href="<?php echo e(url('/')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav class="links" aria-label="Primary navigation">
        <a href="<?php echo e(url('/')); ?>">Home</a>
        <a href="<?php echo e(url('/animals.php')); ?>">Browse</a>
        <a href="<?php echo e(url('/shelters.php')); ?>">Shelters</a>
        <a class="active" href="<?php echo e(url('/vote.php')); ?>">Vote</a>
      </nav>
      <div class="actions">
        <?php if (isLoggedIn()) : ?>
          <a class="btn secondary" href="<?php echo e(url(user_home_path())); ?>"><?php echo e(user_home_label()); ?></a>
        <?php else : ?>
          <a class="btn secondary" href="<?php echo e(url('/login.php')); ?>">Sign in</a>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <main>
    <section class="page-title">
      <div class="wrap">
        <span class="eyebrow">Engagement voting</span>
        <h1>Which animal should get more visibility?</h1>
        <p class="lead muted">Votes create an engagement signal used by the configurable visibility engine.</p>
        <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <?php if (!$pair) : ?>
          <div class="empty-state">
            <h2>Voting needs at least two adoptable animals.</h2>
            <p class="muted">Approved shelter listings will appear here automatically.</p>
          </div>
        <?php else : ?>
          <form class="vote-grid" method="post">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
            <input type="hidden" name="animal_a_id" value="<?php echo e($pair[0]['id']); ?>">
            <input type="hidden" name="animal_b_id" value="<?php echo e($pair[1]['id']); ?>">
            <?php foreach ($pair as $animal) : ?>
              <article class="animal-card vote-card">
                <div class="media">
                  <?php if ($animal['thumbnail_path'] ?? $animal['image_path'] ?? '') : ?>
                    <img src="<?php echo e(uploaded_url($animal['thumbnail_path'] ?: $animal['image_path'])); ?>" alt="<?php echo e($animal['name']); ?>" style="object-position: <?php echo e($animal['image_crop_focus'] ?? 'center'); ?>;">
                  <?php else : ?>
                    <div class="image-placeholder"><?php echo e($animal['species']); ?></div>
                  <?php endif; ?>
                </div>
                <div class="card-body">
                  <div class="card-title">
                    <h2><?php echo e($animal['name']); ?></h2>
                    <span class="badge <?php echo e(status_badge_class($animal['status'])); ?>"><?php echo e(status_label($animal['status'])); ?></span>
                  </div>
                  <p class="muted"><?php echo e($animal['species']); ?> - <?php echo e($animal['breed'] ?: 'Mixed breed'); ?> - <?php echo e($animal['shelter_name']); ?></p>
                  <div class="meta">
                    <?php if ($animal['age']) : ?><span class="pill"><?php echo e($animal['age']); ?></span><?php endif; ?>
                    <?php if ((int) $animal['is_senior'] === 1) : ?><span class="pill">Senior</span><?php endif; ?>
                    <?php if ((int) $animal['good_with_children'] === 1) : ?><span class="pill">Child friendly</span><?php endif; ?>
                  </div>
                  <button class="btn green" name="winner_animal_id" value="<?php echo e($animal['id']); ?>" type="submit">Vote for <?php echo e($animal['name']); ?></button>
                </div>
              </article>
            <?php endforeach; ?>
          </form>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
