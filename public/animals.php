<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

try {
    $pdo = db();
    $repository = new AnimalRepository($pdo);
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = (int) config('pagination.per_page', 12);
    $filters = [
        'q' => trim((string) ($_GET['q'] ?? '')),
        'species' => trim((string) ($_GET['species'] ?? '')),
        'breed' => trim((string) ($_GET['breed'] ?? '')),
        'age' => trim((string) ($_GET['age'] ?? '')),
        'gender' => trim((string) ($_GET['gender'] ?? '')),
        'size' => trim((string) ($_GET['size'] ?? '')),
        'location' => trim((string) ($_GET['location'] ?? '')),
        'status' => trim((string) ($_GET['status'] ?? '')),
        'special_needs' => !empty($_GET['special_needs']),
        'child_friendly' => !empty($_GET['child_friendly']),
    ];
    $result = $repository->search($filters, $page, $perPage);
    $visibilityService = new VisibilityService(config('visibility.weights', []), config('visibility.limits', []));
    $animals = $result['items'];

    foreach ($animals as $index => $animal) {
        $animals[$index]['visibility_score'] = $visibilityService->score($animal);
    }

    $totalPages = max(1, (int) ceil($result['total'] / $result['per_page']));
} catch (Throwable) {
    http_response_code(500);
    exit('Database connection failed. Run the installer first.');
}

$queryWithoutPage = $_GET;
unset($queryWithoutPage['page']);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Browse Animals | <?php echo e(config('app_name')); ?></title>
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
        <h1>Browse adoptable animals</h1>
        <p class="lead muted">Search, filter, compare, and discover animals from approved shelters.</p>
        <form class="filters" method="get" action="<?php echo e(url('/animals.php')); ?>">
          <input class="input" name="q" value="<?php echo e($filters['q']); ?>" placeholder="Keyword">
          <input class="input" name="species" value="<?php echo e($filters['species']); ?>" placeholder="Species">
          <input class="input" name="breed" value="<?php echo e($filters['breed']); ?>" placeholder="Breed">
          <input class="input" name="age" value="<?php echo e($filters['age']); ?>" placeholder="Age">
          <input class="input" name="location" value="<?php echo e($filters['location']); ?>" placeholder="Location">
          <select class="input" name="gender">
            <option value="">Any gender</option>
            <?php foreach (['Female', 'Male', 'Unknown'] as $gender) : ?>
              <option value="<?php echo e($gender); ?>" <?php echo selected($filters['gender'], $gender); ?>><?php echo e($gender); ?></option>
            <?php endforeach; ?>
          </select>
          <select class="input" name="size">
            <option value="">Any size</option>
            <?php foreach (['Small', 'Medium', 'Large', 'Extra large'] as $size) : ?>
              <option value="<?php echo e($size); ?>" <?php echo selected($filters['size'], $size); ?>><?php echo e($size); ?></option>
            <?php endforeach; ?>
          </select>
          <select class="input" name="status">
            <option value="">Adoptable status</option>
            <?php foreach (['available', 'reserved', 'medical_hold', 'adopted'] as $status) : ?>
              <option value="<?php echo e($status); ?>" <?php echo selected($filters['status'], $status); ?>><?php echo e(status_label($status)); ?></option>
            <?php endforeach; ?>
          </select>
          <label class="check-filter"><input type="checkbox" name="special_needs" value="1" <?php echo checked($filters['special_needs']); ?>> Special needs</label>
          <label class="check-filter"><input type="checkbox" name="child_friendly" value="1" <?php echo checked($filters['child_friendly']); ?>> Child friendly</label>
          <button class="btn green" type="submit">Apply filters</button>
        </form>
      </div>
    </section>

    <section class="section">
      <div class="wrap">
        <div class="section-head">
          <p class="muted"><?php echo e(number_format($result['total'])); ?> animals found</p>
        </div>
        <?php if ($animals === []) : ?>
          <div class="empty-state">
            <h2>No animals match those filters yet.</h2>
            <p class="muted">Try a wider keyword, species, or location search.</p>
          </div>
        <?php endif; ?>
        <div class="grid cards">
          <?php foreach ($animals as $animal) : ?>
            <article class="animal-card" data-animate>
              <a class="media" href="<?php echo e(url('/animal.php?id=' . $animal['id'])); ?>">
                <?php if ((float) $animal['visibility_score'] >= 0.6 || (int) $animal['is_featured'] === 1) : ?><span class="badge promoted">Promoted</span><?php endif; ?>
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
                <p class="muted"><?php echo e($animal['species']); ?> - <?php echo e($animal['breed'] ?: 'Mixed breed'); ?> - <?php echo e($animal['shelter_name']); ?></p>
                <div class="meta">
                  <?php if ($animal['age']) : ?><span class="pill"><?php echo e($animal['age']); ?></span><?php endif; ?>
                  <?php if ($animal['size']) : ?><span class="pill"><?php echo e($animal['size']); ?></span><?php endif; ?>
                  <?php if ((int) $animal['good_with_children'] === 1) : ?><span class="pill">Child friendly</span><?php endif; ?>
                </div>
                <small class="muted">Visibility score <?php echo e($animal['visibility_score']); ?></small>
              </div>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1) : ?>
          <nav class="pagination" aria-label="Animal pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++) : ?>
              <?php $query = http_build_query(array_merge($queryWithoutPage, ['page' => $i])); ?>
              <a class="<?php echo $i === $page ? 'active' : ''; ?>" href="<?php echo e(url('/animals.php?' . $query)); ?>"><?php echo e($i); ?></a>
            <?php endfor; ?>
          </nav>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
