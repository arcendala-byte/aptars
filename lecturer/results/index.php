<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('lecturer');

$pageTitle = 'Enter Marks';
$activeNav = 'results';

$lect = currentProfile();
if (!$lect) { echo '<p>Lecturer profile not found.</p>'; exit; }
$lectId = (int)$lect['id'];

$currentSem = (int)setting('current_semester_id', 1);

$stmt = db()->prepare("
    SELECT a.*, sub.code AS subj_code, sub.name AS subj_name, sem.semester_name, y.year_name
    FROM assessments a
    JOIN subjects sub ON sub.id = a.subject_id
    JOIN semesters sem ON sem.id = a.semester_id
    JOIN academic_years y ON y.id = sem.academic_year_id
    JOIN lecturer_subjects ls ON ls.subject_id = a.subject_id AND ls.semester_id = a.semester_id
    WHERE ls.lecturer_id = ? AND a.status='active'
    ORDER BY y.start_date DESC, sub.code, a.id
");
$stmt->execute([$lectId]);
$assessments = $stmt->fetchAll();

$progress = [];
foreach ($assessments as $a) $progress[$a['id']] = assessmentProgress((int)$a['id']);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Enter Marks</h1>
    <p class="muted mb-0">Assessments for subjects you are assigned to. Select one to enter marks.</p>
  </div>

  <?php displayFlash(); ?>

  <div class="panel"><div class="panel-body p-0">
    <?php if ($assessments): ?>
      <table class="table mb-0 align-middle">
        <thead><tr><th>Subject</th><th>Assessment</th><th>Semester</th><th>Graded / Enrolled</th><th>Average</th><th>Pass Rate</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($assessments as $a): $p = $progress[$a['id']]; ?>
          <tr>
            <td><strong><?= e($a['subj_code']) ?></strong> — <?= e($a['subj_name']) ?></td>
            <td><?= e($a['name']) ?> <span class="badge bg-secondary"><?= e($a['type']) ?></span></td>
            <td><?= e($a['year_name']) ?> / <?= e($a['semester_name']) ?></td>
            <td><?= $p['graded'] ?> / <?= $p['students'] ?></td>
            <td><?= pct($p['avg']) ?></td>
            <td><?= pct($p['pass_rate']) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-primary" href="enter.php?id=<?= (int)$a['id'] ?>"><i class="bi bi-pencil-square"></i> Enter</a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty p-4 mb-0">No active assessments for your subjects. Ask the administrator to create assessments.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>