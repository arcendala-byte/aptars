<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/analysis.php';
requireRole('student');

$pageTitle = 'My Results';
$activeNav = 'results';

$stud = currentProfile();
if (!$stud) { echo '<p>Student profile not found.</p>'; exit; }
$studId = (int)$stud['id'];

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id ORDER BY y.start_date DESC")->fetchAll();

$stmt = db()->prepare("
    SELECT r.marks, r.max_marks, r.percentage, r.grade, r.grade_point,
           sub.code, sub.name, a.name AS assessment_name, a.type,
           r.created_at
    FROM results r
    JOIN subjects sub ON sub.id = r.subject_id
    JOIN assessments a ON a.id = r.assessment_id
    WHERE r.student_id = ? AND r.semester_id = ? AND r.status='published'
    ORDER BY sub.code, a.id
");
$stmt->execute([$studId, $semId]);
$results = $stmt->fetchAll();

$summary = studentPerformanceSummary($studId, $semId);

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>My Results</h1><p class="muted mb-0">Detailed marks for the selected semester.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Average</div><div class="stat-value"><?= pct($summary['avg']) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">GPA</div><div class="stat-value"><?= number_format($summary['gpa'], 2) ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Passed</div><div class="stat-value"><?= (int)$summary['passed'] ?></div></div></div>
    <div class="col-6 col-md-3"><div class="stat-card"><div class="stat-label">Failed</div><div class="stat-value"><?= (int)$summary['failed'] ?></div></div></div>
  </div>

  <div class="panel"><div class="panel-body p-0">
    <?php if ($results): ?>
      <table class="table mb-0 align-middle">
        <thead><tr><th>Subject</th><th>Assessment</th><th>Type</th><th>Marks</th><th>%</th><th>Grade</th><th>Points</th></tr></thead>
        <tbody>
        <?php foreach ($results as $r): ?>
          <tr>
            <td><strong><?= e($r['code']) ?></strong> — <?= e($r['name']) ?></td>
            <td><?= e($r['assessment_name']) ?></td>
            <td><span class="badge bg-secondary"><?= e($r['type']) ?></span></td>
            <td><?= (float)$r['marks'] ?> / <?= (float)$r['max_marks'] ?></td>
            <td><?= pct((float)$r['percentage']) ?></td>
            <td><span class="badge bg-primary"><?= e($r['grade']) ?></span></td>
            <td><?= number_format((float)$r['grade_point'],2) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty p-4 mb-0">No published results for this semester yet.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>