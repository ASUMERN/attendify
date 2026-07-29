<?php
require __DIR__ . '/includes/config.php';

// Self-registration is disabled. Only admins can register students.
header('Location: ' . app_url('login.php'));
exit;

$pageTitle = 'Student Sign Up';
$db = getDB();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $regNo = trim($_POST['reg_no'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $course = trim($_POST['course'] ?? '');
    $username = $regNo;
    $password = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

  if ($regNo === '' || $fullName === '' || $course === '' || $password === '' || $confirmPassword === '') {
        $error = 'Please complete every field to create your student account.';
  } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } else {
        try {
            $stmt = $db->prepare("INSERT INTO users (role, reg_no, full_name, username, password, course, biometric_registered)
                                  VALUES ('student', ?, ?, ?, ?, ?, 0)");
            $stmt->execute([
                $regNo,
                $fullName,
        $username,
        password_hash($password, PASSWORD_DEFAULT),
                $course,
            ]);

              $studentId = (int)$db->lastInsertId();
              $student = $db->prepare('SELECT id, role, reg_no, full_name, username, course, biometric_registered FROM users WHERE id = ?');
              $student->execute([$studentId]);
              $student = $student->fetch(PDO::FETCH_ASSOC);

              if ($student) {
                $_SESSION['user'] = $student;
                $_SESSION['flash_message'] = 'Your account has been created. Complete biometric enrollment now.';
                $_SESSION['flash_type'] = 'success';
                header('Location: ' . app_url('biometric_enrollment.php'));
                exit;
              }

              $success = 'Your student account has been created. You can now sign in and complete biometric enrollment.';
        } catch (PDOException $exception) {
            $error = 'Could not create your account: ' . $exception->getMessage();
        }
    }
}

require __DIR__ . '/includes/header.php';
?>
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-7 col-xl-6">
      <div class="card login-card p-4">
        <div class="text-center mb-3">
          <i class="bi bi-person-badge" style="font-size:2.5rem;color:var(--accent);"></i>
          <h4 class="mt-2 mb-0">Student Sign Up</h4>
          <small class="text-muted">Create your student account, then log in to enroll biometrics.</small>
        </div>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
          <div class="alert alert-success py-2"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <div class="alert alert-info small mb-4">
          Your student ID is used as your login username, and your biometric status starts as pending until you enroll.
        </div>

        <form method="post">
          <div class="mb-3">
            <label class="form-label">Student ID / Reg No</label>
            <input type="text" name="reg_no" class="form-control" required placeholder="S005">
          </div>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" name="full_name" class="form-control" required placeholder="Your full name">
          </div>
          <div class="mb-3">
            <label class="form-label">Course</label>
            <input type="text" name="course" class="form-control" required placeholder="BIT 2201">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" required placeholder="Create a password">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" required placeholder="Confirm your password">
          </div>
          <div class="alert alert-info small mb-4">
            Your biometric status will be set to <strong>pending</strong> until you enroll fingerprint/Face ID or camera face recognition after signing in.
          </div>
          <button class="btn btn-primary w-100" type="submit">Create Student Account</button>
        </form>

        <div class="text-center mt-3 small">
          Already registered? <a href="<?= htmlspecialchars(app_url('login.php')) ?>">Sign in here</a>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>