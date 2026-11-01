<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireRole('lecturer');

$pageTitle = 'Lecturer Dashboard';
$activeNav = 'dashboard';
$pdo  = db();
$user = currentUser();
$lect = currentProfile();

if (!$lect) {
    echo '<p>Lecturer profile not found. Contact the administrator.</p>';
    exit;
}
$lectId = (int)$lect['id'];
$currentSem = (int)setting('current_semester_id', 1);

$stmt = $pdo->prepare("
    SELECT sub.id, sub.code, sub.name,
           (SELECT COUNT(*) FROM student_subjects ss WHERE ss.subject_id = sub.id AND ss.semester_id = ls.semester_id) AS student_count
    FROM lecturer_subjects ls
    JOIN subjects sub ON sub.id = ls.subject_id
    WHERE ls.lecturer_id = ? AND ls.semester_id = ?
    ORDER BY sub.code
");
$stmt->execute([$lectId, $currentSem]);
$subjects = $stmt->fetchAll();

$totalStudents = array_sum(array_column($subjects, 'student_count'));

$avgStmt = $pdo->prepare("
    SELECT COALESCE(AVG(r.percentage),0)
    FROM results r
    JOIN lecturer_subjects ls ON ls.subject_id = r.subject_id AND ls.semester_id = r.semester_id
    WHERE ls.lecturer_id = ? AND r.semester_id = ?
");
$avgStmt->execute([$lectId, $currentSem]);
$avgMark = (float)$avgStmt->fetchColumn();

$passStmt = $pdo->prepare("
    SELECT COALESCE(SUM(CASE WHEN r.percentage >= 40 THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) * 100, 0)
    FROM results r
    JOIN lecturer_subjects ls ON ls.subject_id = r.subject_id AND ls.semester_id = r.semester_id
    WHERE ls.lecturer_id = ? AND r.semester_id = ?
");
$passStmt->execute([$lectId, $currentSem]);
$passRate = (float)$passStmt->fetchColumn();

$attStmt = $pdo->prepare("
    SELECT COALESCE(SUM(CASE WHEN a.status IN ('Present','Late') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) * 100, 0)
    FROM attendance a
    WHERE a.lecturer_id = ? AND a.semester_id = ?
");
$attStmt->execute([$lectId, $currentSem]);
$attPct = (float)$attStmt->fetchColumn();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Welcome, <?= e($user['name']) ?></h1>
    <p class="muted">Summary of your assigned subjects and class performance.</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Assigned Subjects</div><div class="stat-value"><?= count($subjects) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Students</div><div class="stat-value"><?= (int)$totalStudents ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Class Average</div><div class="stat-value"><?= pct($avgMark) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Pass Rate</div><div class="stat-value"><?= pct($passRate) ?></div></div></div>
  </div>

  <div class="panel">
    <div class="panel-head"><h5>My Subjects (Current Semester)</h5></div>
    <div class="panel-body p-0">
      <?php if ($subjects): ?>
        <table class="table mb-0">
          <thead><tr><th>Code</th><th>Subject</th><th>Students</th></tr></thead>
          <tbody>
          <?php foreach ($subjects as $s): ?>
            <tr><td><strong><?= e($s['code']) ?></strong></td><td><?= e($s['name']) ?></td><td><?= (int)$s['student_count'] ?></td></tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-3 mb-0">You have no subjects assigned for the current semester.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>