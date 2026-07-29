<?php
require __DIR__ . '/includes/config.php';
requireRole('student');
$u = currentUser();
$pageTitle = 'My Attendance';

$db = getDB();
$stmt = $db->prepare("
    SELECT al.*, cs.title, cs.course, cs.session_date
    FROM attendance_logs al
    JOIN class_sessions cs ON cs.id = al.session_id
    WHERE al.student_id = ?
    ORDER BY al.logged_at DESC
");
$stmt->execute([$u['id']]);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalVerified = count(array_filter($logs, fn($l) => $l['attendance_status'] === 'verified'));
$totalUnverified = count(array_filter($logs, fn($l) => $l['attendance_status'] === 'unverified'));

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h4 class="mb-1"><i class="bi bi-clock-history me-2"></i>My Attendance History</h4>
  <p class="text-muted">Reg No: <?= htmlspecialchars($u['reg_no']) ?> · <?= htmlspecialchars($u['course']) ?></p>

  <?php if (!$u['biometric_registered']): ?>
  <div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Biometric not enrolled!</strong>
    <a href="<?= htmlspecialchars(app_url('student_biometric_enrollment.php')) ?>" class="btn btn-sm btn-warning ms-2">
      <i class="bi bi-fingerprint me-1"></i>Enroll Now
    </a>
  </div>
  <?php endif; ?>

  <div class="row g-3 mb-4">
    <div class="col-sm-4">
      <div class="card p-3 text-center">
        <div class="text-muted small">Total Scans</div>
        <div class="fs-3 fw-bold"><?= count($logs) ?></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card p-3 text-center">
        <div class="text-muted small">Verified</div>
        <div class="fs-3 fw-bold text-success"><?= $totalVerified ?></div>
      </div>
    </div>
    <div class="col-sm-4">
      <div class="card p-3 text-center">
        <div class="text-muted small">Unverified</div>
        <div class="fs-3 fw-bold text-danger"><?= $totalUnverified ?></div>
      </div>
    </div>
  </div>

  <div class="card p-3">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Date</th><th>Class</th><th>Method</th><th>Scan Result</th><th>Retries</th><th>Entered?</th><th>Attendance</th><th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$logs): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">No attendance records yet. Go to <a href="<?= htmlspecialchars(app_url('scan.php')) ?>">Scan &amp; Enter</a>.</td></tr>
          <?php endif; ?>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td><?= htmlspecialchars($l['session_date']) ?></td>
              <td><?= htmlspecialchars($l['title']) ?><br><small class="text-muted"><?= htmlspecialchars($l['course']) ?></small></td>
              <td class="text-capitalize"><?= htmlspecialchars($l['scan_method']) ?></td>
              <td>
                <span class="status-pill <?= $l['scan_result'] === 'verified' ? 'status-verified' : 'status-unverified' ?>">
                  <?= htmlspecialchars(ucfirst($l['scan_result'])) ?>
                </span>
              </td>
              <td><?= (int)$l['retry_count'] ?></td>
              <td>
                <?php if ($l['entered_classroom'] === null): ?>
                  <span class="text-muted">—</span>
                <?php else: ?>
                  <?= $l['entered_classroom'] ? '<i class="bi bi-check-circle text-success"></i> Yes' : '<i class="bi bi-x-circle text-danger"></i> No' ?>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($l['attendance_status']): ?>
                  <span class="status-pill <?= $l['attendance_status'] === 'verified' ? 'status-verified' : 'status-unverified' ?>">
                    <?= htmlspecialchars(ucfirst($l['attendance_status'])) ?>
                  </span>
                <?php else: ?>
                  <span class="text-muted small">pending</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= htmlspecialchars($l['logged_at']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
