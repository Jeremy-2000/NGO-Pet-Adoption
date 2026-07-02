<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $animalRepository = new AnimalRepository($pdo);
    $animalId = max(0, (int) ($_GET['id'] ?? $_POST['animal_id'] ?? 0));
    $animal = $animalRepository->findPublic($animalId);

    if (!$animal) {
        http_response_code(404);
        exit('Animal not found.');
    }

    $rateLimiter = new RateLimiter($pdo);
    $rateConfig = config('rate_limits', []);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'inquiry') {
            $limit = $rateConfig['inquiry'];

            if (!$rateLimiter->allow('inquiry', client_identity_hash(), (int) $limit['attempts'], (int) $limit['decay_seconds'])) {
                flash('error', 'Too many inquiries were submitted from this browser. Please try again later.');
                redirect('/animal.php?id=' . $animalId . '#inquiry');
            }

            if (trim((string) ($_POST['company'] ?? '')) !== '') {
                flash('error', 'The inquiry could not be submitted.');
                redirect('/animal.php?id=' . $animalId . '#inquiry');
            }

            $name = substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
            $email = trim((string) ($_POST['email'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));

            if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($message) < 20) {
                flash('error', 'Please enter your name, a valid email address, and a message of at least 20 characters.');
                redirect('/animal.php?id=' . $animalId . '#inquiry');
            }

            $statement = $pdo->prepare(
                'INSERT INTO inquiries (animal_id, shelter_id, user_id, name, email, phone, message, ip_hash, user_agent, source_page)
                VALUES (:animal_id, :shelter_id, :user_id, :name, :email, :phone, :message, :ip_hash, :user_agent, :source_page)'
            );
            $statement->execute([
                'animal_id' => $animalId,
                'shelter_id' => (int) $animal['shelter_id'],
                'user_id' => currentUser()['id'] ?? null,
                'name' => $name,
                'email' => $email,
                'phone' => substr(trim((string) ($_POST['phone'] ?? '')), 0, 50) ?: null,
                'message' => $message,
                'ip_hash' => client_identity_hash(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'source_page' => '/animal.php?id=' . $animalId,
            ]);
            audit_log($pdo, 'inquiry.created', 'animal', $animalId, ['shelter_id' => (int) $animal['shelter_id']]);
            flash('success', 'Inquiry sent. The shelter can review it in their portal.');
            redirect('/animal.php?id=' . $animalId . '#inquiry');
        }

        if ($action === 'favorite') {
            $limit = $rateConfig['favorite'];

            if ($rateLimiter->allow('favorite', client_identity_hash(), (int) $limit['attempts'], (int) $limit['decay_seconds'])) {
                $animalRepository->createFavorite($animalId, currentUser()['id'] ?? null, session_id());
                audit_log($pdo, 'favorite.created', 'animal', $animalId);
            }

            flash('success', 'Saved to favorites.');
            redirect('/animal.php?id=' . $animalId);
        }

        if ($action === 'report') {
            $limit = $rateConfig['report'];

            if (!$rateLimiter->allow('report', client_identity_hash(), (int) $limit['attempts'], (int) $limit['decay_seconds'])) {
                flash('error', 'Too many reports were submitted from this browser. Please try again later.');
                redirect('/animal.php?id=' . $animalId . '#report');
            }

            $reporterName = substr(trim((string) ($_POST['reporter_name'] ?? '')), 0, 150);
            $reporterEmail = trim((string) ($_POST['reporter_email'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));

            if ($reporterName === '' || !filter_var($reporterEmail, FILTER_VALIDATE_EMAIL) || strlen($reason) < 15) {
                flash('error', 'Please include your name, a valid email, and a clear report reason.');
                redirect('/animal.php?id=' . $animalId . '#report');
            }

            $statement = $pdo->prepare(
                'INSERT INTO reports (animal_id, shelter_id, reporter_name, reporter_email, reason)
                VALUES (?, ?, ?, ?, ?)'
            );
            $statement->execute([$animalId, (int) $animal['shelter_id'], $reporterName, $reporterEmail, $reason]);
            audit_log($pdo, 'report.created', 'animal', $animalId);
            flash('success', 'Report submitted for admin review.');
            redirect('/animal.php?id=' . $animalId . '#report');
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $animalRepository->incrementViews($animalId);
        $animal['views_count'] = (int) $animal['views_count'] + 1;
    }

    $images = $animalRepository->imagesForAnimal($animalId);
    $visibility = (new VisibilityService(config('visibility.weights', []), config('visibility.limits', [])))->explain($animal);
} catch (Throwable $exception) {
    http_response_code(500);
    exit('The animal page could not be loaded.');
}

$success = flash('success');
$error = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo e($animal['name']); ?> | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <header class="topbar">
    <div class="wrap nav">
      <a class="brand" href="<?php echo e(url('/')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav class="links" aria-label="Primary navigation">
        <a href="<?php echo e(url('/')); ?>">Home</a>
        <a class="active" href="<?php echo e(url('/animals.php')); ?>">Browse</a>
        <a href="<?php echo e(url('/shelters.php')); ?>">Shelters</a>
        <a href="<?php echo e(url('/vote.php')); ?>">Vote</a>
      </nav>
      <div class="actions"><a class="btn secondary" href="<?php echo e(url('/login.php')); ?>">Sign in</a></div>
    </div>
  </header>

  <main>
    <section class="page-title">
      <div class="wrap">
        <?php if ((float) $visibility['score'] >= 0.6 || (int) $animal['is_featured'] === 1) : ?><span class="badge promoted">Promoted by visibility engine</span><?php endif; ?>
        <h1><?php echo e($animal['name']); ?></h1>
        <p class="lead muted"><?php echo e($animal['species']); ?> - <?php echo e($animal['breed'] ?: 'Mixed breed'); ?> - <?php echo e($animal['age'] ?: 'Age unknown'); ?></p>
      </div>
    </section>

    <section class="section detail-section">
      <div class="wrap detail">
        <div>
          <div class="gallery-main">
            <?php if ($images !== []) : ?>
              <div class="media" data-carousel>
                <div class="slides">
                  <?php foreach ($images as $image) : ?>
                    <img class="slide" src="<?php echo e(uploaded_url($image['file_path'])); ?>" alt="<?php echo e($animal['name']); ?>" loading="lazy">
                  <?php endforeach; ?>
                </div>
                <?php if (count($images) > 1) : ?>
                  <button class="car-btn prev" type="button" data-carousel-prev aria-label="Previous image">&lsaquo;</button>
                  <button class="car-btn next" type="button" data-carousel-next aria-label="Next image">&rsaquo;</button>
                <?php endif; ?>
              </div>
            <?php else : ?>
              <div class="image-placeholder large"><?php echo e($animal['species']); ?></div>
            <?php endif; ?>
          </div>

          <div class="panel">
            <h2>About <?php echo e($animal['name']); ?></h2>
            <div class="quick">
              <div><b>Energy</b><br><span class="muted"><?php echo e($animal['energy_level'] ?: 'Not specified'); ?></span></div>
              <div><b>Temperament</b><br><span class="muted"><?php echo e($animal['temperament'] ?: 'Not specified'); ?></span></div>
              <div><b>Good with children</b><br><span class="muted"><?php echo e(bool_label($animal['good_with_children'])); ?></span></div>
              <div><b>Good with cats</b><br><span class="muted"><?php echo e(bool_label($animal['good_with_cats'])); ?></span></div>
              <div><b>Good with dogs</b><br><span class="muted"><?php echo e(bool_label($animal['good_with_dogs'])); ?></span></div>
              <div><b>Size</b><br><span class="muted"><?php echo e($animal['size'] ?: 'Not specified'); ?></span></div>
            </div>
          </div>

          <div class="panel">
            <h2>Medical and care</h2>
            <div class="meta">
              <span class="pill"><?php echo e((int) $animal['vaccinated'] === 1 ? 'Vaccinated' : 'Vaccination unknown'); ?></span>
              <span class="pill"><?php echo e((int) $animal['spayed_neutered'] === 1 ? 'Spayed/neutered' : 'Spay/neuter unknown'); ?></span>
              <?php if ((int) $animal['is_senior'] === 1) : ?><span class="pill">Senior animal</span><?php endif; ?>
            </div>
            <?php if ($animal['medical_conditions']) : ?><p><?php echo nl2br(e($animal['medical_conditions'])); ?></p><?php endif; ?>
            <?php if ($animal['special_needs']) : ?><p><strong>Special needs:</strong> <?php echo nl2br(e($animal['special_needs'])); ?></p><?php endif; ?>
          </div>
        </div>

        <aside>
          <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
          <?php if ($error) : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

          <div class="panel sticky-panel">
            <div class="card-title">
              <h2><?php echo e($animal['name']); ?></h2>
              <span class="badge <?php echo e($animal['status'] === 'medical_hold' ? 'hold' : 'available'); ?>"><?php echo e(status_label($animal['status'])); ?></span>
            </div>
            <p class="muted">Listed by <a href="<?php echo e(url('/shelter.php?slug=' . rawurlencode($animal['shelter_slug']))); ?>"><b><?php echo e($animal['shelter_name']); ?></b></a></p>
            <div class="quick">
              <div><b>Species</b><br><span class="muted"><?php echo e($animal['species']); ?></span></div>
              <div><b>Gender</b><br><span class="muted"><?php echo e($animal['gender'] ?: 'Unknown'); ?></span></div>
              <div><b>Color</b><br><span class="muted"><?php echo e($animal['color'] ?: 'Unknown'); ?></span></div>
              <div><b>Location</b><br><span class="muted"><?php echo e($animal['city'] ?: $animal['country'] ?: 'Not listed'); ?></span></div>
            </div>
            <div class="button-row">
              <a class="btn green" href="#inquiry">Start inquiry</a>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                <input type="hidden" name="animal_id" value="<?php echo e($animalId); ?>">
                <input type="hidden" name="action" value="favorite">
                <button class="btn secondary" type="submit">Save</button>
              </form>
            </div>
          </div>

          <div class="panel">
            <h3>Why promoted?</h3>
            <p class="muted">Visibility score <?php echo e($visibility['score']); ?> balances waiting time, low views, low favorites, senior status, and vote wins.</p>
            <div class="timeline">
              <div class="step"><span class="num">1</span><div><b><?php echo e($visibility['days_listed']); ?> days listed</b><br><span class="muted">Longer wait can increase exposure.</span></div></div>
              <div class="step"><span class="num">2</span><div><b><?php echo e($visibility['views_count']); ?> views</b><br><span class="muted">Lower views can receive a boost.</span></div></div>
              <div class="step"><span class="num">3</span><div><b><?php echo e($visibility['vote_wins']); ?> vote wins</b><br><span class="muted">Community engagement supports discovery.</span></div></div>
            </div>
          </div>

          <div id="inquiry" class="panel">
            <h3>Inquiry form</h3>
            <form class="form" method="post">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <input type="hidden" name="animal_id" value="<?php echo e($animalId); ?>">
              <input type="hidden" name="action" value="inquiry">
              <label class="visually-hidden" for="company">Company</label>
              <input id="company" class="honeypot" name="company" tabindex="-1" autocomplete="off">
              <input class="input" name="name" placeholder="Your name" required>
              <input class="input" name="email" type="email" placeholder="Email address" required>
              <input class="input" name="phone" placeholder="Phone optional">
              <textarea class="input" name="message" rows="5" placeholder="Tell the shelter about your home and interest" required></textarea>
              <button class="btn green" type="submit">Submit inquiry</button>
            </form>
          </div>

          <div id="report" class="panel">
            <h3>Report listing</h3>
            <form class="form" method="post">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <input type="hidden" name="animal_id" value="<?php echo e($animalId); ?>">
              <input type="hidden" name="action" value="report">
              <input class="input" name="reporter_name" placeholder="Your name" required>
              <input class="input" name="reporter_email" type="email" placeholder="Email address" required>
              <textarea class="input" name="reason" rows="4" placeholder="What should administrators review?" required></textarea>
              <button class="btn secondary" type="submit">Submit report</button>
            </form>
          </div>
        </aside>
      </div>
    </section>
  </main>
</body>
</html>
