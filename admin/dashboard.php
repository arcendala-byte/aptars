<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth.php';
requireRole('admin');

$pageTitle = 'Admin Dashboard';
$activeNav = 'dashboard';
$pdo = db();

$totalStudents   = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE status='active'")->fetchColumn();
$totalLecturers  = (int)$pdo->query("SELECT COUNT(*) FROM lecturers WHERE status='active'")->fetchColumn();
$totalProgrammes = (int)$pdo->query("SELECT COUNT(*) FROM programmes WHERE status='active'")->fetchColumn();
$totalSubjects   = (int)$pdo->query("SELECT COUNT(*) FROM subjects WHERE status='active'")->fetchColumn();

$avgPerf  = (float)$pdo->query("SELECT COALESCE(AVG(percentage),0) FROM results WHERE status='published'")->fetchColumn();
$passRate = (float)$pdo->query(
    "SELECT COALESCE(SUM(CASE WHEN percentage >= 40 THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) * 100, 0)
     FROM results WHERE status='published'"
)->fetchColumn();

$attPct = (float)$pdo->query(
    "SELECT COALESCE(SUM(CASE WHEN status IN ('Present','Late') THEN 1 ELSE 0 END) / NULLIF(COUNT(*),0) * 100, 0)
     FROM attendance"
)->fetchColumn();

$lowPerf   = (float)setting('performance_threshold', 50);
$lowAtt    = (float)setting('attendance_threshold', 75);
$failLimit = (int)setting('failed_subjects_threshold', 2);

$attentionStmt = $pdo->prepare("
    SELECT s.id, s.registration_number, s.first_name, s.last_name,
           COALESCE(AVG(r.percentage),0) AS avg_mark,
           SUM(CASE WHEN r.percentage < 40 THEN 1 ELSE 0 END) AS failed
    FROM students s
    LEFT JOIN results r ON r.student_id = s.id AND r.status='published'
    WHERE s.status='active'
    GROUP BY s.id, s.registration_number, s.first_name, s.last_name
    HAVING avg_mark < :thr OR failed >= :fail
    ORDER BY avg_mark ASC
    LIMIT 10
");
$attentionStmt->execute([':thr' => $lowPerf, ':fail' => $failLimit]);
$attention = $attentionStmt->fetchAll();

$recent = $pdo->query("
    SELECT r.id, r.marks, r.max_marks, r.percentage, r.grade, r.created_at,
           s.first_name, s.last_name, sub.code AS subject_code, sub.name AS subject_name
    FROM results r
    JOIN students s ON s.id = r.student_id
    JOIN subjects sub ON sub.id = r.subject_id
    ORDER BY r.created_at DESC
    LIMIT 8
")->fetchAll();

$gradeDist = $pdo->query("
    SELECT grade, COUNT(*) AS c FROM results WHERE status='published' GROUP BY grade ORDER BY grade
")->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>

<main class="content">
  <div class="page-head">
    <h1>Dashboard</h1>
    <p class="muted">Overview of academic performance across the institution.</p>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Students</div><div class="stat-value"><?= number_format($totalStudents) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Lecturers</div><div class="stat-value"><?= number_format($totalLecturers) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Programmes</div><div class="stat-value"><?= number_format($totalProgrammes) ?></div></div></div>
    <div class="col-6 col-lg-3"><div class="stat-card"><div class="stat-label">Subjects</div><div class="stat-value"><?= number_format($totalSubjects) ?></div></div></div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Average Performance</div><div class="stat-value"><?= pct($avgPerf) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Pass Rate</div><div class="stat-value"><?= pct($passRate) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Attendance Rate</div><div class="stat-value"><?= pct($attPct) ?></div></div></div>
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="panel">
        <div class="panel-head"><h5>Grade Distribution</h5></div>
        <div class="panel-body">
          <?php if ($gradeDist): ?>
            <canvas id="gradeChart" height="130"></canvas>
          <?php else: ?>
            <p class="empty">No results recorded yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="panel">
        <div class="panel-head"><h5>Students Requiring Attention</h5></div>
        <div class="panel-body p-0">
          <?php if ($attention): ?>
            <table class="table table-sm mb-0">
              <thead><tr><th>Student</th><th>Avg</th><th>Failed</th></tr></thead>
              <tbody>
              <?php foreach ($attention as $a): ?>
                <tr>
                  <td><strong><?= e($a['first_name'] . ' ' . $a['last_name']) ?></strong><br>
                      <small class="muted"><?= e($a['registration_number']) ?></small></td>
                  <td><?= pct((float)$a['avg_mark']) ?></td>
                  <td><span class="badge bg-warning text-dark"><?= (int)$a['failed'] ?></span></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="empty p-3 mb-0">No students currently require attention.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <div class="col-12">
      <div class="panel">
        <div class="panel-head"><h5>Recent Results</h5></div>
        <div class="panel-body p-0">
          <?php if ($recent): ?>
            <table class="table table-hover mb-0">
              <thead><tr><th>Student</th><th>Subject</th><th>Marks</th><th>%</th><th>Grade</th><th>Date</th></tr></thead>
              <tbody>
              <?php foreach ($recent as $r): ?>
                <tr>
                  <td><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                  <td><?= e($r['subject_code']) ?> - <?= e($r['subject_name']) ?></td>
                  <td><?= e($r['marks']) ?>/<?= e($r['max_marks']) ?></td>
                  <td><?= pct((float)$r['percentage']) ?></td>
                  <td><span class="badge bg-primary"><?= e($r['grade']) ?></span></td>
                  <td><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          <?php else: ?>
            <p class="empty p-3 mb-0">No results recorded yet.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</main>

<?php if ($gradeDist): ?>
<script>
window.APTARS_CHARTS = window.APTARS_CHARTS || {};
window.APTARS_CHARTS.gradeChart = {
  labels: <?= json_encode(array_column($gradeDist, 'grade')) ?>,
  data:   <?= json_encode(array_map('intval', array_column($gradeDist, 'c'))) ?>
};
</script>
<?php endif; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>