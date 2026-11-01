<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/crud.php';
requireRole('admin');

$pageTitle = 'Grading Scale';
$activeNav = 'settings';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('UPDATE grading_scales SET min_percentage=?, max_percentage=?, grade_point=?, description=? WHERE id=?');
        foreach ((array)($_POST['scales'] ?? []) as $id => $row) {
            $id = (int)$id;
            $stmt->execute([
                (float)$row['min'], (float)$row['max'],
                (float)$row['point'], trim((string)$row['description']), $id,
            ]);
        }
        $pdo->commit();
        setFlash('success', 'Grading scale updated.');
        redirect(baseUrl('admin/settings/grading.php'));
    } catch (Throwable $t) {
        $pdo->rollBack();
        $errors[] = 'Could not save grading scale.';
    }
}

$scales = db()->query('SELECT * FROM grading_scales ORDER BY min_percentage DESC')->fetchAll();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head"><h1>Grading Scale</h1>
    <p class="muted mb-0"><a href="index.php">&larr; Back to Settings</a></p></div>
  <?= errorList($errors) ?>
  <?php displayFlash(); ?>
  <div class="panel"><div class="panel-body">
    <form method="post">
      <?= csrfField() ?>
      <table class="table align-middle">
        <thead><tr><th>Grade</th><th>Min %</th><th>Max %</th><th>Grade Point</th><th>Description</th></tr></thead>
        <tbody>
        <?php foreach ($scales as $s): ?>
          <tr>
            <td><strong><?= e($s['grade']) ?></strong></td>
            <td><input type="number" step="0.01" name="scales[<?= (int)$s['id'] ?>][min]" class="form-control form-control-sm" value="<?= e((string)$s['min_percentage']) ?>"></td>
            <td><input type="number" step="0.01" name="scales[<?= (int)$s['id'] ?>][max]" class="form-control form-control-sm" value="<?= e((string)$s['max_percentage']) ?>"></td>
            <td><input type="number" step="0.01" name="scales[<?= (int)$s['id'] ?>][point]" class="form-control form-control-sm" value="<?= e((string)$s['grade_point']) ?>"></td>
            <td><input type="text" name="scales[<?= (int)$s['id'] ?>][description]" class="form-control form-control-sm" value="<?= e($s['description']) ?>"></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <button class="btn btn-primary">Save Grading Scale</button>
    </form>
    <div class="alert alert-warning mt-3 mb-0 py-2 small">
      <strong>Note:</strong> changing the grading scale affects <em>future</em> calculations only. Existing results keep the grade they were awarded when entered.
    </div>
  </div></div>
</main>
<?php include __DIR__ . '/../../includes/footer.php'; ?>