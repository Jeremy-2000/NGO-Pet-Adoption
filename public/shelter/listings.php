<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('shelter');

$error = '';

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);
    $animalRepository = new AnimalRepository($pdo);
    $shelter = $shelterRepository->findByUserId((int) currentUser()['id']);

    if (!$shelter) {
        http_response_code(404);
        exit('Shelter profile not found.');
    }

    $canPublish = $shelter['status'] === 'approved';
    $editAnimal = null;

    if (!empty($_GET['edit'])) {
        $editAnimal = $animalRepository->findForShelter((int) $_GET['edit'], (int) $shelter['id']);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();

        if (!$canPublish) {
            flash('error', 'Only approved shelters can create or update listings.');
            redirect('/shelter/listings.php');
        }

        try {
            $animalId = (int) ($_POST['animal_id'] ?? 0);

            if ($animalId > 0) {
                $animalRepository->update($animalId, (int) $shelter['id'], $_POST);
                audit_log($pdo, 'animal.updated', 'animal', $animalId);
            } else {
                $animalId = $animalRepository->create((int) $shelter['id'], $_POST);
                audit_log($pdo, 'animal.created', 'animal', $animalId);
            }

            if (!empty($_FILES['photos']['name'][0])) {
                $uploadService = new UploadService($pdo, config('uploads', []));
                $uploadService->storeAnimalImages($_FILES['photos'], $animalId);
                audit_log($pdo, 'animal.images_uploaded', 'animal', $animalId);
            }

            flash('success', 'Listing saved.');
            redirect('/shelter/listings.php');
        } catch (Throwable $exception) {
            $error = $exception->getMessage();
        }
    }

    $animals = $animalRepository->forShelter((int) $shelter['id']);
} catch (Throwable) {
    http_response_code(500);
    exit('Listings could not be loaded.');
}

