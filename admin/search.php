<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
requireRole('admin');

$pageTitle = 'Search';
$activeNav = 'search';

$q = trim((string)get('q', ''));
$results = ['students'=>[], 'lecturers'=>[], 'subjects'=>[], 'programmes'=>[]];

if ($q !== '') {
    $like = "%$q%";

    $stmt = db()->prepare("
        SELECT s.id, s.registration_number, s.first_name, s.last_name, p.code AS prog_code
        FROM students s JOIN programmes p ON p.id = s.programme_id
        WHERE s.registration_number LIKE ? OR s.first_name LIKE ? OR s.last_name LIKE ?
        ORDER BY s.registration_number LIMIT 20
    ");
    $stmt->execute([$like,$like,$like]);
    $results['students'] = $stmt->fetchAll();

    $stmt = db()->prepare("
        SELECT l.id, l.staff_number, l.first_name, l.last_name, d.code AS dept_code
        FROM lecturers l JOIN departments d ON d.id = l.department_id
        WHERE l.staff_number LIKE ? OR l.first_name LIKE ? OR l.last_name LIKE ?
        ORDER BY l.last_name LIMIT 20
    ");
    $stmt->execute([$like,$like,$like]);
    $results['lecturers'] = $stmt->fetchAll();

    $stmt = db()->prepare("
        SELECT sub.id, sub.code, sub.name, p.code AS prog_code
        FROM subjects sub JOIN programmes p ON p.id = sub.programme_id
        WHERE sub.code LIKE ? OR sub.name LIKE ?
        ORDER BY sub.code LIMIT 20
    ");
    $stmt->execute([$like,$like]);
    $results['subjects'] = $stmt->fetchAll();

    $stmt = db()->prepare("
        SELECT id, code, name FROM programmes
        WHERE code LIKE ? OR name LIKE ?
        ORDER BY code LIMIT 20
    ");
    $stmt->execute([$like,$like]);
    $results['programmes'] = $stmt->fetchAll();
}

$totalResults = array_sum(array_map('count', $results));

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head">
    <h1>Search</h1>
    <p class="muted mb-0">Search across students, lecturers, subjects, and programmes.</p>
  </div>

  <div class="panel mb-4"><div class="panel-body">
    <form method="get" class="row g-2">
      <div class="col-md-10">
        <input type="text" name="q" class="form-control" autofocus value="<?= e($q) ?>"
               placeholder="Type a name, reg. no., staff no., code, or subject…">
      </div>
      <div class="col-md-2 d-grid"><button class="btn btn-primary"><i class="bi bi-search"></i> Search</button></div>
    </form>
  </div></div>

  <?php if ($q === ''): ?>
    <p class="empty p-4">Type a keyword above and press Search.</p>
  <?php elseif ($totalResults === 0): ?>
    <div class="alert alert-info">No matches for "<strong><?= e($q) ?></strong>". Try a partial name or code.</div>
  <?php else: ?>

    <?php if ($results['students']): ?>
      <div class="panel mb-3">
        <div class="panel-head"><h5><i class="bi bi-people"></i> Students (<?= count($results['students']) ?>)</h5></div>
        <div class="panel-body p-0">
          <table class="table mb-0">
            <thead><tr><th>Reg. No.</th><th>Name</th><th>Programme</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($results['students'] as $s): ?>
              <tr>
                <td><strong><?= e($s['registration_number']) ?></strong></td>
                <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
                <td><?= e($s['prog_code']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(baseUrl('admin/students/form.php?id='.(int)$s['id'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($results['lecturers']): ?>
      <div class="panel mb-3">
        <div class="panel-head"><h5><i class="bi bi-person-badge"></i> Lecturers (<?= count($results['lecturers']) ?>)</h5></div>
        <div class="panel-body p-0">
          <table class="table mb-0">
            <thead><tr><th>Staff No.</th><th>Name</th><th>Department</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($results['lecturers'] as $l): ?>
              <tr>
                <td><strong><?= e($l['staff_number']) ?></strong></td>
                <td><?= e($l['first_name'].' '.$l['last_name']) ?></td>
                <td><?= e($l['dept_code']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(baseUrl('admin/lecturers/form.php?id='.(int)$l['id'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($results['subjects']): ?>
      <div class="panel mb-3">
        <div class="panel-head"><h5><i class="bi bi-book"></i> Subjects (<?= count($results['subjects']) ?>)</h5></div>
        <div class="panel-body p-0">
          <table class="table mb-0">
            <thead><tr><th>Code</th><th>Subject</th><th>Programme</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($results['subjects'] as $s): ?>
              <tr>
                <td><strong><?= e($s['code']) ?></strong></td>
                <td><?= e($s['name']) ?></td>
                <td><?= e($s['prog_code']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(baseUrl('admin/subjects/form.php?id='.(int)$s['id'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

    <?php if ($results['programmes']): ?>
      <div class="panel mb-3">
        <div class="panel-head"><h5><i class="bi bi-journal-bookmark"></i> Programmes (<?= count($results['programmes']) ?>)</h5></div>
        <div class="panel-body p-0">
          <table class="table mb-0">
            <thead><tr><th>Code</th><th>Programme</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($results['programmes'] as $p): ?>
              <tr>
                <td><strong><?= e($p['code']) ?></strong></td>
                <td><?= e($p['name']) ?></td>
                <td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?= e(baseUrl('admin/programmes/form.php?id='.(int)$p['id'])) ?>">Open</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endif; ?>

  <?php endif; ?>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>