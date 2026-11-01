<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
require_once __DIR__ . '/../../includes/tracking.php';
requireRole('admin');

$pageTitle = 'Rate Students';
$activeNav = 'factors';

$factorId   = (int)(post('factor_id')   ?? get('factor_id', 0));
$semesterId = (int)(post('semester_id') ?? get('semester_id', (int)setting('current_semester_id', 1)));

$stmt = db()->prepare('SELECT * FROM influencing_factors WHERE id=?');
$stmt->execute([$factorId]); $factor = $stmt->fetch();
if (!$factor) { setFlash('danger','Factor not found.'); redirect(baseUrl('admin/factors/index.php')); }

$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id = s.academic_year_id WHERE s.status IN ('active','closed') ORDER BY y.start_date DESC, s.start_date")->fetchAll();

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $res = saveFactorRatings($factorId, $semesterId, (array)($_POST['rating'] ?? []), (int)$_SESSION['user_id']);
    if ($res['errors']) $errors = $res['errors'];
    else { setFlash('success', $res['saved'] . ' rating(s) saved.'); redirect(baseUrl('admin/factors/rate.php?factor_id='.$factorId.'&semester_id='.$semesterId)); }
}

// Student list with existing ratings for this factor + semester
$stmt = db()->prepare("
    SELECT s.id, s.registration_number, s.first_name, s.last_name,
           sf.rating AS existing_rating
    FROM students s
    LEFT JOIN student_factors sf ON sf.student_id = s.id AND sf.factor_id = ? AND sf.semester_id = ?
    WHERE s.status='active'
    ORDER BY s.registration_number
");
$stmt->execute([$factorId, $semesterId]);
$students = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $posted = (array)($_POST['rating'] ?? []);
    foreach ($students as &$s) if (array_key_exists((int)$s['id'], $posted)) $s['existing_rating'] = $posted[(int)$s['id']];
    unset($s);
}

$scaleLabels = $factor['scale_labels'] ? json_decode($factor['scale_labels'], true) : null;

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1>Rate — <?= e($factor['name']) ?></h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Factors</a></p></div>
  <?= errorList($errors) ?>
  <?php displayFlash(); ?>

  <div class="panel mb-3"><div class="panel-body">
    <form method="get" class="row g-2 align-items-end">
      <input type="hidden" name="factor_id" value="<?= $factorId ?>">
      <div class="col-md-5"><label class="form-label small mb-1">Semester</label>
        <select name="semester_id" class="form-select form-select-sm">
          <?= selectOptions($semesters, 'id', 'label', $semesterId ?: null) ?>
        </select></div>
      <div class="col-md-2"><button class="btn btn-sm btn-outline-secondary w-100">Load</button></div>
    </form>
  </div></div>

  <div class="panel"><div class="panel-body p-0">
    <?php if ($students): ?>
      <form method="post">
        <?= csrfField() ?>
        <input type="hidden" name="factor_id" value="<?= $factorId ?>">
        <input type="hidden" name="semester_id" value="<?= $semesterId ?>">
        <table class="table mb-0 align-middle">
          <thead><tr><th>Reg. No.</th><th>Name</th><th style="width:260px">Rating (<?= (int)$factor['scale_min'] ?> – <?= (int)$factor['scale_max'] ?>)</th></tr></thead>
          <tbody>
          <?php foreach ($students as $s): ?>
            <tr>
              <td><strong><?= e($s['registration_number']) ?></strong></td>
              <td><?= e($s['first_name'].' '.$s['last_name']) ?></td>
              <td>
                <select name="rating[<?= (int)$s['id'] ?>]" class="form-select form-select-sm">
                  <option value="">— not rated —</option>
                  <?php for ($r = (int)$factor['scale_min']; $r <= (int)$factor['scale_max']; $r++):
                      $label = $scaleLabels[(string)$r] ?? null;
                  ?>
                    <option value="<?= $r ?>" <?= (string)$s['existing_rating']===(string)$r ? 'selected':'' ?>>
                      <?= $r ?><?= $label ? ' — ' . e($label) : '' ?>
                    </option>
                  <?php endfor; ?>
                </select>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <div class="p-3 border-top">
          <button class="btn btn-primary"><i class="bi bi-save"></i> Save Ratings</button>
        </div>
      </form>
    <?php else: ?>
      <p class="empty p-4 mb-0">No active students.</p>
    <?php endif; ?>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>