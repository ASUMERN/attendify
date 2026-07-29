<?php
require __DIR__ . '/includes/config.php';

$u = currentUser();
if (!$u || !in_array($u['role'], ['admin', 'lecturer'], true)) {
    header('Location: ' . app_url('login.php'));
    exit;
}

$pageTitle = 'Face Scanner';
require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-person-bounding-box me-2"></i>Face Scanner</h4>
      <p class="text-muted mb-0">Recognise enrolled students by camera face scan only.</p>
    </div>
    <span id="scannerStatus" class="status-pill status-pending">Scanner idle</span>
  </div>

  <div class="row g-4 align-items-start">
    <div class="col-lg-8">
      <div class="card p-3">
        <div class="mb-3">
          <label for="classSession" class="form-label">Class being scanned</label>
          <select id="classSession" class="form-select" disabled>
            <option value="">Loading today's classes...</option>
          </select>
        </div>
        <div class="ratio ratio-4x3 bg-dark rounded overflow-hidden border border-secondary-subtle">
          <video id="faceScannerVideo" autoplay muted playsinline style="width:100%;height:100%;object-fit:cover;"></video>
        </div>
        <div class="d-flex gap-2 justify-content-center flex-wrap mt-3">
          <button type="button" class="btn btn-primary" id="startScannerBtn">
            <i class="bi bi-camera-video me-1"></i> Start Scanner
          </button>
          <button type="button" class="btn btn-outline-primary" id="scanOnceBtn" disabled>
            <i class="bi bi-search me-1"></i> Scan Face
          </button>
          <button type="button" class="btn btn-outline-secondary" id="stopScannerBtn" disabled>
            <i class="bi bi-stop-circle me-1"></i> Stop
          </button>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="card p-4 text-center">
        <div id="scannerIcon" class="scan-pad mb-3">
          <i class="bi bi-person-bounding-box" style="font-size:3rem;color:var(--accent);"></i>
        </div>
        <div class="text-muted small mb-2">Recognition result</div>
        <div id="recognitionResult" class="fs-4 fw-bold text-break">Waiting for scan</div>
        <div id="scannerMessage" class="small text-muted mt-3">Start the scanner, face the camera, then scan.</div>
      </div>
    </div>
  </div>
</div>

<script>
window.APP_BASE_URL = <?= json_encode(rtrim(app_url(''), '/')) ?>;
</script>
<script src="<?= htmlspecialchars(app_url('assets/js/face_scanner.js')) ?>"></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
