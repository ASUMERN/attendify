<?php
require __DIR__ . '/includes/config.php';
requireRole('admin');
$u = currentUser();

$pageTitle = 'Admin Portal';
$db = getDB();
$action = $_GET['action'] ?? '';
$studentId = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// DELETE student
if ($action === 'delete' && $studentId && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db->prepare('DELETE FROM users WHERE id = ? AND role = ?')->execute([$studentId, 'student']);
        $db->prepare('DELETE FROM webauthn_credentials WHERE user_id = ?')->execute([$studentId]);
        $db->prepare('DELETE FROM face_templates WHERE user_id = ?')->execute([$studentId]);
        $success = 'Student deleted successfully.';
        header('Location: ' . app_url('admin_portal.php'));
        exit;
    } catch (Exception $e) {
        $error = 'Could not delete student: ' . $e->getMessage();
    }
}

// EDIT student
if ($action === 'edit' && $studentId) {
    $student = $db->prepare('SELECT * FROM users WHERE id = ? AND role = ?');
    $student->execute([$studentId, 'student']);
    $student = $student->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        header('Location: ' . app_url('admin_portal.php'));
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $fullName = trim($_POST['full_name'] ?? '');
        $course = trim($_POST['course'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $biometricRegistered = !empty($_POST['biometric_registered']) ? 1 : 0;

        if ($fullName === '' || $course === '' || $username === '') {
            $error = 'Please complete all required fields.';
        } else {
            try {
                if ($password) {
                    $db->prepare('UPDATE users SET full_name = ?, course = ?, username = ?, password = ?, biometric_registered = ? WHERE id = ?')
                        ->execute([$fullName, $course, $username, password_hash($password, PASSWORD_DEFAULT), $biometricRegistered, $studentId]);
                } else {
                    $db->prepare('UPDATE users SET full_name = ?, course = ?, username = ?, biometric_registered = ? WHERE id = ?')
                        ->execute([$fullName, $course, $username, $biometricRegistered, $studentId]);
                }
                $success = 'Student updated successfully.';
                header('Location: ' . app_url('admin_portal.php'));
                exit;
            } catch (PDOException $e) {
                $error = 'Could not update student: ' . $e->getMessage();
            }
        }
    }
}

// Fetch all students
$students = $db->query("SELECT id, reg_no, full_name, username, course, biometric_registered, created_at
                       FROM users
                       WHERE role = 'student'
                       ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-gear me-2"></i>Admin Portal</h4>
      <p class="text-muted mb-0">Manage students, biometrics, courses, and system data.</p>
    </div>
    <div>
      <a href="<?= htmlspecialchars(app_url('admin_biometric_dashboard.php')) ?>" class="btn btn-info">
        <i class="bi bi-shield-lock me-1"></i> Biometric Dashboard
      </a>
      <a href="<?= htmlspecialchars(app_url('register_student.php')) ?>" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Register Student
      </a>
      <a href="<?= htmlspecialchars(app_url('logout.php')) ?>" class="btn btn-outline-secondary">Logout</a>
    </div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <!-- PENDING BIOMETRIC ENROLLMENT -->
  <?php
  $pendingBiometric = array_filter($students, fn($s) => !$s['biometric_registered']);
  if (!empty($pendingBiometric)):
  ?>
  <div class="alert alert-warning mb-4">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong><?= count($pendingBiometric) ?> student(s) need biometric enrollment.</strong>
    Use the "Biometric" button below to enroll each student's fingerprint/face recognition.
  </div>
  <?php endif; ?>

  <!-- EDIT STUDENT FORM -->
  <?php if ($action === 'edit' && $student): ?>
    <div class="card p-4 mb-4">
      <h6 class="mb-3">Edit Student</h6>
      <form method="post">
        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label">Student ID / Reg No</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($student['reg_no']) ?>" disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label">Full Name *</label>
            <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($student['full_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Course *</label>
            <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($student['course']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Username *</label>
            <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($student['username']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">New Password (leave blank to keep current)</label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password or leave blank">
          </div>
          <div class="col-md-6">
            <label class="form-check-label d-block mt-4">
              <input class="form-check-input" type="checkbox" name="biometric_registered" <?= $student['biometric_registered'] ? 'checked' : '' ?>>
              Biometric Registered
            </label>
          </div>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary">Save Changes</button>
          <a href="<?= htmlspecialchars(app_url('admin_portal.php')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
      </form>
    </div>
  <?php endif; ?>

  <!-- STUDENTS LIST -->
  <div class="card">
    <div class="card-header bg-light">
      <h6 class="mb-0">All Students (<?= count($students) ?>)</h6>
    </div>
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th>Reg No</th>
            <th>Full Name</th>
            <th>Username</th>
            <th>Course</th>
            <th>Biometric Status</th>
            <th>Created</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$students): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No students registered yet.</td></tr>
          <?php endif; ?>
          <?php foreach ($students as $s): ?>
            <tr>
              <td><strong><?= htmlspecialchars($s['reg_no']) ?></strong></td>
              <td><?= htmlspecialchars($s['full_name']) ?></td>
              <td><code><?= htmlspecialchars($s['username']) ?></code></td>
              <td><?= htmlspecialchars($s['course']) ?></td>
              <td>
                <?php if ($s['biometric_registered']): ?>
                  <span class="status-pill status-verified"><i class="bi bi-check-circle me-1"></i>Registered</span>
                <?php else: ?>
                  <span class="status-pill status-unverified"><i class="bi bi-x-circle me-1"></i>Pending</span>
                <?php endif; ?>
              </td>
              <td class="small text-muted"><?= htmlspecialchars(substr($s['created_at'], 0, 10)) ?></td>
              <td>
                <a href="<?= htmlspecialchars(app_url('admin_view_biometrics.php?id=' . $s['id'])) ?>" class="btn btn-sm btn-info" title="View stored biometrics">
                  <i class="bi bi-eye me-1"></i>View
                </a>
                <a href="<?= htmlspecialchars(app_url('student_biometric_enrollment.php?id=' . $s['id'] . '&admin=1')) ?>" class="btn btn-sm btn-outline-success">
                  <i class="bi bi-fingerprint me-1"></i>Enroll Biometric
                </a>
                <a href="<?= htmlspecialchars(app_url('admin_portal.php?action=edit&id=' . $s['id'])) ?>" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil me-1"></i>Edit
                </a>
                <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?= $s['id'] ?>">
                  <i class="bi bi-trash me-1"></i>Delete
                </button>

                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteModal<?= $s['id'] ?>" tabindex="-1">
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h6 class="modal-title">Delete Student</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>
                      <div class="modal-body">
                        <p>Are you sure you want to delete <strong><?= htmlspecialchars($s['full_name']) ?></strong>?</p>
                        <p class="small text-danger">This will also delete all associated biometric data and attendance records.</p>
                      </div>
                      <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <form method="post" action="<?= htmlspecialchars(app_url('admin_portal.php?action=delete&id=' . $s['id'])) ?>" style="display:inline;">
                          <button type="submit" class="btn btn-danger">Delete</button>
                        </form>
                      </div>
                    </div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
