<?php
require __DIR__ . '/includes/config.php';
requireRole('admin');
$u = currentUser();

$pageTitle = 'Register Student';
$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $regNo = trim($_POST['reg_no'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $password = (string)($_POST['password'] ?? '');
    $biometricRegistered = !empty($_POST['biometric_registered']) ? 1 : 0;

    if ($regNo === '' || $fullName === '' || $course === '' || $username === '' || $password === '') {
        $error = 'Please complete every required field.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO users (role, reg_no, full_name, username, password, course, biometric_registered)
                                  VALUES ('student', ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $regNo,
                $fullName,
                $username,
                password_hash($password, PASSWORD_DEFAULT),
                $course,
                $biometricRegistered,
            ]);

            $success = 'Student registered successfully.';
        } catch (PDOException $exception) {
            $error = 'Could not register student: ' . $exception->getMessage();
        }
    }
}

$students = $db->query("SELECT id, reg_no, full_name, username, course, biometric_registered, created_at
                       FROM users
                       WHERE role = 'student'
                       ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/includes/header.php';
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
    <div>
      <h4 class="mb-1"><i class="bi bi-person-plus me-2"></i>Register Student</h4>
      <p class="text-muted mb-0">Add a student profile, login account, course, and biometric registration status.</p>
    </div>
    <a href="<?= htmlspecialchars(app_url('admin_portal.php')) ?>" class="btn btn-outline-secondary">Back to Admin Portal</a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
  <?php endif; ?>

  <div class="row g-4">
    <div class="col-lg-5">
      <div class="card p-4">
        <h6 class="mb-3">Student Registration Form</h6>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Student ID / Reg No</label>
            <input type="text" name="reg_no" class="form-control" required placeholder="S005">
          </div>
          <div class="mb-3">
            <label class="form-label">Student Name</label>
            <input type="text" name="full_name" class="form-control" required placeholder="Jane Doe">
          </div>
          <div class="mb-3">
            <label class="form-label">Course</label>
            <input type="text" name="course" class="form-control" required placeholder="BIT 2201">
          </div>
          <div class="mb-3">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" required placeholder="student5">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required placeholder="Create a login password">
          </div>
          <div class="form-check mb-4">
            <input class="form-check-input" type="checkbox" value="1" id="biometric_registered" name="biometric_registered" checked>
            <label class="form-check-label" for="biometric_registered">
              Mark biometric registration as completed
            </label>
            <div class="form-text">Use this when the student's biometric data is already on file.</div>
          </div>
          <button class="btn btn-primary w-100" type="submit">Register Student</button>
        </form>
      </div>
    </div>

    <div class="col-lg-7">
      <div class="card p-3">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="mb-0">Registered Students</h6>
          <span class="badge text-bg-light text-dark"><?= count($students) ?> total</span>
        </div>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr>
                <th>Reg No</th>
                <th>Name</th>
                <th>Course</th>
                <th>Biometric</th>
                <th>Username</th>
                <th>Created</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$students): ?>
                <tr><td colspan="6" class="text-center text-muted py-4">No students registered yet.</td></tr>
              <?php endif; ?>
              <?php foreach ($students as $student): ?>
                <tr>
                  <td><?= htmlspecialchars($student['reg_no']) ?></td>
                  <td><?= htmlspecialchars($student['full_name']) ?></td>
                  <td><?= htmlspecialchars($student['course']) ?></td>
                  <td>
                    <span class="status-pill <?= $student['biometric_registered'] ? 'status-verified' : 'status-unverified' ?>">
                      <?= $student['biometric_registered'] ? 'Registered' : 'Pending' ?>
                    </span>
                  </td>
                  <td><?= htmlspecialchars($student['username']) ?></td>
                  <td class="small text-muted"><?= htmlspecialchars($student['created_at']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>