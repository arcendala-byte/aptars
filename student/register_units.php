<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/notifications.php';
requireRole('student');

$pageTitle = 'Register Units';
$activeNav = 'register_units';

$stud = currentProfile();
if (!$stud) { echo '<p>Student profile not found.</p>'; exit; }
$studId       = (int)$stud['id'];
$programmeId  = (int)$stud['programme_id'];

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$semesters = db()->query("
    SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label
    FROM semesters s
    JOIN academic_years y ON y.id = s.academic_year_id
    WHERE s.status='active'
    ORDER BY y.start_date DESC, s.start_date
")->fetchAll();

$registrationOpen = setting('self_registration_units', '1') === '1';
$errors = [];

$stmt = db()->prepare('SELECT semester_name FROM semesters WHERE id = ?');
$stmt->execute([$semId]);
$currentSemesterName = (string)$stmt->fetchColumn();

$stmt = db()->prepare("SELECT subject_id FROM student_subjects WHERE student_id=? AND semester_id=? AND status='registered'");
$stmt->execute([$studId, $semId]);
$registeredIds = array_map('intval', array_column($stmt->fetchAll(), 'subject_id'));

$stmt = db()->prepare("
    SELECT sub.*
    FROM subjects sub
    WHERE sub.programme_id = ? AND sub.status = 'active'
    ORDER BY sub.semester, sub.code
");
$stmt->execute([$programmeId]);
$available = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registrationOpen) {
    csrfVerify();
    $action = post('action', 'register');

    if ($action === 'register') {
        $pick = array_map('intval', (array)($_POST['subject_ids'] ?? []));
        $pick = array_values(array_diff($pick, $registeredIds));
        if (empty($pick)) { $errors[] = 'Tick at least one unit to register.'; }
        else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $ins = $pdo->prepare("INSERT IGNORE INTO student_subjects (student_id, subject_id, semester_id, registration_date, status) VALUES (?,?,?,?,'registered')");
                $count = 0;
                foreach ($pick as $sid) {
                    $chk = $pdo->prepare("SELECT 1 FROM subjects WHERE id=? AND programme_id=? AND status='active'");
                    $chk->execute([$sid, $programmeId]);
                    if (!$chk->fetchColumn()) continue;
                    $ins->execute([$studId, $sid, $semId, date('Y-m-d')]);
                    if ($ins->rowCount() > 0) $count++;
                }
                $pdo->commit();
                logActivity((int)$_SESSION['user_id'], 'student.register_units', "Registered $count unit(s)");
                setFlash('success', "$count unit(s) registered.");
                redirect(baseUrl('student/register_units.php?semester_id='.$semId));
            } catch (Throwable $t) {
                $pdo->rollBack();
                $errors[] = 'Could not register units.';
            }
        }
    } elseif ($action === 'drop') {
        $dropId = (int)post('subject_id', 0);
        if ($dropId > 0) {
            db()->prepare("DELETE FROM student_subjects WHERE student_id=? AND subject_id=? AND semester_id=?")
                ->execute([$studId, $dropId, $semId]);
            logActivity((int)$_SESSION['user_id'], 'student.drop_unit', "Dropped subject #$dropId");
            setFlash('success', 'Unit dropped.');
            redirect(baseUrl('student/register_units.php?semester_id='.$semId));
        }
    }
}

$totalRegistered = count($registeredIds);
$totalAvailable  = count($available);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Register Units</h1>
      <p class="muted mb-0">Choose the units you wish to take this semester.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <?php displayFlash(); ?>

  <?php if (!$registrationOpen): ?>
    <div class="alert alert-warning"><i class="bi bi-lock"></i> Self-registration is currently closed.</div>
  <?php else: ?>
    <?= errorList($errors) ?>

    <div class="row g-3 mb-4">
      <div class="col-md-4"><div class="stat-card"><div class="stat-label">Already Registered</div><div class="stat-value"><?= $totalRegistered ?></div></div></div>
      <div class="col-md-4"><div class="stat-card"><div class="stat-label">Available in Programme</div><div class="stat-value"><?= $totalAvailable ?></div></div></div>
      <div class="col-md-4"><div class="stat-card"><div class="stat-label">Semester</div><div class="stat-value" style="font-size:1.1rem"><?= e($currentSemesterName) ?></div></div></div>
    </div>

    <div class="panel"><div class="panel-body p-0">
      <?php if (!$available): ?>
        <div class="p-4 text-center">
          <i class="bi bi-journal-x" style="font-size:2rem;color:#9aa4b2"></i>
          <p class="empty mt-2 mb-1"><strong>No units available for your programme.</strong></p>
          <p class="muted small mb-0">Ask the administrator to add subjects.</p>
        </div>
      <?php else: ?>
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="register">
          <table class="table mb-0 align-middle">
            <thead>
              <tr>
                <th style="width:40px"></th>
                <th>Code</th>
                <th>Unit Name</th>
                <th style="width:80px">Sem</th>
                <th style="width:80px">Credits</th>
                <th style="width:160px">Status</th>
              </tr>
            </thead>
            <tbody>
            <?php
            $canRegister = false;
            foreach ($available as $s):
                $isReg = in_array((int)$s['id'], $registeredIds, true);
                if (!$isReg) $canRegister = true;
            ?>
              <tr>
                <td>
                  <?php if ($isReg): ?>
                    <i class="bi bi-check-circle-fill text-success"></i>
                  <?php else: ?>
                    <input class="form-check-input" type="checkbox" name="subject_ids[]" value="<?= (int)$s['id'] ?>" id="u<?= (int)$s['id'] ?>">
                  <?php endif; ?>
                </td>
                <td><strong><?= e($s['code']) ?></strong></td>
                <td><label for="u<?= (int)$s['id'] ?>" class="<?= $isReg ? 'text-muted' : '' ?>" style="cursor:pointer"><?= e($s['name']) ?></label></td>
                <td><?= e($s['semester']) ?></td>
                <td><?= (int)$s['credit_hours'] ?></td>
                <td>
                  <?php if ($isReg): ?>
                    <span class="badge bg-success">Registered</span>
                  <?php else: ?>
                    <span class="badge bg-secondary">Available</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <div class="p-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="muted"><?= $canRegister ? 'Tick the units to register, then click Register Selected.' : 'You are already registered for all available units.' ?></small>
            <button class="btn btn-primary" <?= $canRegister ? '' : 'disabled' ?> onclick="return confirm('Register the selected units?');">
              <i class="bi bi-check2-circle"></i> Register Selected
            </button>
          </div>
        </form>

        <?php if ($registeredIds): ?>
          <div class="p-3 border-top">
            <h6 class="mb-2 text-muted small text-uppercase">Currently Registered — click Drop to remove</h6>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($available as $s): if (!in_array((int)$s['id'], $registeredIds, true)) continue; ?>
                <form method="post" onsubmit="return confirm('Drop <?= e($s['code']) ?>?');" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="drop">
                  <input type="hidden" name="subject_id" value="<?= (int)$s['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">
                    <i class="bi bi-x-circle"></i> Drop <?= e($s['code']) ?>
                  </button>
                </form>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div></div>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>