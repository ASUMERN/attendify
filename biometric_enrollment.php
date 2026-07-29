<?php
require __DIR__ . '/includes/config.php';

$u = currentUser();
if (!$u || $u['role'] !== 'student') {
    header('Location: ' . app_url('login.php'));
    exit;
}

$pageTitle = 'Biometric Enrollment';
$flashMessage = $_SESSION['flash_message'] ?? null;
$flashType = $_SESSION['flash_type'] ?? 'info';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
      <div class="card p-4 shadow-sm">
        <div class="text-center mb-3">
          <i class="bi bi-shield-check" style="font-size:2.5rem;color:var(--accent);"></i>
          <h4 class="mt-2 mb-0">Biometric Enrollment</h4>
          <small class="text-muted">Enroll fingerprint / Face ID or camera face recognition for <?= htmlspecialchars($u['full_name']) ?></small>
        </div>

        <?php if ($flashMessage): ?>
          <div class="alert alert-<?= htmlspecialchars($flashType) ?> py-2"><?= htmlspecialchars($flashMessage) ?></div>
        <?php endif; ?>

        <div class="alert alert-warning small">
          Iris scan is not available in a standard browser. Use fingerprint/Face ID or face recognition instead.
        </div>

        <div class="row g-3">
          <div class="col-md-6">
            <div class="card h-100 p-3">
              <h6 class="mb-2"><i class="bi bi-fingerprint me-1"></i>Fingerprint / Face ID</h6>
              <p class="text-muted small mb-3">Register this computer or device with your built-in platform biometric prompt.</p>
              <button class="btn btn-primary w-100" id="enrollWebauthnBtn">Enroll Platform Biometrics</button>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card h-100 p-3">
              <h6 class="mb-2"><i class="bi bi-person-bounding-box me-1"></i>Camera Face Recognition</h6>
              <p class="text-muted small mb-3">Use your webcam to capture a face template that will be matched at attendance time.</p>
              <button class="btn btn-outline-primary w-100" id="enrollFaceBtn">Enroll Face</button>
            </div>
          </div>
        </div>

        <div class="mt-4">
          <div id="enrollmentStatus" class="alert alert-light border small mb-3">Choose a biometric method to enroll.</div>
          <div id="enrollmentPanel"></div>
        </div>

        <div class="d-flex justify-content-between gap-2 flex-wrap mt-3">
          <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(app_url('logout.php')) ?>">Logout</a>
          <a class="btn btn-success" href="<?= htmlspecialchars(app_url('scan.php')) ?>">Continue to Attendance</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.APP_BASE_URL = <?= json_encode(rtrim(app_url(''), '/')) ?>;
</script>
<script src="<?= htmlspecialchars(app_url('assets/js/enroll.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>