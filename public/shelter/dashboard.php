<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('shelter');

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);
    $animalRepository = new AnimalRepository($pdo);
    $shelter = $shelterRepository->findByUserId((int) currentUser()['id']);

    if (!$shelter) {
        http_response_code(404);
        exit('Shelter profile not found.');
    }

    $animals = $animalRepository->forShelter((int) $shelter['id']);
    $stats = [
        'Listings' => count($animals),
        'Available' => count(array_filter($animals, static fn (array $animal): bool => $animal['status'] === 'available')),
    ];
    $inquiryCount = $pdo->prepare('SELECT COUNT(*) FROM inquiries WHERE shelter_id = ?');
    $inquiryCount->execute([(int) $shelter['id']]);
    $newInquiries = $pdo->prepare("SELECT COUNT(*) FROM inquiries WHERE shelter_id = ? AND status = 'new'");
    $newInquiries->execute([(int) $shelter['id']]);
    $applicationCount = $pdo->prepare('SELECT COUNT(*) FROM adoption_applications WHERE shelter_id = ?');
    $applicationCount->execute([(int) $shelter['id']]);
    $newApplications = $pdo->prepare("SELECT COUNT(*) FROM adoption_applications WHERE shelter_id = ? AND status IN ('new','reviewing')");
    $newApplications->execute([(int) $shelter['id']]);
    $listingsNeedingPhotos = $pdo->prepare(
        'SELECT COUNT(*) FROM animals a
        WHERE a.shelter_id = ? AND NOT EXISTS (SELECT 1 FROM animal_images ai WHERE ai.animal_id = a.id)'
    );
    $listingsNeedingPhotos->execute([(int) $shelter['id']]);
    $stats['Inquiries'] = (int) $inquiryCount->fetchColumn();
    $stats['New inquiries'] = (int) $newInquiries->fetchColumn();
    $stats['Applications'] = (int) $applicationCount->fetchColumn();
    $stats['Needs review'] = (int) $newApplications->fetchColumn();
    $tasks = [
        ['label' => 'New inquiries', 'count' => (int) $stats['New inquiries'], 'href' => '/shelter/inquiries.php'],
        ['label' => 'Applications needing review', 'count' => (int) $stats['Needs review'], 'href' => '/shelter/applications.php'],
        ['label' => 'Listings missing photos', 'count' => (int) $listingsNeedingPhotos->fetchColumn(), 'href' => '/shelter/listings.php'],
        ['label' => 'Profile missing location', 'count' => ($shelter['city'] || $shelter['country']) ? 0 : 1, 'href' => '/shelter/profile.php'],
    ];
    $appointments = $pdo->prepare(
        "SELECT ap.*, a.name AS animal_name
        FROM appointments ap
        LEFT JOIN animals a ON a.id = ap.animal_id
        WHERE ap.shelter_id = ? AND ap.status = 'scheduled' AND ap.appointment_at >= NOW()
        ORDER BY ap.appointment_at ASC
        LIMIT 5"
    );
    $appointments->execute([(int) $shelter['id']]);
    $appointments = $appointments->fetchAll();
} catch (Throwable) {
    http_response_code(500);
    exit('Shelter dashboard could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shelter Dashboard | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/shelter/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a class="active" href="<?php echo e(url('/shelter/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/shelter/profile.php')); ?>">Profile</a>
        <a href="<?php echo e(url('/shelter/listings.php')); ?>">Listings</a>
        <a href="<?php echo e(url('/shelter/inquiries.php')); ?>">Inquiries</a>
        <a href="<?php echo e(url('/shelter/applications.php')); ?>">Applications</a>
        <a href="<?php echo e(url('/shelter/questions.php')); ?>">Questions</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header">
        <div>
          <p class="eyebrow">Shelter portal</p>
          <h1><?php echo e($shelter['name']); ?></h1>
          <p class="muted">Status: <span class="badge <?php echo e(status_badge_class($shelter['status'])); ?>"><?php echo e(status_label($shelter['status'])); ?></span></p>
        </div>
        <a class="btn secondary" href="<?php echo e(url('/shelter/profile.php')); ?>">Edit profile</a>
      </header>

      <?php if ($shelter['status'] !== 'approved') : ?>
        <div class="alert alert-warning">Only approved shelters can publish animal listings. Your current status is <?php echo e(status_label($shelter['status'])); ?>.</div>
      <?php endif; ?>

      <section class="stats-grid">
        <?php foreach ($stats as $label => $value) : ?>
          <article class="card stat-card">
            <span class="muted"><?php echo e($label); ?></span>
            <b><?php echo e(number_format($value)); ?></b>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="grid two-up">
        <article class="card">
          <h2>Task queue</h2>
          <ul class="list task-list">
            <?php foreach ($tasks as $task) : ?>
              <li>
                <a href="<?php echo e(url($task['href'])); ?>">
                  <strong><?php echo e($task['label']); ?></strong>
                  <span class="badge <?php echo (int) $task['count'] > 0 ? 'pending' : 'approved'; ?>"><?php echo e((string) $task['count']); ?></span>
                </a>
              </li>
            <?php endforeach; ?>
          </ul>
        </article>
        <article class="card">
          <h2>Publishing checklist</h2>
          <ul class="list">
            <li>Approval status: <strong><?php echo e(status_label($shelter['status'])); ?></strong></li>
            <li>Contact email: <strong><?php echo e($shelter['contact_email'] ?: 'Missing'); ?></strong></li>
            <li>Profile location: <strong><?php echo e($shelter['city'] ?: $shelter['country'] ?: 'Missing'); ?></strong></li>
          </ul>
        </article>
      </section>

      <section class="grid two-up">
        <article class="card">
          <h2>Recent listings</h2>
          <?php if ($animals === []) : ?>
            <div class="empty-state compact-empty"><h3>No listings yet.</h3><p class="muted">Create the first listing once your shelter is approved.</p></div>
          <?php else : ?>
            <ul class="list">
              <?php foreach (array_slice($animals, 0, 6) as $animal) : ?>
                <li>
                  <strong><?php echo e($animal['name']); ?></strong>
                  <span><span class="badge <?php echo e(status_badge_class($animal['status'])); ?>"><?php echo e(status_label($animal['status'])); ?></span> <span class="muted"><?php echo e($animal['views_count']); ?> views</span></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
        <article class="card">
          <h2>Upcoming appointments</h2>
          <?php if ($appointments === []) : ?>
            <div class="empty-state compact-empty"><h3>No scheduled appointments.</h3><p class="muted">Meet-and-greet times saved on applications will appear here.</p></div>
          <?php else : ?>
            <ul class="list">
              <?php foreach ($appointments as $appointment) : ?>
                <li>
                  <strong><?php echo e($appointment['title']); ?></strong>
                  <span class="muted"><?php echo e($appointment['animal_name'] ?: 'General'); ?> - <?php echo e($appointment['appointment_at']); ?></span>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </article>
      </section>
    </main>
  </div>
</body>
</html>
