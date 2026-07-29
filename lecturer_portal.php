<?php
require __DIR__ . '/includes/config.php';
$u = currentUser();
if (!$u || $u['role'] !== 'lecturer') {
  header('Location: ' . app_url($u && $u['role'] === 'admin' ? 'admin_portal.php' : 'login.php'));
    exit;
}
$pageTitle = 'Lecturer Portal';
$db = getDB();

// Filter: session
$sessions = $db->query("SELECT cs.*, us.full_name AS lecturer_name FROM class_sessions cs JOIN users us ON us.id = cs.lecturer_id ORDER BY cs.session_date DESC")->fetchAll(PDO::FETCH_ASSOC);

$selectedSession = $_GET['session_id'] ?? ($sessions[0]['id'] ?? null);

$logs = [];
if ($selectedSession) {
    $stmt = $db->prepare("
        SELECT al.*, s.full_name, s.reg_no
        FROM attendance_logs al
        JOIN users s ON s.id = al.student_id
        WHERE al.session_id = ?
        ORDER BY al.logged_at DESC
    ");
    $stmt->execute([$selectedSession]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$totalStudents = $db->query("SELECT COUNT(*) c FROM users WHERE role='student'")->fetch()['c'];
$verifiedCount = count(array_filter($logs, fn($l) => $l['attendance_status'] === 'verified'));
$unverifiedCount = count(array_filter($logs, fn($l) => $l['attendance_status'] === 'unverified'));

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <h4 class="mb-1"><i class="bi bi-easel me-2"></i>Lecturer's Portal</h4>
  <p class="text-muted">Live attendance data pulled straight from Data Storage.</p>

  <div class="mb-4">
    <div class="col-auto">
      <label class="form-label small mb-1">Class session</label>
      <select name="session_id" class="form-select" onchange="this.form.submit()">
        <?php foreach ($sessions as $s): ?>
          <option value="<?= $s['id'] ?>" <?= $s['id'] == $selectedSession ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['title']) ?> — <?= htmlspecialchars($s['course']) ?> (<?= htmlspecialchars($s['session_date']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <div class="row g-3 mb-4">
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Enrolled Students</div>
        <div class="fs-3 fw-bold"><?= (int)$totalStudents ?></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Scan Attempts</div>
        <div class="fs-3 fw-bold"><?= count($logs) ?></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Verified Present</div>
        <div class="fs-3 fw-bold text-success"><?= $verifiedCount ?></div>
      </div>
    </div>
    <div class="col-sm-3">
      <div class="card p-3 text-center">
        <div class="text-muted small">Unverified</div>
        <div class="fs-3 fw-bold text-danger"><?= $unverifiedCount ?></div>
      </div>
    </div>
  </div>

  <div class="card p-3">
    <div class="table-responsive">
      <table class="table align-middle">
        <thead>
          <tr>
            <th>Student</th><th>Reg No</th><th>Method</th><th>Scan Result</th><th>Retries</th><th>Door Opened</th><th>Entered?</th><th>Attendance</th><th>Time</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$logs): ?>
            <tr><td colspan="9" class="text-center text-muted py-4">No scans recorded for this session yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($logs as $l): ?>
            <tr>
              <td><?= htmlspecialchars($l['full_name']) ?></td>
              <td><?= htmlspecialchars($l['reg_no']) ?></td>
              <td class="text-capitalize"><?= htmlspecialchars($l['scan_method']) ?></td>
              <td>
                <span class="status-pill <?= $l['scan_result'] === 'verified' ? 'status-verified' : 'status-unverified' ?>">
                  <?= htmlspecialchars(ucfirst($l['scan_result'])) ?>
                </span>
              </td>
              <td><?= (int)$l['retry_count'] ?></td>
              <td><?= $l['door_opened'] ? '<i class="bi bi-unlock text-success"></i>' : '<i class="bi bi-lock text-danger"></i>' ?></td>
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
