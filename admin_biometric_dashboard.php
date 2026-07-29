<?php
require __DIR__ . '/includes/config.php';
requireRole('admin');

$pageTitle = 'Biometric Status Dashboard';
$db = getDB();

// Get all students with their biometric status
$students = $db->query("
    SELECT 
        u.id, 
        u.reg_no, 
        u.full_name, 
        u.course,
        COUNT(DISTINCT wc.id) as has_webauthn,
        COUNT(DISTINCT ft.id) as has_face_template
    FROM users u
    LEFT JOIN webauthn_credentials wc ON wc.user_id = u.id
    LEFT JOIN face_templates ft ON ft.user_id = u.id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY u.full_name
")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-shield-lock me-2"></i>Biometric Status Dashboard</h4>
      <p class="text-muted mb-0">Overview of all enrolled students and their biometric data</p>
    </div>
    <a href="<?= htmlspecialchars(app_url('admin_portal.php')) ?>" class="btn btn-outline-secondary">Back to Admin Portal</a>
  </div>

  <!-- STATISTICS -->
  <div class="row g-3 mb-4">
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Total Students</div>
        <div class="fs-3 fw-bold"><?= count($students) ?></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Platform Biometric</div>
        <div class="fs-3 fw-bold text-success"><?= count(array_filter($students, fn($s) => $s['has_webauthn'])) ?></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Face Recognition</div>
        <div class="fs-3 fw-bold text-info"><?= count(array_filter($students, fn($s) => $s['has_face_template'])) ?></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Fully Enrolled</div>
        <div class="fs-3 fw-bold text-primary"><?= count(array_filter($students, fn($s) => $s['has_webauthn'] && $s['has_face_template'])) ?></div>
      </div>
    </div>
  </div>

  <!-- STUDENT BIOMETRIC TABLE -->
  <div class="card">
    <div class="card-header bg-light">
      <h6 class="mb-0">Student Biometric Enrollment Status</h6>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Reg No</th>
            <th>Full Name</th>
            <th>Course</th>
            <th class="text-center"><i class="bi bi-fingerprint"></i> Platform Biometric</th>
            <th class="text-center"><i class="bi bi-person-bounding-box"></i> Face Recognition</th>
            <th class="text-center">Completion</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$students): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No students registered yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($students as $s): 
            $completion = 0;
            if ($s['has_webauthn']) $completion += 50;
            if ($s['has_face_template']) $completion += 50;
          ?>
            <tr>
              <td><strong><?= htmlspecialchars($s['reg_no']) ?></strong></td>
              <td><?= htmlspecialchars($s['full_name']) ?></td>
              <td><?= htmlspecialchars($s['course']) ?></td>
              <td class="text-center">
                <?php if ($s['has_webauthn']): ?>
                  <span class="badge bg-success" title="Platform biometric enrolled">
                    <i class="bi bi-check-circle me-1"></i>Enrolled
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary" title="Not enrolled">
                    <i class="bi bi-circle me-1"></i>Pending
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <?php if ($s['has_face_template']): ?>
                  <span class="badge bg-info" title="Face recognition template stored">
                    <i class="bi bi-check-circle me-1"></i>Enrolled
                  </span>
                <?php else: ?>
                  <span class="badge bg-secondary" title="Not enrolled">
                    <i class="bi bi-circle me-1"></i>Pending
                  </span>
                <?php endif; ?>
              </td>
              <td class="text-center">
                <div class="progress" style="height: 24px;">
                  <div class="progress-bar bg-success" role="progressbar" style="width: <?= $completion ?>%" aria-valuenow="<?= $completion ?>" aria-valuemin="0" aria-valuemax="100">
                    <?= $completion ?>%
                  </div>
                </div>
              </td>
              <td>
                <a href="<?= htmlspecialchars(app_url('admin_view_biometrics.php?id=' . $s['id'])) ?>" class="btn btn-sm btn-info" title="View stored biometric data">
                  <i class="bi bi-eye me-1"></i>View
                </a>
                <?php if ($completion < 100): ?>
                  <a href="<?= htmlspecialchars(app_url('student_biometric_enrollment.php?id=' . $s['id'] . '&admin=1')) ?>" class="btn btn-sm btn-warning">
                    <i class="bi bi-arrow-repeat me-1"></i>Continue
                  </a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- BIOMETRIC TECHNOLOGY EXPLANATION -->
  <div class="row g-4 mt-4">
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header bg-light">
          <h6 class="mb-0"><i class="bi bi-fingerprint me-2"></i>Platform Biometric (Fingerprint/Face ID)</h6>
        </div>
        <div class="card-body">
          <p class="small mb-3">WebAuthn-based authentication using device hardware:</p>
          <ul class="small list-unstyled">
            <li><i class="bi bi-check-circle text-success me-2"></i>🔐 Fingerprint stored on device (Windows Hello, Touch ID)</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>🔐 Face ID recognition via device hardware</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>🔐 Never transmitted in plain text</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>🔐 Cryptographic credential verification</li>
            <li><i class="bi bi-check-circle text-success me-2"></i>⚡ Fastest method - instant verification</li>
          </ul>
          <div class="alert alert-success small mt-3 mb-0">
            <strong>Most Secure:</strong> The student's biometric data remains on their device and never leaves it.
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card">
        <div class="card-header bg-light">
          <h6 class="mb-0"><i class="bi bi-person-bounding-box me-2"></i>Camera Face Recognition</h6>
        </div>
        <div class="card-body">
          <p class="small mb-3">AI/ML-based face matching using webcam:</p>
          <ul class="small list-unstyled">
            <li><i class="bi bi-check-circle text-info me-2"></i>📷 Face descriptor (128-dimensional vector) stored</li>
            <li><i class="bi bi-check-circle text-info me-2"></i>📷 face-api.js neural network processing</li>
            <li><i class="bi bi-check-circle text-info me-2"></i>📷 Real-time webcam frame analysis</li>
            <li><i class="bi bi-check-circle text-info me-2"></i>📷 Euclidean distance matching algorithm</li>
            <li><i class="bi bi-check-circle text-info me-2"></i>📷 Works on any device with webcam</li>
          </ul>
          <div class="alert alert-info small mt-3 mb-0">
            <strong>Flexible & Convenient:</strong> Works as backup and on devices without built-in fingerprint scanner.
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
