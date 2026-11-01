<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/crud.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/mpesa.php';
requireRole('student');

$pageTitle = 'Pay Fees';
$activeNav = 'pay';

$stud = currentProfile();
if (!$stud) { echo '<p>Student profile not found.</p>'; exit; }
$studId = (int)$stud['id'];

$semId = (int)get('semester_id', (int)setting('current_semester_id', 1));
$semesters = db()->query("SELECT s.id, CONCAT(y.year_name,' - ',s.semester_name) AS label FROM semesters s JOIN academic_years y ON y.id=s.academic_year_id WHERE s.status='active' ORDER BY y.start_date DESC")->fetchAll();
$fee = (float)setting('registration_fee', 2500);

$errors = [];
$result = null;

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = post('action', '');

    if ($action === 'pay') {
        $phone = post('phone', '');
        $amount = (float)post('amount', $fee);
        $result = initiateStkPush($studId, $semId, $amount, $phone, 'Registration fees');
        if (!$result['ok']) $errors[] = $result['error'];
    }
}

// Fetch payment history
$stmt = db()->prepare("SELECT * FROM payments WHERE student_id=? ORDER BY created_at DESC LIMIT 15");
$stmt->execute([$studId]);
$recent = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/sidebar.php';
?>
<main class="content">
  <div class="page-head d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div><h1>Pay Fees via M-Pesa</h1>
      <p class="muted mb-0">Pay your semester registration fees using the STK Push prompt.</p></div>
    <form method="get">
      <select name="semester_id" class="form-select form-select-sm" onchange="this.form.submit()">
        <?= selectOptions($semesters, 'id', 'label', $semId) ?>
      </select>
    </form>
  </div>

  <?php displayFlash(); ?>
  <?= errorList($errors) ?>

  <?php if ($result && $result['ok']): ?>
    <div class="alert alert-info">
      <i class="bi bi-phone"></i>
      <strong>STK Push Sent!</strong> <?= e($result['message']) ?>
      <div class="mt-2">
        <small class="muted">Payment ID: #<?= (int)$result['payment_id'] ?> &middot; Ref: <?= e($result['checkout_request_id']) ?></small>
      </div>
    </div>

    <div class="panel mb-4"><div class="panel-body text-center py-5">
      <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;"></div>
      <h5>Waiting for you to enter your PIN...</h5>
      <p class="text-muted small mb-3">Please check your phone. An M-Pesa prompt has been sent. Enter your PIN to complete the payment.<br>This page will update automatically.</p>
      
      <script>
          // Poll the page every 3 seconds to check if the status changed from Pending
          setInterval(function() {
              fetch(window.location.href)
                .then(r => r.text())
                .then(html => {
                    // If the page no longer contains the "Pending" badge, reload it
                    if (!html.includes('badge bg-warning text-dark">Pending')) {
                        location.reload();
                    }
                });
          }, 3000);
      </script>
    </div></div>
  <?php endif; ?>

  <div class="panel mb-4"><div class="panel-body">
    <h5 class="mb-3">New Payment</h5>
    <form method="post" class="row g-3">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="pay">
      <div class="col-md-4"><label class="form-label">Phone (M-Pesa)</label>
        <input type="text" name="phone" class="form-control" placeholder="07XX XXX XXX" required
               value="<?= e($stud['phone'] ?? '') ?>"></div>
      <div class="col-md-4"><label class="form-label">Amount (KES)</label>
        <input type="number" step="1" min="1" name="amount" class="form-control" required
               value="<?= (int)$fee ?>"></div>
      <div class="col-md-4 d-flex align-items-end">
        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-send"></i> Pay with M-Pesa</button>
      </div>
    </form>
    <small class="muted d-block mt-2">Fee per semester: KES <?= number_format($fee, 2) ?>. Payment is processed via Safaricom STK Push.</small>
  </div></div>

  <div class="panel"><div class="panel-head"><h5>My Payment History</h5></div>
    <div class="panel-body p-0">
      <?php if ($recent): ?>
        <table class="table mb-0 align-middle">
          <thead><tr><th>Date</th><th>Purpose</th><th>Amount</th><th>Phone</th><th>Receipt</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $p): ?>
            <tr>
              <td><small><?= e(date('d M Y H:i', strtotime($p['created_at']))) ?></small></td>
              <td><?= e($p['purpose']) ?></td>
              <td>KES <?= number_format((float)$p['amount'], 2) ?></td>
              <td><?= e($p['phone']) ?></td>
              <td><?= e($p['mpesa_receipt_number'] ?? '—') ?></td>
              <td>
                <?php if ($p['status'] === 'success'): ?><span class="badge bg-success">Success</span>
                <?php elseif ($p['status'] === 'pending'): ?><span class="badge bg-warning text-dark">Pending</span>
                <?php elseif ($p['status'] === 'failed'): ?><span class="badge bg-danger">Failed</span>
                <?php else: ?><span class="badge bg-secondary"><?= e(ucfirst($p['status'])) ?></span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php else: ?>
        <p class="empty p-4 mb-0">No payments yet.</p>
      <?php endif; ?>
    </div>
  </div>
</main>
<?php include __DIR__ . '/../includes/footer.php'; ?>