$success = flash('success');
$flashError = flash('error');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Listings | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/shelter/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/shelter/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/shelter/profile.php')); ?>">Profile</a>
        <a class="active" href="<?php echo e(url('/shelter/listings.php')); ?>">Listings</a>
        <a href="<?php echo e(url('/shelter/inquiries.php')); ?>">Inquiries</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header">
        <div><p class="eyebrow">Shelter portal</p><h1>Animal listings</h1></div>
      </header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($flashError) : ?><div class="alert alert-error"><?php echo e($flashError); ?></div><?php endif; ?>
      <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>
      <?php if (!$canPublish) : ?><div class="alert alert-warning">Your shelter must be approved before listings can be published.</div><?php endif; ?>

      <section class="card">
        <h2><?php echo $editAnimal ? 'Edit listing' : 'Create a listing'; ?></h2>
        <form method="post" enctype="multipart/form-data" class="form">
          <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
          <?php if ($editAnimal) : ?><input type="hidden" name="animal_id" value="<?php echo e($editAnimal['id']); ?>"><?php endif; ?>
          <div class="grid two-up">
            <label><span>Name</span><input class="input" type="text" name="name" value="<?php echo e($editAnimal['name'] ?? ''); ?>" <?php echo $canPublish ? 'required' : 'disabled'; ?>></label>
            <label><span>Species</span><input class="input" type="text" name="species" value="<?php echo e($editAnimal['species'] ?? ''); ?>" <?php echo $canPublish ? 'required' : 'disabled'; ?>></label>
            <label><span>Breed</span><input class="input" type="text" name="breed" value="<?php echo e($editAnimal['breed'] ?? ''); ?>" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
            <label><span>Age</span><input class="input" type="text" name="age" value="<?php echo e($editAnimal['age'] ?? ''); ?>" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
            <label><span>Gender</span><input class="input" type="text" name="gender" value="<?php echo e($editAnimal['gender'] ?? ''); ?>" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
            <label><span>Size</span><input class="input" type="text" name="size" value="<?php echo e($editAnimal['size'] ?? ''); ?>" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
            <label><span>Color</span><input class="input" type="text" name="color" value="<?php echo e($editAnimal['color'] ?? ''); ?>" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
            <label><span>Status</span><select class="input" name="status" <?php echo $canPublish ? '' : 'disabled'; ?>>
              <?php foreach (['available', 'reserved', 'adopted', 'medical_hold'] as $status) : ?>
                <option value="<?php echo e($status); ?>" <?php echo selected($editAnimal['status'] ?? 'available', $status); ?>><?php echo e(status_label($status)); ?></option>
              <?php endforeach; ?>
            </select></label>
            <label><span>Energy level</span><input class="input" type="text" name="energy_level" value="<?php echo e($editAnimal['energy_level'] ?? ''); ?>" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
            <label><span>Video URL</span><input class="input" type="url" name="video_url" value="<?php echo e($editAnimal['video_url'] ?? ''); ?>" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
          </div>
          <label><span>Temperament</span><textarea class="input" name="temperament" <?php echo $canPublish ? '' : 'disabled'; ?>><?php echo e($editAnimal['temperament'] ?? ''); ?></textarea></label>
          <label><span>Medical conditions</span><textarea class="input" name="medical_conditions" <?php echo $canPublish ? '' : 'disabled'; ?>><?php echo e($editAnimal['medical_conditions'] ?? ''); ?></textarea></label>
          <label><span>Special needs</span><textarea class="input" name="special_needs" <?php echo $canPublish ? '' : 'disabled'; ?>><?php echo e($editAnimal['special_needs'] ?? ''); ?></textarea></label>
          <div class="checkbox-row">
            <label><input type="checkbox" name="good_with_children" value="1" <?php echo checked($editAnimal['good_with_children'] ?? false); ?> <?php echo $canPublish ? '' : 'disabled'; ?>> Good with children</label>
            <label><input type="checkbox" name="good_with_dogs" value="1" <?php echo checked($editAnimal['good_with_dogs'] ?? false); ?> <?php echo $canPublish ? '' : 'disabled'; ?>> Good with dogs</label>
            <label><input type="checkbox" name="good_with_cats" value="1" <?php echo checked($editAnimal['good_with_cats'] ?? false); ?> <?php echo $canPublish ? '' : 'disabled'; ?>> Good with cats</label>
            <label><input type="checkbox" name="vaccinated" value="1" <?php echo checked($editAnimal['vaccinated'] ?? false); ?> <?php echo $canPublish ? '' : 'disabled'; ?>> Vaccinated</label>
            <label><input type="checkbox" name="spayed_neutered" value="1" <?php echo checked($editAnimal['spayed_neutered'] ?? false); ?> <?php echo $canPublish ? '' : 'disabled'; ?>> Spayed/neutered</label>
            <label><input type="checkbox" name="is_senior" value="1" <?php echo checked($editAnimal['is_senior'] ?? false); ?> <?php echo $canPublish ? '' : 'disabled'; ?>> Senior animal</label>
          </div>
          <label><span>Photos</span><input class="input" type="file" name="photos[]" multiple accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" <?php echo $canPublish ? '' : 'disabled'; ?>></label>
          <button class="btn green" type="submit" <?php echo $canPublish ? '' : 'disabled'; ?>>Save listing</button>
        </form>
      </section>

      <section class="card">
        <h2>Existing listings</h2>
        <div class="table-wrap">
          <table class="table">
            <thead><tr><th>Animal</th><th>Status</th><th>Views</th><th>Favorites</th><th>Action</th></tr></thead>
            <tbody>
              <?php foreach ($animals as $animal) : ?>
                <tr>
                  <td><strong><?php echo e($animal['name']); ?></strong><br><span class="muted"><?php echo e($animal['species']); ?> - <?php echo e($animal['breed'] ?: 'Mixed breed'); ?></span></td>
                  <td><?php echo e(status_label($animal['status'])); ?></td>
                  <td><?php echo e($animal['views_count']); ?></td>
                  <td><?php echo e($animal['favorites_count']); ?></td>
                  <td><a class="btn secondary small" href="<?php echo e(url('/shelter/listings.php?edit=' . $animal['id'])); ?>">Edit</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</body>
</html>
