<?php
require __DIR__ . '/includes/config.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = getDB()->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        unset($user['password']);
        $_SESSION['user'] = $user;
        if ($user['role'] === 'student') {
      header('Location: ' . app_url(!empty($user['biometric_registered']) ? 'scan.php' : 'biometric_enrollment.php'));
        } elseif ($user['role'] === 'admin') {
            header('Location: ' . app_url('admin_portal.php'));
        } else {
        header('Location: ' . app_url('lecturer_portal.php'));
        }
        exit;
    }
    $error = 'Invalid username or password.';
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="container">
  <div class="card login-card p-4">
    <div class="text-center mb-3">
      <i class="bi bi-fingerprint" style="font-size:2.5rem;color:var(--accent);"></i>
      <h4 class="mt-2 mb-0">Student Attendance System</h4>
      <small class="text-muted">Fingerprint / Face / Eye verified entry</small>
    </div>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <form method="post">
      <div class="mb-3">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" required autofocus>
      </div>
      <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button class="btn btn-primary w-100" type="submit">Sign In</button>
    </form>
    <hr>
    <div class="small text-muted">
      <strong>Demo accounts</strong><br>
      Student: <code>student1</code> / <code>pass123</code><br>
      Lecturer: <code>lecturer</code> / <code>lecturer123</code><br>
      Admin: <code>admin</code> / <code>admin123</code>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
