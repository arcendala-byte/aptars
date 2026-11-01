<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/analysis.php';
requireRole('lecturer');

$pageTitle = 'Students';
$activeNav = 'analysis_students';

$lect = currentProfile();
if (!$lect) { echo '<p>Lecturer profile not found.</p>'; exit; }
$lectId = (int)$lect['id'];

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));

$stmt = db()->prepare("
    SELECT DISTINCT s.id, s.registration_number, s.first_name, s.last_name
    FROM lecturer_subjects ls
    JOIN student_subjects ss ON ss.subject_id = ls.subject_id AND ss.semester_id = ls.semester_id
    JOIN students s ON s.id = ss.student_id
    WHERE ls.lecturer_id = ? AND ls.semester_id = ? AND ss.status='registered'
    ORDER BY s.registration_number
");
$stmt->execute([$lectId, $semId]);
$students = $stmt->fetchAll();

foreach ($students as &$s) {
    $a = attentionStatus((int)$s['id'], $semId);
    $s['avg'] = $a['avg']; $s['attendance'] = $a['attendance']; $s['status'] = $a['status']; $s['reason'] = $a['reason'];
}
unset($s);

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC")->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Students in My Classes</h1><p class="muted mb-0">Attention status based on average, attendance, and failed subjects.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <div class="panel"><div class="panel-body p-0">
    <?php if ($students): ?>
      <table class="table mb-0 align-middle">
        <thead><tr><th>Reg. No.</th><th>Name</th><th>Average</th><th>Attendance</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($students as $s): ?>
          <tr>
            <td><strong><?= e($s['registration_number']) ?></strong></td>
            <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
            <td><?= pct($s['avg']) ?></td>
            <td><?= pct($s['attendance']) ?></td>
            <td><?= attentionBadge($s['status']) ?> <small class="muted"><?= e(implode(', ', $s['reason'])) ?></small></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty p-4 mb-0">No students found in your classes for this semester.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>