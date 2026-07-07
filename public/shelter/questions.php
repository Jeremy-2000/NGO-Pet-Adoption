<?php
declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
requireRole('shelter');

$error = '';

try {
    $pdo = db();
    $shelterRepository = new ShelterRepository($pdo);
    $shelter = $shelterRepository->findByUserId((int) currentUser()['id']);

    if (!$shelter) {
        http_response_code(404);
        exit('Shelter profile not found.');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrf();
        $id = (int) ($_POST['question_id'] ?? 0);
        $questionText = substr(trim((string) ($_POST['question_text'] ?? '')), 0, 255);
        $answerType = (string) ($_POST['answer_type'] ?? 'free_text');
        $choiceOptions = trim((string) ($_POST['choice_options'] ?? '')) ?: null;
        $sortOrder = max(0, (int) ($_POST['sort_order'] ?? 100));
        $isRequired = !empty($_POST['is_required']) ? 1 : 0;
        $isActive = !empty($_POST['is_active']) ? 1 : 0;

        if ($questionText === '') {
            $error = 'Question text is required.';
        } elseif (!in_array($answerType, ['yes_no', 'free_text', 'choice'], true)) {
            $error = 'Choose a valid answer type.';
        } elseif ($answerType === 'choice' && $choiceOptions === null) {
            $error = 'Dropdown questions need at least one option.';
        } elseif ($id > 0) {
            $statement = $pdo->prepare(
                'UPDATE shelter_questions
                SET question_text = ?, answer_type = ?, choice_options = ?, sort_order = ?, is_required = ?, is_active = ?
                WHERE id = ? AND shelter_id = ?'
            );
            $statement->execute([$questionText, $answerType, $choiceOptions, $sortOrder, $isRequired, $isActive, $id, (int) $shelter['id']]);
            audit_log($pdo, 'question.updated', 'question', $id);
            flash('success', 'Question updated.');
            redirect('/shelter/questions.php');
        } else {
            $statement = $pdo->prepare(
                'INSERT INTO shelter_questions (shelter_id, question_text, answer_type, choice_options, sort_order, is_required, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $statement->execute([(int) $shelter['id'], $questionText, $answerType, $choiceOptions, $sortOrder, $isRequired, $isActive]);
            audit_log($pdo, 'question.created', 'question', (int) $pdo->lastInsertId());
            flash('success', 'Question added.');
            redirect('/shelter/questions.php');
        }
    }

    $questions = $pdo->prepare('SELECT * FROM shelter_questions WHERE shelter_id = ? ORDER BY sort_order ASC, id ASC');
    $questions->execute([(int) $shelter['id']]);
    $questions = $questions->fetchAll();
} catch (Throwable $exception) {
    if ($error === '') {
        $error = $exception->getMessage();
    }
    $questions = [];
}

$success = flash('success');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Application Questions | <?php echo e(config('app_name')); ?></title>
  <link rel="stylesheet" href="<?php echo e(asset('css/styles.css')); ?>">
  <script defer src="<?php echo e(asset('js/app.js')); ?>"></script>
</head>
<body>
  <div class="app-shell">
    <aside class="sidebar">
      <a class="brand inverse" href="<?php echo e(url('/shelter/dashboard.php')); ?>"><span class="brand-mark">PA</span>Pet Adoption</a>
      <nav>
        <a href="<?php echo e(url('/shelter/dashboard.php')); ?>">Dashboard</a>
        <a href="<?php echo e(url('/shelter/profile.php')); ?>">Profile</a>
        <a href="<?php echo e(url('/shelter/listings.php')); ?>">Listings</a>
        <a href="<?php echo e(url('/shelter/inquiries.php')); ?>">Inquiries</a>
        <a href="<?php echo e(url('/shelter/applications.php')); ?>">Applications</a>
        <a class="active" href="<?php echo e(url('/shelter/questions.php')); ?>">Questions</a>
        <a href="<?php echo e(url('/logout.php')); ?>">Logout</a>
      </nav>
    </aside>
    <main class="content">
      <header class="page-header"><div><p class="eyebrow">Shelter portal</p><h1>Application questions</h1></div></header>
      <?php if ($success) : ?><div class="alert alert-success"><?php echo e($success); ?></div><?php endif; ?>
      <?php if ($error !== '') : ?><div class="alert alert-error"><?php echo e($error); ?></div><?php endif; ?>

      <section class="grid two-up">
        <article class="card">
          <h2>Add question</h2>
          <form method="post" class="form">
            <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
            <label><span>Question</span><input class="input" name="question_text" required></label>
            <label><span>Answer type</span><select class="input" name="answer_type" data-answer-type>
              <option value="free_text">Free text</option>
              <option value="yes_no">Yes/No</option>
              <option value="choice">Choice/dropdown</option>
            </select></label>
            <label data-choice-options><span>Choice options</span><textarea class="input" name="choice_options" rows="4" placeholder="One option per line"></textarea></label>
            <label><span>Sort order</span><input class="input" type="number" min="0" name="sort_order" value="100"></label>
            <div class="checkbox-row">
              <label><input type="checkbox" name="is_required" value="1"> Required</label>
              <label><input type="checkbox" name="is_active" value="1" checked> Active</label>
            </div>
            <button class="btn green" type="submit">Add question</button>
          </form>
        </article>
        <article class="card">
          <h2>Applicant experience</h2>
          <p class="muted">Active questions are added to every adoption application for this shelter. Dropdown options can be separated by new lines or commas.</p>
        </article>
      </section>

      <section class="card">
        <h2>Existing questions</h2>
        <?php if ($questions === []) : ?>
          <div class="empty-state compact-empty"><h3>No custom questions yet.</h3><p class="muted">Add questions to collect shelter-specific adoption details.</p></div>
        <?php else : ?>
          <div class="table-wrap">
            <table class="table" data-enhanced-table data-table-key="shelter-questions" data-table-empty="No questions match these filters.">
              <thead><tr><th>Question</th><th>Type</th><th>Required</th><th>Status</th><th>Order</th><th data-no-filter="true" data-no-sort="true">Action</th></tr></thead>
              <tbody>
                <?php foreach ($questions as $question) : ?>
                  <tr>
                    <td><strong><?php echo e($question['question_text']); ?></strong><br><span class="muted"><?php echo e(excerpt($question['choice_options'], 90)); ?></span></td>
                    <td><?php echo e(status_label($question['answer_type'])); ?></td>
                    <td><?php echo e(bool_label($question['is_required'])); ?></td>
                    <td><span class="badge <?php echo (int) $question['is_active'] === 1 ? 'approved' : 'archived'; ?>"><?php echo (int) $question['is_active'] === 1 ? 'Active' : 'Inactive'; ?></span></td>
                    <td><?php echo e($question['sort_order']); ?></td>
                    <td class="table-actions">
                      <button class="btn secondary small" type="button" data-open-dialog="question-dialog-<?php echo e($question['id']); ?>">Edit</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </section>

      <?php foreach ($questions as $question) : ?>
        <dialog class="app-dialog" id="question-dialog-<?php echo e($question['id']); ?>">
          <div class="dialog-shell">
            <header class="dialog-header">
              <div><p class="eyebrow">Question editor</p><h2>Edit question</h2></div>
              <button class="dialog-close" type="button" data-close-dialog>Close</button>
            </header>
            <form method="post" class="form">
              <input type="hidden" name="csrf_token" value="<?php echo e(csrfToken()); ?>">
              <input type="hidden" name="question_id" value="<?php echo e($question['id']); ?>">
              <label><span>Question</span><input class="input" name="question_text" value="<?php echo e($question['question_text']); ?>" required></label>
              <label><span>Answer type</span><select class="input" name="answer_type" data-answer-type>
                <?php foreach (['free_text' => 'Free text', 'yes_no' => 'Yes/No', 'choice' => 'Choice/dropdown'] as $value => $label) : ?>
                  <option value="<?php echo e($value); ?>" <?php echo selected($question['answer_type'], $value); ?>><?php echo e($label); ?></option>
                <?php endforeach; ?>
              </select></label>
              <label data-choice-options><span>Choice options</span><textarea class="input" name="choice_options" rows="4"><?php echo e($question['choice_options']); ?></textarea></label>
              <label><span>Sort order</span><input class="input" type="number" min="0" name="sort_order" value="<?php echo e($question['sort_order']); ?>"></label>
              <div class="checkbox-row">
                <label><input type="checkbox" name="is_required" value="1" <?php echo checked($question['is_required']); ?>> Required</label>
                <label><input type="checkbox" name="is_active" value="1" <?php echo checked($question['is_active']); ?>> Active</label>
              </div>
              <div class="dialog-actions"><button class="btn green" type="submit">Save question</button></div>
            </form>
          </div>
        </dialog>
      <?php endforeach; ?>
    </main>
  </div>
</body>
</html>
