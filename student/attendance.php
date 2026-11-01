<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
requireRole('student');

$pageTitle = 'My Attendance';
$activeNav = 'attendance';

$stud = currentProfile();
if (!$stud) { echo '<p>Student profile not found.</p>'; exit; }
$studId = (int)$stud['id'];

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC")->fetchAll();

// Per-subject breakdown
$stmt = db()->prepare("
    SELECT sub.code, sub.name,
           COUNT(*) AS sessions,
           SUM(CASE WHEN a.status IN ('Present','Late') THEN 1 ELSE 0 END) AS attended,
           SUM(CASE WHEN a.status='Absent' THEN 1 ELSE 0 END) AS absences,
           SUM(CASE WHEN a.status='Late' THEN 1 ELSE 0 END) AS lates,
           SUM(CASE WHEN a.status='Excused' THEN 1 ELSE 0 END) AS excused
    FROM attendance a
    JOIN subjects sub ON sub.id = a.subject_id
    WHERE a.student_id = ? AND a.semester_id = ?
    GROUP BY sub.id, sub.code, sub.name
    ORDER BY sub.code
");
$stmt->execute([$studId, $semId]);
$bySubject = $stmt->fetchAll();

$totals = ['sessions'=>0,'attended'=>0];
foreach ($bySubject as $r) { $totals['sessions'] += (int)$r['sessions']; $totals['attended'] += (int)$r['attended']; }
$overallPct = $totals['sessions'] > 0 ? $totals['attended']/$totals['sessions']*100 : 0;

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>My Attendance</h1><p class="muted mb-0">Attendance records for the selected semester.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Overall Attendance</div><div class="stat-value"><?= pct($overallPct) ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Sessions Attended</div><div class="stat-value"><?= (int)$totals['attended'] ?></div></div></div>
    <div class="col-md-4"><div class="stat-card"><div class="stat-label">Total Sessions</div><div class="stat-value"><?= (int)$totals['sessions'] ?></div></div></div>
  </div>

  <div class="panel"><div class="panel-head"><h5>Per-Subject Breakdown</h5></div>
    <div class="panel-body p-0">
      <?php if ($bySubject): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Subject</th><th>Sessions</th><th>Present/Late</th><th>Absent</th><th>Late</th><th>Excused</th><th>Attendance %</th></tr></thead>
          <tbody>
          <?php foreach ($bySubject as $r): $p = (int)$r['sessions'] > 0 ? (int)$r['attended']/(int)$r['sessions']*100 : 0; ?>
            <tr>
              <td><strong><?= e($r['code']) ?></strong> — <?= e($r['name']) ?></td>
              <td><?= (int)$r['sessions'] ?></td>
              <td><?= (int)$r['attended'] ?></td>
              <td><?= (int)$r['absences'] ?></td>
              <td><?= (int)$r['lates'] ?></td>
              <td><?= (int)$r['excused'] ?></td>
              <td><?= pct($p) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No attendance records for this semester.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>