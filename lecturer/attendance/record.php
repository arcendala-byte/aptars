<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('lecturer');

$lect = currentProfile();
if (!$lect) { echo '<p>Lecturer profile not found.</p>'; exit; }
$lectId = (int)$lect['id'];

$subjectId  = (int)(post('subject_id')  ?? get('subject_id', 0));
$semesterId = (int)(post('semester_id') ?? get('semester_id', 0));
$date       = post('date') ?? get('date', date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) $date = date('Y-m-d');

if ($subjectId <= 0 || $semesterId <= 0 || !lecturerCanAccessSubject($lectId, $subjectId, $semesterId)) {
    setFlash('danger', 'You are not assigned to that subject/semester.');
    redirect(baseUrl('lecturer/attendance/index.php'));
}

$stmt = db()->prepare("SELECT * FROM subjects WHERE id=?"); $stmt->execute([$subjectId]); $subject = $stmt->fetch();
$stmt = db()->prepare("SELECT s.*, y.year_name FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id WHERE s.id=?"); $stmt->execute([$semesterId]); $semester = $stmt->fetch();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $res = saveAttendanceRecords($subjectId, $semesterId, $date, (array)($_POST['status'] ?? []), $lectId);
    if ($res['errors']) $errors = $res['errors'];
    else {
        setFlash('success', $res['saved'] . ' attendance record(s) saved.');
        redirect(baseUrl('lecturer/attendance/record.php?subject_id='.$subjectId.'&semester_id='.$semesterId.'&date='.$date));
    }
}

$stmt = db()->prepare("
    SELECT s.id, s.registration_number, s.first_name, s.last_name, a.status AS existing_status
    FROM student_subjects ss
    JOIN students s ON s.id = ss.student_id
    LEFT JOIN attendance a ON a.student_id = s.id AND a.subject_id = ?
         AND a.semester_id = ? AND a.attendance_date = ?
    WHERE ss.subject_id = ? AND ss.semester_id = ? AND ss.status='registered'
    ORDER BY s.registration_number
");
$stmt->execute([$subjectId, $semesterId, $date, $subjectId, $semesterId]);
$students = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = (array)($_POST['status'] ?? []);
    foreach ($students as &$s) if (isset($posted[(int)$s['id']])) $s['existing_status'] = $posted[(int)$s['id']];
    unset($s);
}

$statuses = ['Present','Absent','Late','Excused'];

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1>Record Attendance</h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back</a></p></div>
  <?= errorList($errors) ?>
  <?php displayFlash(); ?>

  <div class="panel mb-3"><div class="panel-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
      <input type="hidden" name="semester_id" value="<?= $semesterId ?>">
      <div class="col-md-4"><label class="form-label small mb-1">Session Date</label>
        <input type="date" name="date" class="form-control form-control-sm" value="<?= e($date) ?>"></div>
      <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Load</button></div>
    </form>
  </div></div>

  <div class="panel"><div class="panel-body p-0">
    <div class="p-3 border-bottom">
      <strong><?= e($subject['code'] ?? '') ?> — <?= e($subject['name'] ?? '') ?></strong>
      &middot; <?= e($semester['year_name'] ?? '') ?> / <?= e($semester['semester_name'] ?? '') ?>
      &middot; <strong><?= e($date) ?></strong>
    </div>
    <?php if ($students): ?>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
        <input type="hidden" name="semester_id" value="<?= $semesterId ?>">
        <input type="hidden" name="date" value="<?= e($date) ?>">
        <table class="table mb-0 align-middle">
          <thead><tr><th>Reg. No.</th><th>Name</th><th style="width:220px">Status</th></tr></thead>
          <tbody>
          <?php foreach ($students as $s): $cur = $s['existing_status'] ?: 'Present'; ?>
            <tr>
              <td><strong><?= e($s['registration_number']) ?></strong></td>
              <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
              <td>
                <select name="status[<?= (int)$s['id'] ?>]" class="form-select form-select-sm">
                  <?php foreach ($statuses as $st): ?>
                    <option value="<?= $st ?>" <?= $cur===$st?'selected':'' ?>><?= $st ?></option>
                  <?php endforeach; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="p-3 border-top"><button class="btn btn-primary"><i class="bi bi-save"></i> Save Attendance</button></div>
      </form>
    <?php else: ?>
      <p class="empty p-4 mb-0">No students registered for this subject/semester.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>