<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
requireRole('visitor');

$success = flash('success');
$error = flash('error');

try {
    $pdo = db();
    $animalRepository = new AnimalRepository($pdo);
    $user = currentUser();
    $userId = (int) $user['id'];
    $speciesOptions = taxonomy_values($pdo, 'species', ['Dog', 'Cat', 'Rabbit', 'Bird']);
    $sizeOptions = taxonomy_values($pdo, 'size', ['Small', 'Medium', 'Large', 'Extra large']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $statement = $pdo->prepare(
            'INSERT INTO user_preferences
                (user_id, lifestyle, home_type, has_children, has_pets, preferred_species, preferred_size, preferred_age)
            VALUES
                (:user_id, :lifestyle, :home_type, :has_children, :has_pets, :preferred_species, :preferred_size, :preferred_age)
            ON DUPLICATE KEY UPDATE
                lifestyle = VALUES(lifestyle),
                home_type = VALUES(home_type),
                has_children = VALUES(has_children),
                has_pets = VALUES(has_pets),
                preferred_species = VALUES(preferred_species),
                preferred_size = VALUES(preferred_size),
                preferred_age = VALUES(preferred_age)'
        );
        $statement->execute([
            'user_id' => $userId,
            'lifestyle' => substr(trim((string) ($_POST['lifestyle'] ?? '')), 0, 80) ?: null,
            'home_type' => substr(trim((string) ($_POST['home_type'] ?? '')), 0, 80) ?: null,
            'has_children' => !empty($_POST['has_children']) ? 1 : 0,
            'has_pets' => !empty($_POST['has_pets']) ? 1 : 0,
            'preferred_species' => substr(trim((string) ($_POST['preferred_species'] ?? '')), 0, 80) ?: null,
            'preferred_size' => in_array(($_POST['preferred_size'] ?? ''), $sizeOptions, true) ? $_POST['preferred_size'] : null,
            'preferred_age' => substr(trim((string) ($_POST['preferred_age'] ?? '')), 0, 80) ?: null,
        ]);
        audit_log($pdo, 'preferences.updated', 'user', $userId);
        flash('success', 'Matching preferences saved.');
        redirect('/account.php');
    }

    $preferences = $pdo->prepare('SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1');
    $preferences->execute([$userId]);
    $preferences = $preferences->fetch() ?: [];

    $applications = $pdo->prepare(
        'SELECT aa.*, a.name AS animal_name, a.status AS animal_status, s.name AS shelter_name
        FROM adoption_applications aa
        INNER JOIN animals a ON a.id = aa.animal_id
        INNER JOIN shelters s ON s.id = aa.shelter_id
        WHERE aa.user_id = ?
        ORDER BY aa.created_at DESC'
    );
    $applications->execute([$userId]);
    $applications = $applications->fetchAll();
    $favorites = $animalRepository->favoritesForViewer($userId, session_id(), 6);
    $recentlyViewed = $animalRepository->recentlyViewedForViewer($userId, session_id(), 6);
    $suggestions = $animalRepository->suggestedForUser($userId, 6);
} catch (Throwable) {
    http_response_code(500);
    exit('Account dashboard could not be loaded.');
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Account | <?php echo e(config('app_name')); ?></title>
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
        <a href="<?php echo e(url('/shelters.php')); ?>">Shelters</a>
        <a class="active" href="<?php echo e(url('/account.php')); ?>">My account</a>
      </nav>
      <div class="actions"><a class="btn secondary" href="<?php echo e(url('/logout.php')); ?>">Logout</a></div>
    </div>
  </header>

  <main>
    <section class="page-title">
      <div class="wrap">
        <p class="eyebrow">Adopter dashboard</p>
        <h1>Welcome, <?php echo e($user['name']); ?></h1>
        <p class="lead muted">Track applications, save favourites, and tune your matching preferences.</p>
      </div>
    </section>

    <section class="section compact">
      <div class="wrap">
        <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
        <?php if ($error) : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

        <section class="card">
          <div class="section-head table-section-head">
            <div>
              <h2>Application status</h2>
              <p class="muted">Follow each adoption request without leaving the site.</p>
            </div>
          </div>
          <?php if ($applications === []) : ?>
            <div class="empty-state compact-empty">
              <h3>No adoption applications yet.</h3>
              <p class="muted">When you apply for a listing, the status will appear here.</p>
              <a class="btn green small" href="<?php echo e(url('/animals.php')); ?>">Browse listings</a>
            </div>
          <?php else : ?>
            <div class="table-wrap">
              <table class="table" data-enhanced-table data-table-key="account-applications" data-table-empty="No applications match these filters.">
                <thead><tr><th>Animal</th><th>Shelter</th><th>Status</th><th>Appointment</th><th>Submitted</th></tr></thead>
                <tbody>
                  <?php foreach ($applications as $application) : ?>
                    <tr>
                      <td><strong><?php echo e($application['animal_name']); ?></strong><br><span class="muted"><?php echo e(status_label($application['animal_status'])); ?></span></td>
                      <td><?php echo e($application['shelter_name']); ?></td>
                      <td><span class="badge <?php echo e(status_badge_class($application['status'])); ?>"><?php echo e(status_label($application['status'])); ?></span></td>
                      <td><?php echo e($application['appointment_at'] ?: 'Not scheduled'); ?></td>
                      <td><?php echo e($application['created_at']); ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          <?php endif; ?>
        </section>

        <section class="grid two-up account-grid">
          <article class="card">
            <h2>Matching questionnaire</h2>
            <form method="post" class="form">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <label><span>Lifestyle</span><select class="input" name="lifestyle">
                <?php foreach (['', 'Quiet', 'Active', 'Family', 'Senior-friendly'] as $value) : ?>
                  <option value="<?php echo e($value); ?>" <?php echo selected($preferences['lifestyle'] ?? '', $value); ?>><?php echo e($value === '' ? 'Any lifestyle' : $value); ?></option>
                <?php endforeach; ?>
              </select></label>
              <label><span>Home type</span><select class="input" name="home_type">
                <?php foreach (['', 'Apartment', 'House', 'House with garden', 'Farm or large outdoor space'] as $value) : ?>
                  <option value="<?php echo e($value); ?>" <?php echo selected($preferences['home_type'] ?? '', $value); ?>><?php echo e($value === '' ? 'Any home type' : $value); ?></option>
                <?php endforeach; ?>
              </select></label>
              <div class="grid two-up">
                <label><span>Preferred species</span><select class="input" name="preferred_species">
                  <option value="">Any species</option>
                  <?php foreach ($speciesOptions as $species) : ?>
                    <option value="<?php echo e($species); ?>" <?php echo selected($preferences['preferred_species'] ?? '', $species); ?>><?php echo e($species); ?></option>
                  <?php endforeach; ?>
                </select></label>
                <label><span>Preferred size</span><select class="input" name="preferred_size">
                  <option value="">Any size</option>
                  <?php foreach ($sizeOptions as $size) : ?>
                    <option value="<?php echo e($size); ?>" <?php echo selected($preferences['preferred_size'] ?? '', $size); ?>><?php echo e($size); ?></option>
                  <?php endforeach; ?>
                </select></label>
              </div>
              <label><span>Preferred age</span><input class="input" name="preferred_age" value="<?php echo e($preferences['preferred_age'] ?? ''); ?>" placeholder="Puppy, adult, senior, etc."></label>
              <div class="checkbox-row">
                <label><input type="checkbox" name="has_children" value="1" <?php echo checked($preferences['has_children'] ?? false); ?>> Children at home</label>
                <label><input type="checkbox" name="has_pets" value="1" <?php echo checked($preferences['has_pets'] ?? false); ?>> Other pets at home</label>
              </div>
              <button class="btn green" type="submit">Save preferences</button>
            </form>
          </article>

          <article class="card">
            <h2>Suggested listings</h2>
            <?php if ($suggestions === []) : ?>
              <div class="empty-state compact-empty">
                <h3>No suggestions yet.</h3>
                <p class="muted">Save matching preferences to improve recommendations.</p>
              </div>
            <?php else : ?>
              <ul class="media-list">
                <?php foreach ($suggestions as $animal) : ?>
                  <li>
                    <a href="<?php echo e(url('/animal.php?id=' . $animal['id'])); ?>">
                      <?php if ($animal['thumbnail_path'] ?? $animal['image_path'] ?? '') : ?>
                        <img src="<?php echo e(uploaded_url($animal['thumbnail_path'] ?: $animal['image_path'])); ?>" alt="<?php echo e($animal['name']); ?>" style="object-position: <?php echo e($animal['image_crop_focus'] ?? 'center'); ?>;">
                      <?php else : ?>
                        <span class="mini-placeholder"><?php echo e(substr($animal['species'], 0, 1)); ?></span>
                      <?php endif; ?>
                      <span><strong><?php echo e($animal['name']); ?></strong><small><?php echo e($animal['species']); ?> - <?php echo e($animal['shelter_name']); ?></small></span>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </section>

        <section class="grid two-up account-grid">
          <article class="card">
            <h2>Saved favourites</h2>
            <?php if ($favorites === []) : ?>
              <div class="empty-state compact-empty"><h3>No saved listings yet.</h3><p class="muted">Use Save on listings you want to revisit.</p></div>
            <?php else : ?>
              <ul class="media-list">
                <?php foreach ($favorites as $animal) : ?>
                  <li><a href="<?php echo e(url('/animal.php?id=' . $animal['id'])); ?>"><strong><?php echo e($animal['name']); ?></strong><small><?php echo e($animal['shelter_name']); ?> - <?php echo e(status_label($animal['status'])); ?></small></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>

          <article class="card">
            <h2>Recently viewed</h2>
            <?php if ($recentlyViewed === []) : ?>
              <div class="empty-state compact-empty"><h3>No recently viewed listings.</h3><p class="muted">Listings open here after you view animal profiles.</p></div>
            <?php else : ?>
              <ul class="media-list">
                <?php foreach ($recentlyViewed as $animal) : ?>
                  <li><a href="<?php echo e(url('/animal.php?id=' . $animal['id'])); ?>"><strong><?php echo e($animal['name']); ?></strong><small><?php echo e($animal['shelter_name']); ?> - viewed <?php echo e($animal['viewed_at']); ?></small></a></li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </article>
        </section>
      </div>
    </section>
  </main>
</body>
</html>
