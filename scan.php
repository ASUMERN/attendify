<?php
require __DIR__ . '/includes/config.php';
requireRole('student');
$u = currentUser();
$pageTitle = 'Scan & Enter';

$db = getDB();
$stmt = $db->prepare("SELECT cs.*, l.full_name AS lecturer_name FROM class_sessions cs
                       JOIN users l ON l.id = cs.lecturer_id
                       WHERE cs.course = ? AND cs.session_date = date('now')
                       ORDER BY cs.id DESC LIMIT 1");
$stmt->execute([$u['course']]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h4 class="mb-1"><i class="bi bi-door-open me-2"></i>Student Attendance</h4>
  <p class="text-muted">Follow the verification flow to be marked present.</p>

  <?php if (!$session): ?>
    <div class="alert alert-warning">No class session scheduled for your course today.</div>
  <?php else: ?>

  <div class="row g-4">
    <!-- Flow diagram reference -->
    <div class="col-lg-4">
      <div class="card p-3 h-100">
        <h6 class="text-uppercase text-muted small mb-3">Process Flow</h6>
        <div class="d-flex flex-column align-items-center gap-2">
          <div class="flow-box w-100">Fingerprint / Eye / Face Recognition</div>
          <div class="flow-arrow"><i class="bi bi-arrow-down"></i></div>
          <div class="flow-diamond"><span>Verified?</span></div>
          <div class="small text-muted text-center">If unverified → re-enter input (retry)</div>
          <div class="flow-arrow"><i class="bi bi-arrow-down"></i></div>
          <div class="flow-box w-100">Door of the Class Opens</div>
          <div class="flow-arrow"><i class="bi bi-arrow-down"></i></div>
          <div class="flow-diamond"><span>Student Enters?</span></div>
          <div class="flow-arrow"><i class="bi bi-arrow-down"></i></div>
          <div class="d-flex gap-2 w-100">
            <div class="flow-box flex-fill" style="font-size:.75rem;">Attendance Verified</div>
            <div class="flow-box flex-fill" style="font-size:.75rem;">Attendance Unverified</div>
          </div>
          <div class="flow-arrow"><i class="bi bi-arrow-down"></i></div>
          <div class="flow-box w-100">Data Storage</div>
          <div class="flow-arrow"><i class="bi bi-arrow-down"></i></div>
          <div class="d-flex gap-2 w-100">
            <div class="flow-box flex-fill" style="font-size:.75rem;">Student Portal</div>
            <div class="flow-box flex-fill" style="font-size:.75rem;">Lecturer's Portal</div>
          </div>
        </div>
      </div>
    </div>

    <!-- Interactive simulator -->
    <div class="col-lg-8">
      <div class="card p-4 h-100">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h6 class="mb-0"><?= htmlspecialchars($session['title']) ?></h6>
            <small class="text-muted"><?= htmlspecialchars($session['course']) ?> · <?= htmlspecialchars($session['lecturer_name']) ?> · <?= htmlspecialchars($session['start_time']) ?>–<?= htmlspecialchars($session['end_time']) ?></small>
          </div>
          <span id="stepBadge" class="status-pill status-pending">Step 1 · Choose method</span>
        </div>

        <!-- STEP 1: choose method -->
        <div id="step-method">
          <p class="mb-2 fw-semibold"><span class="step-num me-2">1</span>Select a biometric method</p>
          <div class="d-flex gap-2 flex-wrap mb-4">
            <button class="btn btn-outline-primary method-btn" data-method="fingerprint"><i class="bi bi-fingerprint me-1"></i> Fingerprint / Face ID</button>
            <button class="btn btn-outline-primary method-btn" data-method="face"><i class="bi bi-person-bounding-box me-1"></i> Camera Face Recognition</button>
            <button class="btn btn-outline-secondary method-btn" data-method="eye"><i class="bi bi-eye me-1"></i> Iris Scan (native app required)</button>
          </div>
          <div class="small text-muted">Fingerprint / Face ID uses your browser's WebAuthn support. Camera face recognition uses your webcam and runs in the browser.</div>
        </div>

        <!-- STEP 2: scanning -->
        <div id="step-scan" class="d-none text-center">
          <p class="mb-3 fw-semibold text-start"><span class="step-num me-2">2</span>Scanning &amp; verifying...</p>
          <div id="scanPad" class="scan-pad mb-3"><i class="bi bi-fingerprint" style="font-size:3rem;color:var(--accent);" id="scanIcon"></i></div>
          <div id="scanResultMsg" class="mb-2 fw-semibold"></div>
          <div id="biometricPanel" class="mb-3"></div>
          <div id="retryArea" class="d-none">
            <p class="small text-muted">Not recognised — please re-position and try again.</p>
            <button class="btn btn-primary" id="retryBtn"><i class="bi bi-arrow-repeat me-1"></i> Re-enter Input</button>
          </div>
        </div>

        <!-- STEP 3: door -->
        <div id="step-door" class="d-none text-center">
          <p class="mb-3 fw-semibold text-start"><span class="step-num me-2">3</span>Door status</p>
          <div id="doorEl" class="door mb-3"></div>
          <p id="doorMsg" class="fw-semibold text-success">Verified — the classroom door has opened!</p>
          <p class="small text-muted mb-3">Simulate the doorway sensor: did the student walk into the classroom?</p>
          <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-success" id="enteredBtn"><i class="bi bi-box-arrow-in-right me-1"></i> Student Entered Classroom</button>
            <button class="btn btn-outline-danger" id="notEnteredBtn"><i class="bi bi-x-circle me-1"></i> Did Not Enter</button>
          </div>
        </div>

        <!-- STEP 4: result -->
        <div id="step-result" class="d-none text-center">
          <p class="mb-3 fw-semibold text-start"><span class="step-num me-2">4</span>Attendance recorded</p>
          <div id="resultIcon" class="mb-2" style="font-size:3rem;"></div>
          <div id="resultMsg" class="fw-semibold mb-3"></div>
          <p class="small text-muted">Saved to Data Storage — visible on your Student Portal and the Lecturer's Portal.</p>
          <a href="<?= htmlspecialchars(app_url('student_portal.php')) ?>" class="btn btn-primary"><i class="bi bi-clock-history me-1"></i> View My Attendance</a>
          <button class="btn btn-outline-secondary" id="scanAgainBtn">Scan Again</button>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<script>
const SESSION_ID = <?= (int)($session['id'] ?? 0) ?>;
window.APP_BASE_URL = <?= json_encode(rtrim(app_url(''), '/')) ?>;
</script>
<script src="<?= htmlspecialchars(app_url('assets/js/scan.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
