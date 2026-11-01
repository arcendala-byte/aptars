<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('lecturer');

$pageTitle = 'Record Attendance';
$activeNav = 'attendance';

$lect = currentProfile();
if (!$lect) { echo '<p>Lecturer profile not found.</p>'; exit; }
$lectId = (int)$lect['id'];

// Subjects the lecturer is assigned to (distinct across current + past semesters)
$stmt = db()->prepare("
    SELECT DISTINCT sub.id, sub.code, sub.name, sem.id AS semester_id,
           sem.semester_name, y.year_name, sub.id AS subject_id
    FROM lecturer_subjects ls
    JOIN subjects  sub ON sub.id = ls.subject_id
    JOIN semesters sem ON sem.id = ls.semester_id
    JOIN academic_years y ON y.id = sem.academic_year_id
    WHERE ls.lecturer_id = ?
    ORDER BY y.start_date DESC, sub.code
");
$stmt->execute([$lectId]);
$assignments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1>Record Attendance</h1>
    <p class="muted mb-0">Choose one of your assigned subjects to record attendance.</p></div>

  <?php displayFlash(); ?>

  <div class="panel"><div class="panel-body p-0">
    <?php if ($assignments): ?>
      <table class="table mb-0 align-middle">
        <thead><tr><th>Subject</th><th>Semester</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($assignments as $a): ?>
          <tr>
            <td><strong><?= e($a['code']) ?></strong> — <?= e($a['name']) ?></td>
            <td><?= e($a['year_name']) ?> / <?= e($a['semester_name']) ?></td>
            <td class="text-end">
              <a class="btn btn-sm btn-primary"
                 href="record.php?subject_id=<?= (int)$a['subject_id'] ?>&semester_id=<?= (int)$a['semester_id'] ?>">
                <i class="bi bi-calendar-check"></i> Record
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="empty p-4 mb-0">You have no subject assignments yet.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>