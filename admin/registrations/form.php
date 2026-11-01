<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Register Student for Subjects';
$activeNav = 'registrations';

$errors = [];
$data = ['student_id'=>'', 'semester_id'=>'', 'subject_ids'=>[]];

$students = db()->query("SELECT id, registration_number, first_name, last_name FROM students WHERE status='active' ORDER BY registration_number")->fetchAll();
foreach ($students as &$s) { $s['label'] = $s['registration_number'] . ' — ' . $s['first_name'] . ' ' . $s['last_name']; }
unset($s);

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id WHERE s.status='active' ORDER BY y.start_date DESC, s.start_date")->fetchAll();

$subjects = db()->query("SELECT id, code, name FROM subjects WHERE status='active' ORDER BY code")->fetchAll();
foreach ($subjects as &$s) { $s['label'] = $s['code'] . ' — ' . $s['name']; }
unset($s);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $data['student_id']  = (int)post('student_id', 0);
    $data['semester_id'] = (int)post('semester_id', 0);
    $data['subject_ids'] = array_map('intval', (array)($_POST['subject_ids'] ?? []));

    if ($data['student_id'] <= 0)  $errors[] = 'Student is required.';
    if ($data['semester_id'] <= 0) $errors[] = 'Semester is required.';
    if (empty($data['subject_ids'])) $errors[] = 'Select at least one subject.';

    if (!$errors) {
        $today = date('Y-m-d');
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $inserted = 0;
            $check = $pdo->prepare('SELECT id FROM student_subjects WHERE student_id=? AND subject_id=? AND semester_id=?');
            $insert = $pdo->prepare('INSERT INTO student_subjects (student_id, subject_id, semester_id, registration_date, status) VALUES (?,?,?,?,"registered")');
            foreach ($data['subject_ids'] as $sid) {
                $check->execute([$data['student_id'], $sid, $data['semester_id']]);
                if ($check->fetch()) continue;
                $insert->execute([$data['student_id'], $sid, $data['semester_id'], $today]);
                $inserted++;
            }
            $pdo->commit();
            logActivity((int)$_SESSION['user_id'], 'registration.create', "Registered student #{$data['student_id']} for $inserted subject(s)");
            setFlash('success', "$inserted registration(s) added.");
            redirect(baseUrl('admin/registrations/index.php'));
        } catch (Throwable $t) {
            $pdo->rollBack();
            error_log('[APTARS registration] ' . $t->getMessage());
            $errors[] = 'Could not save registrations. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1><?= e($pageTitle) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Registrations</a></p></div>
  <?= errorList($errors) ?>
  <div class="panel"><div class="panel-body">
    <form method="post">
      <?= csrfField() ?>
      <div class="row g-3 mb-3">
        <div class="col-md-6"><label class="form-label">Student <span class="text-danger">*</span></label>
          <select name="student_id" class="form-select" required>
            <?= selectOptions($students, 'id', 'label', $data['student_id'], 'Select student') ?>
          </select></div>
        <div class="col-md-6"><label class="form-label">Semester <span class="text-danger">*</span></label>
          <select name="semester_id" class="form-select" required>
            <?= selectOptions($semesters, 'id', 'label', $data['semester_id'], 'Select semester') ?>
          </select></div>
      </div>

      <label class="form-label">Subjects <span class="text-danger">*</span></label>
      <div class="border rounded p-3" style="max-height: 360px; overflow-y:auto;">
        <?php if ($subjects): ?>
          <?php foreach ($subjects as $s): ?>
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="subject_ids[]" value="<?= (int)$s['id'] ?>"
                     id="subj<?= (int)$s['id'] ?>"
                     <?= in_array((int)$s['id'], $data['subject_ids'], true) ? 'checked' : '' ?>>
              <label class="form-check-label" for="subj<?= (int)$s['id'] ?>">
                <strong><?= e($s['code']) ?></strong> — <?= e($s['name']) ?>
              </label>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="empty mb-0">No active subjects available.</p>
        <?php endif; ?>
      </div>

      <div class="mt-3">
        <button class="btn btn-primary">Register</button>
        <a href="index.php" class="btn btn-link">Cancel</a>
      </div>
    </form>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>