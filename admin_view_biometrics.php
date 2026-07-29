<?php
require __DIR__ . '/includes/config.php';
requireRole('admin');

$studentId = (int)($_GET['id'] ?? 0);
if (!$studentId) {
    header('Location: ' . app_url('admin_portal.php'));
    exit;
}

$db = getDB();
$student = $db->prepare('SELECT id, reg_no, full_name, course, biometric_registered FROM users WHERE id = ? AND role = ?');
$student->execute([$studentId, 'student']);
$student = $student->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    header('Location: ' . app_url('admin_portal.php'));
    exit;
}

// Fetch biometric data
$webauthn = $db->prepare('SELECT credential_id, label, created_at, updated_at FROM webauthn_credentials WHERE user_id = ?');
$webauthn->execute([$studentId]);
$webauthn = $webauthn->fetch(PDO::FETCH_ASSOC);

$faceTemplate = $db->prepare('SELECT descriptor_json, sample_count, created_at, updated_at FROM face_templates WHERE user_id = ?');
$faceTemplate->execute([$studentId]);
$faceTemplate = $faceTemplate->fetch(PDO::FETCH_ASSOC);

$pageTitle = 'Student Biometrics - ' . htmlspecialchars($student['full_name']);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-shield-check me-2"></i>Biometric Data</h4>
      <p class="text-muted mb-0"><?= htmlspecialchars($student['full_name']) ?> (<?= htmlspecialchars($student['reg_no']) ?>)</p>
    </div>
    <a href="<?= htmlspecialchars(app_url('admin_portal.php')) ?>" class="btn btn-outline-secondary">Back to Admin Portal</a>
  </div>

  <div class="row g-4">
    <!-- PLATFORM AUTHENTICATOR (Fingerprint/Face ID) -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-light">
          <h6 class="mb-0"><i class="bi bi-fingerprint me-2"></i>Platform Biometric (Fingerprint/Face ID)</h6>
        </div>
        <div class="card-body">
          <?php if ($webauthn): ?>
            <div class="alert alert-success mb-3">
              <i class="bi bi-check-circle me-2"></i>
              <strong>Enrolled</strong>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted small">Device Label</label>
              <p class="mb-0"><strong><?= htmlspecialchars($webauthn['label']) ?></strong></p>
            </div>
            <div class="row">
              <div class="col-6">
                <label class="form-label text-muted small">Enrolled On</label>
                <p class="mb-0"><code><?= htmlspecialchars(substr($webauthn['created_at'], 0, 19)) ?></code></p>
              </div>
              <div class="col-6">
                <label class="form-label text-muted small">Last Updated</label>
                <p class="mb-0"><code><?= htmlspecialchars(substr($webauthn['updated_at'], 0, 19)) ?></code></p>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label text-muted small">Credential ID (Encrypted)</label>
              <p class="small text-break mb-0"><code style="word-break: break-all;"><?= htmlspecialchars(substr($webauthn['credential_id'], 0, 60)) ?>...</code></p>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
              <i class="bi bi-info-circle me-2"></i>
              <strong>What this is:</strong> This credential is stored on the student's device and verified through Windows Hello, Touch ID, or Face ID biometric scanner.
            </div>
          <?php else: ?>
            <div class="alert alert-warning mb-3">
              <i class="bi bi-exclamation-triangle me-2"></i>
              <strong>Not Enrolled</strong>
            </div>
            <p class="text-muted">Student has not enrolled their device's fingerprint/Face ID yet.</p>
            <a href="<?= htmlspecialchars(app_url('student_biometric_enrollment.php?id=' . $studentId . '&admin=1')) ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-fingerprint me-1"></i>Enroll Now
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- CAMERA FACE RECOGNITION -->
    <div class="col-lg-6">
      <div class="card h-100">
        <div class="card-header bg-light">
          <h6 class="mb-0"><i class="bi bi-person-bounding-box me-2"></i>Camera Face Recognition</h6>
        </div>
        <div class="card-body">
          <?php if ($faceTemplate): ?>
            <div class="alert alert-success mb-3">
              <i class="bi bi-check-circle me-2"></i>
              <strong>Enrolled</strong>
            </div>
            <div class="mb-3">
              <label class="form-label text-muted small">Face Template Descriptor</label>
              <p class="small text-muted mb-0">128-dimensional face encoding (face-api.js)</p>
            </div>
            <div class="row">
              <div class="col-6">
                <label class="form-label text-muted small">Enrolled On</label>
                <p class="mb-0"><code><?= htmlspecialchars(substr($faceTemplate['created_at'], 0, 19)) ?></code></p>
              </div>
              <div class="col-6">
                <label class="form-label text-muted small">Last Updated</label>
                <p class="mb-0"><code><?= htmlspecialchars(substr($faceTemplate['updated_at'], 0, 19)) ?></code></p>
              </div>
            </div>
            <div class="mt-3">
              <label class="form-label text-muted small">Sample Count</label>
              <p class="mb-0"><strong><?= (int)$faceTemplate['sample_count'] ?> template(s)</strong></p>
            </div>
            <div class="alert alert-info small mt-3 mb-0">
              <i class="bi bi-info-circle me-2"></i>
              <strong>What this is:</strong> A machine learning-based face descriptor captured from the student's webcam using face-api.js. Used for real-time face matching during attendance.
            </div>
          <?php else: ?>
            <div class="alert alert-warning mb-3">
              <i class="bi bi-exclamation-triangle me-2"></i>
              <strong>Not Enrolled</strong>
            </div>
            <p class="text-muted">Student has not enrolled their face via webcam yet.</p>
            <a href="<?= htmlspecialchars(app_url('student_biometric_enrollment.php?id=' . $studentId . '&admin=1')) ?>" class="btn btn-primary btn-sm">
              <i class="bi bi-person-bounding-box me-1"></i>Enroll Now
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- BIOMETRIC METHODS SUMMARY -->
  <div class="card mt-4">
    <div class="card-header bg-light">
      <h6 class="mb-0"><i class="bi bi-list-check me-2"></i>Biometric Summary</h6>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-sm-6 col-md-4">
          <div class="d-flex align-items-center gap-2">
            <div class="fs-4">
              <?php if ($webauthn): ?>
                <i class="bi bi-check-circle-fill text-success"></i>
              <?php else: ?>
                <i class="bi bi-circle text-secondary"></i>
              <?php endif; ?>
            </div>
            <div>
              <small class="text-muted">Platform Biometric</small>
              <div class="small fw-semibold"><?= $webauthn ? 'Enrolled' : 'Pending' ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-md-4">
          <div class="d-flex align-items-center gap-2">
            <div class="fs-4">
              <?php if ($faceTemplate): ?>
                <i class="bi bi-check-circle-fill text-success"></i>
              <?php else: ?>
                <i class="bi bi-circle text-secondary"></i>
              <?php endif; ?>
            </div>
            <div>
              <small class="text-muted">Camera Face</small>
              <div class="small fw-semibold"><?= $faceTemplate ? 'Enrolled' : 'Pending' ?></div>
            </div>
          </div>
        </div>
        <div class="col-sm-6 col-md-4">
          <div class="d-flex align-items-center gap-2">
            <div class="fs-4">
              <i class="bi bi-circle text-secondary"></i>
            </div>
            <div>
              <small class="text-muted">Iris Scan</small>
              <div class="small fw-semibold">Not Available</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- BIOMETRIC TECHNOLOGY INFO -->
  <div class="alert alert-info mt-4 mb-0">
    <h6 class="alert-heading"><i class="bi bi-info-circle me-2"></i>How Biometrics Work</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <p class="small mb-2"><strong>🔐 Platform Biometric (Fingerprint/Face ID)</strong></p>
        <p class="small text-muted mb-0">
          When a student enrolls their fingerprint or Face ID, the browser uses your device's secure biometric authenticator (Windows Hello on Windows, Touch ID/Face ID on Mac, etc.). The fingerprint/face data <strong>never leaves the device</strong> - only a cryptographic credential is stored. This is the most secure method.
        </p>
      </div>
      <div class="col-md-6">
        <p class="small mb-2"><strong>📷 Camera Face Recognition</strong></p>
        <p class="small text-muted mb-0">
          When a student enrolls their face via webcam, face-api.js (a machine learning library) creates a 128-dimensional numerical descriptor of their face. This descriptor is stored in the database. During attendance, live video frames are compared against this descriptor for matching.
        </p>
      </div>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
