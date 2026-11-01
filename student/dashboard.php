<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireRole('student');

$pageTitle = 'My Dashboard';
$activeNav = 'dashboard';
$pdo  = db();
$user = currentUser();
$stud = currentProfile();

if (!$stud) {
    echo '<p>Student profile not found. Contact the administrator.</p>';
    exit;
}
$studId     = (int)$stud['id'];
$currentSem = (int)setting('current_semester_id', 1);

$stmt = $pdo->prepare("
    SELECT sub.id, sub.code, sub.name, sub.credit_hours
    FROM student_subjects ss
    JOIN subjects sub ON sub.id = ss.subject_id
    WHERE ss.student_id = ? AND ss.semester_id = ? AND ss.status = 'registered'
    ORDER BY sub.code
");
$stmt->execute([$studId, $currentSem]);
$subjects = $stmt->fetchAll();

$avgStmt = $pdo->prepare("SELECT COALESCE(AVG(percentage),0) FROM results WHERE student_id=? AND semester_id=? AND status='published'");
$avgStmt->execute([$studId, $currentSem]);
$avg = (float)$avgStmt->fetchColumn();

$passStmt = $pdo->prepare("
    SELECT
      SUM(CASE WHEN percentage >= 40 THEN 1 ELSE 0 END) AS passed,
      SUM(CASE WHEN percentage <  40 THEN 1 ELSE 0 END) AS failed
    FROM results WHERE student_id=? AND semester_id=? AND status='published'
");
$passStmt->execute([$studId, $currentSem]);
$pf = $passStmt->fetch() ?: ['passed' => 0, 'failed' => 0];

$attStmt = $pdo->prepare("
    SELECT COALESCE(SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) * 100, 0)
    FROM attendance WHERE student_id=? AND semester_id=?
");
$attStmt->execute([$studId, $currentSem]);
$attPct = (float)$attStmt->fetchColumn();

$rStmt = $pdo->prepare("
    SELECT r.marks, r.max_marks, r.percentage, r.grade, r.created_at, sub.code, sub.name
    FROM results r
    JOIN subjects sub ON sub.id = r.subject_id
    WHERE r.student_id = ? AND r.status='published'
    ORDER BY r.created_at DESC LIMIT 8
");
$rStmt->execute([$studId]);
$recent = $rStmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Hello, <?= e($stud['first_name']) ?></h1>
    <p class="muted">Reg. No: <strong><?= e($stud['registration_number']) ?></strong></p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Current Average</div><div class="stat-value"><?= pct($avg) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Subjects Passed</div><div class="stat-value"><?= (int)$pf['passed'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Subjects Failed</div><div class="stat-value"><?= (int)$pf['failed'] ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Attendance</div><div class="stat-value"><?= pct($attPct) ?></div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-6">
      <div class="panel">
        <div class="panel-head"><h5>My Subjects (Current Semester)</h5></div>
        <div class="panel-body p-0">
          <?php if ($subjects): ?>
            <table class="table mb-0">
              <thead><tr><th>Code</th><th>Subject</th><th>Credits</th></tr></thead>
              <tbody>
              <?php foreach ($subjects as $s): ?>
                <tr><td><strong><?= e($s['code']) ?></strong></td><td><?= e($s['name']) ?></td><td><?= (int)$s['credit_hours'] ?></td></tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="empty p-3 mb-0">You are not registered for any subjects this semester.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-6">
      <div class="panel">
        <div class="panel-head"><h5>Recent Results</h5></div>
        <div class="panel-body p-0">
          <?php if ($recent): ?>
            <table class="table mb-0">
              <thead><tr><th>Subject</th><th>%</th><th>Grade</th></tr></thead>
              <tbody>
              <?php foreach ($recent as $r): ?>
                <tr>
                  <td><?= e($r['code']) ?> - <?= e($r['name']) ?></td>
                  <td><?= pct((float)$r['percentage']) ?></td>
                  <td><span class="badge bg-primary"><?= e($r['grade']) ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="empty p-3 mb-0">No results published yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>