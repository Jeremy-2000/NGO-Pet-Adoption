<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $animalRepository = new AnimalRepository($pdo);
    $previewPayload = preview_payload('animal');
    $isPreview = $previewPayload !== null;
    $animalId = $isPreview ? 0 : max(0, (int) ($_GET['id'] ?? $_POST['animal_id'] ?? 0));
    $animal = $isPreview ? $previewPayload['animal'] : $animalRepository->findPublic($animalId);

    if (!$animal) {
        http_response_code(404);
        exit('Animal not found.');
    }

    $rateLimiter = new RateLimiter($pdo);
    $rateConfig = config('rate_limits', []);
    $viewer = currentUser();
    $shelterQuestions = [];

    if (!$isPreview && db_table_exists('shelter_questions')) {
        $questionStatement = $pdo->prepare(
            'SELECT * FROM shelter_questions
            WHERE shelter_id = ? AND is_active = 1
            ORDER BY sort_order ASC, id ASC'
        );
        $questionStatement->execute([(int) $animal['shelter_id']]);
        $shelterQuestions = $questionStatement->fetchAll();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isPreview) {
        verifyCsrf();
        $action = (string) ($_POST['action'] ?? '');

        if ($action === 'inquiry') {
            $limit = $rateConfig['inquiry'];

            if (!$rateLimiter->allow('inquiry', client_identity_hash(), (int) $limit['attempts'], (int) $limit['decay_seconds'])) {
                flash('error', 'Too many inquiries were submitted from this browser. Please try again later.');
                remember_form('inquiry', $_POST, 'inquiry-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (trim((string) ($_POST['company'] ?? '')) !== '') {
                flash('error', 'The inquiry could not be submitted.');
                remember_form('inquiry', $_POST, 'inquiry-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            $name = substr(trim((string) ($_POST['name'] ?? '')), 0, 150);
            $email = trim((string) ($_POST['email'] ?? ''));
            $message = trim((string) ($_POST['message'] ?? ''));

            if ($name === '') {
                flash('error', 'Inquiry needs your name.');
                remember_form('inquiry', $_POST, 'inquiry-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Inquiry needs a valid email address.');
                remember_form('inquiry', $_POST, 'inquiry-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (strlen($message) < 20) {
                flash('error', 'Inquiry message must be at least 20 characters.');
                remember_form('inquiry', $_POST, 'inquiry-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (empty($_POST['privacy_consent'])) {
                flash('error', 'Inquiry needs consent to share your message with the shelter.');
                remember_form('inquiry', $_POST, 'inquiry-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            $duplicate = $pdo->prepare('SELECT id FROM inquiries WHERE animal_id = ? AND email = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY) LIMIT 1');
            $duplicate->execute([$animalId, $email]);

            if ($duplicate->fetch()) {
                flash('error', 'A recent inquiry for this listing already exists with that email address.');
                remember_form('inquiry', $_POST, 'inquiry-dialog');
                redirect('/animal.php?id=' . $animalId);
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
            redirect('/animal.php?id=' . $animalId);
        }

        if ($action === 'application') {
            $user = currentUser();

            if ($user === null || ($user['role'] ?? '') !== 'visitor') {
                flash('error', 'Please create an adopter account or sign in before applying.');
                redirect('/login.php');
            }

            if (!in_array((string) $animal['status'], ['available', 'reserved', 'medical_hold'], true)) {
                flash('error', 'This listing is not currently accepting adoption applications.');
                redirect('/animal.php?id=' . $animalId);
            }

            $limit = $rateConfig['application'] ?? ['attempts' => 4, 'decay_seconds' => 3600];

            if (!$rateLimiter->allow('application', client_identity_hash(), (int) $limit['attempts'], (int) $limit['decay_seconds'])) {
                flash('error', 'Too many applications were submitted from this browser. Please try again later.');
                remember_form('application', $_POST, 'apply-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (trim((string) ($_POST['company'] ?? '')) !== '') {
                flash('error', 'The application could not be submitted.');
                remember_form('application', $_POST, 'apply-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            $name = substr(trim((string) ($_POST['name'] ?? $user['name'])), 0, 150);
            $email = trim((string) ($_POST['email'] ?? $user['email']));
            $message = trim((string) ($_POST['message'] ?? ''));

            if ($name === '') {
                flash('error', 'Application needs your name.');
                remember_form('application', $_POST, 'apply-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Application needs a valid email address.');
                remember_form('application', $_POST, 'apply-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (strlen($message) < 30) {
                flash('error', 'Application message must be at least 30 characters.');
                remember_form('application', $_POST, 'apply-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (empty($_POST['privacy_consent'])) {
                flash('error', 'Application needs consent to share your details with the shelter.');
                remember_form('application', $_POST, 'apply-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            $postedAnswers = is_array($_POST['question_answers'] ?? null) ? $_POST['question_answers'] : [];
            $questionAnswers = [];

            foreach ($shelterQuestions as $question) {
                $questionId = (int) $question['id'];
                $answer = trim((string) ($postedAnswers[$questionId] ?? ''));
                $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($question['choice_options'] ?? '')) ?: [])));

                if ((int) $question['is_required'] === 1 && $answer === '') {
                    flash('error', 'Please answer: ' . $question['question_text']);
                    remember_form('application', $_POST, 'apply-dialog');
                    redirect('/animal.php?id=' . $animalId);
                }

                if ($answer !== '' && $question['answer_type'] === 'yes_no' && !in_array($answer, ['Yes', 'No'], true)) {
                    flash('error', 'Please choose Yes or No for: ' . $question['question_text']);
                    remember_form('application', $_POST, 'apply-dialog');
                    redirect('/animal.php?id=' . $animalId);
                }

                if ($answer !== '' && $question['answer_type'] === 'choice' && $options !== [] && !in_array($answer, $options, true)) {
                    flash('error', 'Please choose a valid option for: ' . $question['question_text']);
                    remember_form('application', $_POST, 'apply-dialog');
                    redirect('/animal.php?id=' . $animalId);
                }

                $questionAnswers[$questionId] = $answer;
            }

            $duplicate = $pdo->prepare(
                "SELECT id FROM adoption_applications
                WHERE animal_id = ? AND user_id = ? AND status NOT IN ('declined','completed','cancelled')
                LIMIT 1"
            );
            $duplicate->execute([$animalId, (int) $user['id']]);

            if ($duplicate->fetch()) {
                flash('error', 'You already have an open application for this listing.');
                redirect('/animal.php?id=' . $animalId);
            }

            $statement = $pdo->prepare(
                'INSERT INTO adoption_applications
                    (animal_id, shelter_id, user_id, name, email, phone, home_type, lifestyle, has_children, has_pets, experience, message, privacy_consent, ip_hash, user_agent, source_page)
                VALUES
                    (:animal_id, :shelter_id, :user_id, :name, :email, :phone, :home_type, :lifestyle, :has_children, :has_pets, :experience, :message, :privacy_consent, :ip_hash, :user_agent, :source_page)'
            );
            $statement->execute([
                'animal_id' => $animalId,
                'shelter_id' => (int) $animal['shelter_id'],
                'user_id' => (int) $user['id'],
                'name' => $name,
                'email' => $email,
                'phone' => substr(trim((string) ($_POST['phone'] ?? '')), 0, 50) ?: null,
                'home_type' => substr(trim((string) ($_POST['home_type'] ?? '')), 0, 80) ?: null,
                'lifestyle' => substr(trim((string) ($_POST['lifestyle'] ?? '')), 0, 80) ?: null,
                'has_children' => !empty($_POST['has_children']) ? 1 : 0,
                'has_pets' => !empty($_POST['has_pets']) ? 1 : 0,
                'experience' => trim((string) ($_POST['experience'] ?? '')) ?: null,
                'message' => $message,
                'privacy_consent' => 1,
                'ip_hash' => client_identity_hash(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                'source_page' => '/animal.php?id=' . $animalId,
            ]);
            $applicationId = (int) $pdo->lastInsertId();

            if ($questionAnswers !== []) {
                $answerStatement = $pdo->prepare(
                    'INSERT INTO adoption_application_answers (application_id, question_id, answer_text)
                    VALUES (?, ?, ?)'
                );

                foreach ($questionAnswers as $questionId => $answer) {
                    $answerStatement->execute([$applicationId, $questionId, $answer === '' ? null : $answer]);
                }
            }

            audit_log($pdo, 'application.created', 'animal', $animalId, ['shelter_id' => (int) $animal['shelter_id']]);
            flash('success', 'Application submitted. You can track the status from your profile.');
            redirect('/animal.php?id=' . $animalId);
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
                remember_form('report', $_POST, 'report-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (trim((string) ($_POST['report_company'] ?? '')) !== '') {
                flash('error', 'The report could not be submitted.');
                remember_form('report', $_POST, 'report-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            $reporterName = substr(trim((string) ($_POST['reporter_name'] ?? '')), 0, 150);
            $reporterEmail = trim((string) ($_POST['reporter_email'] ?? ''));
            $reason = trim((string) ($_POST['reason'] ?? ''));

            if ($reporterName === '') {
                flash('error', 'Report needs your name.');
                remember_form('report', $_POST, 'report-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (!filter_var($reporterEmail, FILTER_VALIDATE_EMAIL)) {
                flash('error', 'Report needs a valid email address.');
                remember_form('report', $_POST, 'report-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (strlen($reason) < 15) {
                flash('error', 'Report reason must be at least 15 characters.');
                remember_form('report', $_POST, 'report-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            if (empty($_POST['privacy_consent'])) {
                flash('error', 'Report needs consent for administrator review.');
                remember_form('report', $_POST, 'report-dialog');
                redirect('/animal.php?id=' . $animalId);
            }

            $statement = $pdo->prepare(
                'INSERT INTO reports (animal_id, shelter_id, reporter_name, reporter_email, reason)
                VALUES (?, ?, ?, ?, ?)'
            );
            $statement->execute([$animalId, (int) $animal['shelter_id'], $reporterName, $reporterEmail, $reason]);
            audit_log($pdo, 'report.created', 'animal', $animalId);
            flash('success', 'Report submitted for admin review.');
            redirect('/animal.php?id=' . $animalId);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !$isPreview) {
        $animalRepository->incrementViews($animalId);
        $animalRepository->recordRecentlyViewed($animalId, currentUser()['id'] ?? null, session_id());
        $animal['views_count'] = (int) $animal['views_count'] + 1;
    }

    $images = $isPreview ? [] : $animalRepository->imagesForAnimal($animalId);
    $visibility = (new VisibilityService(config('visibility.weights', []), config('visibility.limits', [])))->explain($animal);
    $favoriteIds = $isPreview ? [] : $animalRepository->favoriteIds($viewer['id'] ?? null, session_id());
    $isSaved = in_array($animalId, $favoriteIds, true);
    $userPreferences = [];
    $existingApplication = null;

    if (!$isPreview && $viewer !== null && ($viewer['role'] ?? '') === 'visitor') {
        $preferencesStatement = $pdo->prepare('SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1');
        $preferencesStatement->execute([(int) $viewer['id']]);
        $userPreferences = $preferencesStatement->fetch() ?: [];
        $applicationStatement = $pdo->prepare(
            "SELECT * FROM adoption_applications
            WHERE animal_id = ? AND user_id = ? AND status NOT IN ('declined','completed','cancelled')
            ORDER BY created_at DESC
            LIMIT 1"
        );
        $applicationStatement->execute([$animalId, (int) $viewer['id']]);
        $existingApplication = $applicationStatement->fetch() ?: null;
    }
} catch (Throwable $exception) {
    http_response_code(500);
    exit('The animal page could not be loaded.');
}

$success = flash('success');
$error = flash('error');
$acceptsApplications = in_array((string) $animal['status'], ['available', 'reserved', 'medical_hold'], true);
$openDialog = open_dialog_once();
$oldApplication = old_form('application');
$oldInquiry = old_form('inquiry');
$oldReport = old_form('report');
$applicationDefaults = [
    'name' => $oldApplication['name'] ?? ($viewer['name'] ?? ''),
    'email' => $oldApplication['email'] ?? ($viewer['email'] ?? ''),
    'phone' => $oldApplication['phone'] ?? '',
    'home_type' => $oldApplication['home_type'] ?? ($userPreferences['home_type'] ?? ''),
    'lifestyle' => $oldApplication['lifestyle'] ?? ($userPreferences['lifestyle'] ?? ''),
    'has_children' => array_key_exists('has_children', $oldApplication) ? !empty($oldApplication['has_children']) : !empty($userPreferences['has_children']),
    'has_pets' => array_key_exists('has_pets', $oldApplication) ? !empty($oldApplication['has_pets']) : !empty($userPreferences['has_pets']),
    'experience' => $oldApplication['experience'] ?? '',
    'message' => $oldApplication['message'] ?? '',
    'privacy_consent' => !empty($oldApplication['privacy_consent']),
];
$questionDefaults = is_array($oldApplication['question_answers'] ?? null) ? $oldApplication['question_answers'] : [];
$inquiryDefaults = [
    'name' => $oldInquiry['name'] ?? ($viewer['name'] ?? ''),
    'email' => $oldInquiry['email'] ?? ($viewer['email'] ?? ''),
    'phone' => $oldInquiry['phone'] ?? '',
    'message' => $oldInquiry['message'] ?? '',
    'privacy_consent' => !empty($oldInquiry['privacy_consent']),
];
$reportDefaults = [
    'reporter_name' => $oldReport['reporter_name'] ?? ($viewer['name'] ?? ''),
    'reporter_email' => $oldReport['reporter_email'] ?? ($viewer['email'] ?? ''),
    'reason' => $oldReport['reason'] ?? '',
    'privacy_consent' => !empty($oldReport['privacy_consent']),
];
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
        <?php if ($isPreview) : ?><div class="alert alert-warning">Preview mode. This listing is not publicly published from this preview.</div><?php endif; ?>
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
                    <img class="slide" src="<?php echo e(uploaded_url($image['file_path'])); ?>" alt="<?php echo e($animal['name']); ?>" loading="lazy" style="object-position: <?php echo e($image['crop_focus'] ?? 'center'); ?>;">
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
              <span class="badge <?php echo e(status_badge_class($animal['status'])); ?>"><?php echo e(status_label($animal['status'])); ?></span>
            </div>
            <p class="muted">Listed by <a href="<?php echo e(url('/shelter.php?slug=' . rawurlencode($animal['shelter_slug']))); ?>"><b><?php echo e($animal['shelter_name']); ?></b></a></p>
            <div class="quick">
              <div><b>Species</b><br><span class="muted"><?php echo e($animal['species']); ?></span></div>
              <div><b>Gender</b><br><span class="muted"><?php echo e($animal['gender'] ?: 'Unknown'); ?></span></div>
              <div><b>Color</b><br><span class="muted"><?php echo e($animal['color'] ?: 'Unknown'); ?></span></div>
              <div><b>Location</b><br><span class="muted"><?php echo e($animal['city'] ?: $animal['country'] ?: 'Not listed'); ?></span></div>
            </div>
            <div class="button-row">
              <?php if (!$isPreview) : ?>
                <?php if ($existingApplication) : ?>
                  <button class="btn green" type="button" disabled>Application submitted</button>
                <?php elseif ($acceptsApplications) : ?>
                  <button class="btn green" type="button" data-open-dialog="apply-dialog">Apply to adopt</button>
                <?php endif; ?>
                <button class="btn secondary" type="button" data-open-dialog="inquiry-dialog">Ask a question</button>
                <form method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                  <input type="hidden" name="animal_id" value="<?php echo e($animalId); ?>">
                  <input type="hidden" name="action" value="favorite">
                  <button class="btn secondary" type="submit" <?php echo $isSaved ? 'disabled' : ''; ?>><?php echo $isSaved ? 'Saved' : 'Save'; ?></button>
                </form>
              <?php endif; ?>
            </div>
            <?php if ($existingApplication) : ?>
              <p class="muted action-state">Application status: <span class="badge <?php echo e(status_badge_class($existingApplication['status'])); ?>"><?php echo e(status_label($existingApplication['status'])); ?></span></p>
            <?php elseif ($isSaved) : ?>
              <p class="muted action-state">Saved to your favourites.</p>
            <?php endif; ?>
          </div>

          <?php if (!$isPreview) : ?>
            <div class="panel">
              <h3>Share listing</h3>
              <?php $shareUrl = rtrim((string) config('base_url'), '/') . '/animal.php?id=' . $animalId; ?>
              <div class="button-row">
                <button class="btn secondary small" type="button" data-copy-text="<?php echo e($shareUrl); ?>">Copy link</button>
                <a class="btn secondary small" href="mailto:?subject=<?php echo rawurlencode('Adoption listing: ' . $animal['name']); ?>&body=<?php echo rawurlencode($shareUrl); ?>">Email</a>
                <a class="btn secondary small" href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($shareUrl); ?>" target="_blank" rel="noopener">Facebook</a>
                <button class="btn secondary small" type="button" data-open-dialog="report-dialog">Report</button>
              </div>
            </div>
          <?php endif; ?>

          <div class="panel">
            <h3>Why promoted?</h3>
            <p class="muted">Visibility score <?php echo e($visibility['score']); ?> balances waiting time, low views, low favorites, senior status, and vote wins.</p>
            <div class="timeline">
              <div class="step"><span class="num">1</span><div><b><?php echo e($visibility['days_listed']); ?> days listed</b><br><span class="muted">Longer wait can increase exposure.</span></div></div>
              <div class="step"><span class="num">2</span><div><b><?php echo e($visibility['views_count']); ?> views</b><br><span class="muted">Lower views can receive a boost.</span></div></div>
              <div class="step"><span class="num">3</span><div><b><?php echo e($visibility['vote_wins']); ?> vote wins</b><br><span class="muted">Community engagement supports discovery.</span></div></div>
            </div>
          </div>

          <?php if (!$isPreview) : ?>
            <dialog class="app-dialog large-dialog" id="apply-dialog" <?php echo $openDialog === 'apply-dialog' ? 'data-auto-open' : ''; ?>>
              <div class="dialog-shell">
                <header class="dialog-header">
                  <div><p class="eyebrow">Adoption application</p><h2>Apply for <?php echo e($animal['name']); ?></h2></div>
                  <button class="dialog-close" type="button" data-close-dialog>Close</button>
                </header>
                <?php if (!$acceptsApplications) : ?>
                  <div class="alert alert-warning">This listing is not currently accepting adoption applications.</div>
                <?php elseif ($existingApplication) : ?>
                  <div class="alert alert-success">Application already submitted. Current status: <?php echo e(status_label($existingApplication['status'])); ?>.</div>
                <?php elseif (!isLoggedIn()) : ?>
                  <div class="empty-state compact-empty">
                    <h4>Account required</h4>
                    <p class="muted">Create an adopter account to submit and track applications.</p>
                    <div class="button-row">
                      <a class="btn green small" href="<?php echo e(url('/register.php')); ?>">Create account</a>
                      <a class="btn secondary small" href="<?php echo e(url('/login.php')); ?>">Sign in</a>
                    </div>
                  </div>
                <?php elseif (currentUser()['role'] !== 'visitor') : ?>
                  <div class="alert alert-warning">Only adopter accounts can submit adoption applications.</div>
                <?php else : ?>
                  <form class="form" method="post">
                    <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                    <input type="hidden" name="animal_id" value="<?php echo e($animalId); ?>">
                    <input type="hidden" name="action" value="application">
                    <label class="visually-hidden" for="application-company">Company</label>
                    <input id="application-company" class="honeypot" name="company" tabindex="-1" autocomplete="off">
                    <div class="grid two-up">
                      <label><span>Name</span><input class="input" name="name" value="<?php echo e($applicationDefaults['name']); ?>" required></label>
                      <label><span>Email</span><input class="input" name="email" type="email" value="<?php echo e($applicationDefaults['email']); ?>" required></label>
                      <label><span>Phone</span><input class="input" name="phone" value="<?php echo e($applicationDefaults['phone']); ?>"></label>
                      <label><span>Home type</span><select class="input" name="home_type">
                        <option value="">Choose home type</option>
                        <?php foreach (['Apartment', 'House', 'House with garden', 'Farm or large outdoor space'] as $value) : ?>
                          <option value="<?php echo e($value); ?>" <?php echo selected($applicationDefaults['home_type'], $value); ?>><?php echo e($value); ?></option>
                        <?php endforeach; ?>
                      </select></label>
                      <label><span>Lifestyle</span><select class="input" name="lifestyle">
                        <option value="">Choose lifestyle</option>
                        <?php foreach (['Quiet', 'Active', 'Family', 'Senior-friendly'] as $value) : ?>
                          <option value="<?php echo e($value); ?>" <?php echo selected($applicationDefaults['lifestyle'], $value); ?>><?php echo e($value); ?></option>
                        <?php endforeach; ?>
                      </select></label>
                    </div>
                    <div class="checkbox-row">
                      <label><input type="checkbox" name="has_children" value="1" <?php echo checked($applicationDefaults['has_children']); ?>> Children at home</label>
                      <label><input type="checkbox" name="has_pets" value="1" <?php echo checked($applicationDefaults['has_pets']); ?>> Other pets at home</label>
                    </div>
                    <label><span>Previous pet experience</span><textarea class="input" name="experience" rows="3"><?php echo e($applicationDefaults['experience']); ?></textarea></label>
                    <label><span>Why this adoption could be a good match</span><textarea class="input" name="message" rows="5" required><?php echo e($applicationDefaults['message']); ?></textarea></label>
                    <?php if ($shelterQuestions !== []) : ?>
                      <section class="questionnaire-block">
                        <h3>Shelter questions</h3>
                        <?php foreach ($shelterQuestions as $question) : ?>
                          <?php
                            $questionId = (int) $question['id'];
                            $answerValue = (string) ($questionDefaults[$questionId] ?? '');
                            $options = array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n|,/', (string) ($question['choice_options'] ?? '')) ?: [])));
                          ?>
                          <label>
                            <span><?php echo e($question['question_text']); ?><?php echo (int) $question['is_required'] === 1 ? ' *' : ''; ?></span>
                            <?php if ($question['answer_type'] === 'yes_no') : ?>
                              <select class="input" name="question_answers[<?php echo e($questionId); ?>]" <?php echo (int) $question['is_required'] === 1 ? 'required' : ''; ?>>
                                <option value="">Choose</option>
                                <?php foreach (['Yes', 'No'] as $option) : ?>
                                  <option value="<?php echo e($option); ?>" <?php echo selected($answerValue, $option); ?>><?php echo e($option); ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php elseif ($question['answer_type'] === 'choice') : ?>
                              <select class="input" name="question_answers[<?php echo e($questionId); ?>]" <?php echo (int) $question['is_required'] === 1 ? 'required' : ''; ?>>
                                <option value="">Choose</option>
                                <?php foreach ($options as $option) : ?>
                                  <option value="<?php echo e($option); ?>" <?php echo selected($answerValue, $option); ?>><?php echo e($option); ?></option>
                                <?php endforeach; ?>
                              </select>
                            <?php else : ?>
                              <textarea class="input" name="question_answers[<?php echo e($questionId); ?>]" rows="3" <?php echo (int) $question['is_required'] === 1 ? 'required' : ''; ?>><?php echo e($answerValue); ?></textarea>
                            <?php endif; ?>
                          </label>
                        <?php endforeach; ?>
                      </section>
                    <?php endif; ?>
                    <label class="inline-check"><input type="checkbox" name="privacy_consent" value="1" <?php echo checked($applicationDefaults['privacy_consent']); ?> required> I agree that my application details can be shared with this shelter.</label>
                    <div class="dialog-actions"><button class="btn green" type="submit">Submit application</button></div>
                  </form>
                <?php endif; ?>
              </div>
            </dialog>

            <dialog class="app-dialog" id="inquiry-dialog" <?php echo $openDialog === 'inquiry-dialog' ? 'data-auto-open' : ''; ?>>
              <div class="dialog-shell">
                <header class="dialog-header">
                  <div><p class="eyebrow">Ask a question</p><h2><?php echo e($animal['name']); ?></h2></div>
                  <button class="dialog-close" type="button" data-close-dialog>Close</button>
                </header>
                <form class="form" method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                  <input type="hidden" name="animal_id" value="<?php echo e($animalId); ?>">
                  <input type="hidden" name="action" value="inquiry">
                  <label class="visually-hidden" for="company">Company</label>
                  <input id="company" class="honeypot" name="company" tabindex="-1" autocomplete="off">
                  <input class="input" name="name" value="<?php echo e($inquiryDefaults['name']); ?>" placeholder="Your name" required>
                  <input class="input" name="email" type="email" value="<?php echo e($inquiryDefaults['email']); ?>" placeholder="Email address" required>
                  <input class="input" name="phone" value="<?php echo e($inquiryDefaults['phone']); ?>" placeholder="Phone optional">
                  <textarea class="input" name="message" rows="5" placeholder="Tell the shelter about your question" required><?php echo e($inquiryDefaults['message']); ?></textarea>
                  <label class="inline-check"><input type="checkbox" name="privacy_consent" value="1" <?php echo checked($inquiryDefaults['privacy_consent']); ?> required> I agree that my message can be shared with this shelter.</label>
                  <div class="dialog-actions"><button class="btn green" type="submit">Submit inquiry</button></div>
                </form>
              </div>
            </dialog>

            <dialog class="app-dialog" id="report-dialog" <?php echo $openDialog === 'report-dialog' ? 'data-auto-open' : ''; ?>>
              <div class="dialog-shell">
                <header class="dialog-header">
                  <div><p class="eyebrow">Report listing</p><h2><?php echo e($animal['name']); ?></h2></div>
                  <button class="dialog-close" type="button" data-close-dialog>Close</button>
                </header>
                <form class="form" method="post">
                  <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
                  <input type="hidden" name="animal_id" value="<?php echo e($animalId); ?>">
                  <input type="hidden" name="action" value="report">
                  <label class="visually-hidden" for="report-company">Company</label>
                  <input id="report-company" class="honeypot" name="report_company" tabindex="-1" autocomplete="off">
                  <input class="input" name="reporter_name" value="<?php echo e($reportDefaults['reporter_name']); ?>" placeholder="Your name" required>
                  <input class="input" name="reporter_email" type="email" value="<?php echo e($reportDefaults['reporter_email']); ?>" placeholder="Email address" required>
                  <textarea class="input" name="reason" rows="4" placeholder="What should administrators review?" required><?php echo e($reportDefaults['reason']); ?></textarea>
                  <label class="inline-check"><input type="checkbox" name="privacy_consent" value="1" <?php echo checked($reportDefaults['privacy_consent']); ?> required> I agree that this report can be reviewed by administrators.</label>
                  <div class="dialog-actions"><button class="btn secondary" type="submit">Submit report</button></div>
                </form>
              </div>
            </dialog>
          <?php endif; ?>
        </aside>
      </div>
    </section>
  </main>
</body>
</html>
