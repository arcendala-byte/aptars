<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
requireRole(['admin','lecturer','student']);

$pageTitle = 'Reports';
$activeNav = 'reports';
$role = $_SESSION['role'];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Academic Reports</h1>
    <p class="muted mb-0">Generate printable academic reports. Use the print button on any report to save as PDF.</p>
  </div>

  <?php displayFlash(); ?>

  <div class="row g-3">
    <div class="col-md-6 col-lg-3">
      <div class="panel h-100"><div class="panel-body">
        <h5><i class="bi bi-person-lines-fill text-primary"></i> Student Report</h5>
        <p class="muted small">Full academic record for one student — marks, grades, average, GPA, attendance, and attention status.</p>
        <a href="student_report.php" class="btn btn-sm btn-primary"><?= $role === 'student' ? 'View My Report' : 'Open' ?></a>
      </div></div>
    </div>

    <?php if ($role !== 'student'): ?>
      <div class="col-md-6 col-lg-3">
        <div class="panel h-100"><div class="panel-body">
          <h5><i class="bi bi-book text-success"></i> Subject Report</h5>
          <p class="muted small">Class performance statistics for one subject — average, range, pass rate, grade distribution, per-student breakdown.</p>
          <a href="subject_report.php" class="btn btn-sm btn-primary">Open</a>
        </div></div>
      </div>
      <div class="col-md-6 col-lg-3">
        <div class="panel h-100"><div class="panel-body">
          <h5><i class="bi bi-people text-warning"></i> Class / Programme Report</h5>
          <p class="muted small">Programme-level summary — every student's average, attendance, and attention status in one table.</p>
          <a href="class_report.php" class="btn btn-sm btn-primary">Open</a>
        </div></div>
      </div>
    <?php endif; ?>

    <?php if ($role === 'admin'): ?>
      <div class="col-md-6 col-lg-3">
        <div class="panel h-100"><div class="panel-body">
          <h5><i class="bi bi-graph-up-arrow text-danger"></i> Performance Report</h5>
          <p class="muted small">Institution-wide analysis — trends, programme comparison, factor associations, grade distribution.</p>
          <a href="performance_report.php" class="btn btn-sm btn-primary">Open</a>
        </div></div>
      </div>
    <?php endif; ?>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>