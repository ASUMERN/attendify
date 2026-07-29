<?php
require __DIR__ . '/includes/config.php';

$studentId = (int)($_GET['id'] ?? 0);
$adminMode = !empty($_GET['admin']);
$u = currentUser();

// If no ID provided, try to get current user
if (!$studentId) {
    if (!$u || $u['role'] !== 'student') {
        header('Location: ' . app_url('login.php'));
        exit;
    }
    $studentId = $u['id'];
} else {
    if (!$u || ($adminMode && $u['role'] !== 'admin') || (!$adminMode && ($u['role'] !== 'student' || (int)$u['id'] !== $studentId))) {
        header('Location: ' . app_url('login.php'));
        exit;
    }

    // Verify the student exists
    $db = getDB();
    $student = $db->prepare('SELECT id FROM users WHERE id = ? AND role = ?');
    $student->execute([$studentId, 'student']);
    if (!$student->fetch()) {
        header('Location: ' . app_url('login.php'));
        exit;
    }
}

$db = getDB();
$student = $db->prepare('SELECT id, reg_no, full_name, course, biometric_registered FROM users WHERE id = ?');
$student->execute([$studentId]);
$student = $student->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$pageTitle = 'Biometric Enrollment - ' . htmlspecialchars($student['full_name']);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-fingerprint me-2"></i>Biometric Enrollment</h4>
      <p class="text-muted mb-0">
        <?php if ($adminMode): ?>
          Enrolling: <strong><?= htmlspecialchars($student['full_name']) ?></strong> (<?= htmlspecialchars($student['reg_no']) ?>)
        <?php else: ?>
          Complete your biometric registration for <?= htmlspecialchars($student['course']) ?>
        <?php endif; ?>
      </p>
    </div>
    <?php if ($adminMode): ?>
      <a href="<?= htmlspecialchars(app_url('admin_portal.php')) ?>" class="btn btn-outline-secondary">Back to Admin Portal</a>
    <?php else: ?>
      <a href="<?= htmlspecialchars(app_url('student_portal.php')) ?>" class="btn btn-outline-secondary">Back to Portal</a>
    <?php endif; ?>
  </div>

  <div class="row justify-content-center">
    <div class="col-lg-8 col-xl-7">
      <div class="card p-4 shadow-sm">
        <div class="text-center mb-3">
          <i class="bi bi-shield-check" style="font-size:2.5rem;color:var(--accent);"></i>
          <h5 class="mt-2">Biometric Registration</h5>
          <small class="text-muted">Enroll fingerprint, Face ID, or face recognition</small>
        </div>

        <div id="enrollmentStatus" class="alert alert-light border small mb-3"></div>
        <div id="enrollmentPanel"></div>

        <div class="row g-3 mt-4">
          <div class="col-md-6">
            <div class="card h-100 p-3">
              <h6 class="mb-2"><i class="bi bi-fingerprint me-1"></i>Fingerprint / Face ID</h6>
              <p class="text-muted small mb-3">
                Use your device's built-in biometric scanner:<br>
                <small>🔒 Windows Hello (fingerprint/iris/face) on Windows<br>
                🔒 Touch ID on MacBook<br>
                🔒 Face ID on iPhone/iPad</small>
              </p>
              <p class="small text-primary mb-3">
                <i class="bi bi-shield-check me-1"></i>
                <strong>Most secure:</strong> Your fingerprint never leaves your device
              </p>
              <button class="btn btn-primary w-100" id="enrollWebauthnBtn">Enroll Your Device's Biometric</button>
            </div>
          </div>
          <div class="col-md-6">
            <div class="card h-100 p-3">
              <h6 class="mb-2"><i class="bi bi-person-bounding-box me-1"></i>Camera Face Recognition</h6>
              <p class="text-muted small mb-3">
                Use your webcam to capture a face template for attendance matching via AI/ML.
              </p>
              <p class="small text-success mb-3">
                <i class="bi bi-camera-fill me-1"></i>
                <strong>Flexible:</strong> Works on any device with a webcam
              </p>
              <button class="btn btn-outline-primary w-100" id="enrollFaceBtn">Enroll Via Webcam</button>
            </div>
          </div>
        </div>

        <div class="alert alert-success small mt-4 mb-0">
          <i class="bi bi-check-circle me-2"></i>
          <strong>Both methods work together:</strong> You can enroll both your device's fingerprint scanner AND camera face recognition for maximum flexibility and backup options.
        </div>
      </div>
    </div>
  </div>

</div>

<script>
window.APP_BASE_URL = <?= json_encode(rtrim(app_url(''), '/')) ?>;
window.STUDENT_BIOMETRIC_ID = <?= (int)$studentId ?>;
window.ADMIN_ENROLLMENT_MODE = <?= json_encode($adminMode ? true : false) ?>;
</script>
<script src="<?= htmlspecialchars(app_url('assets/js/enroll.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
