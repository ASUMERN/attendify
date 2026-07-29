<?php $u = currentUser(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' | ' : '' ?>Biometric Attendance System</title>
    <link href="<?= htmlspecialchars(app_url('assets/bootstrap-5.0.2-dist/css/bootstrap.min.css')) ?>" rel="stylesheet">
<link rel="stylesheet" href="<?= htmlspecialchars(app_url('assets/css/style.css')) ?>">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= htmlspecialchars(app_url('index.php')) ?>"><i class="bi bi-fingerprint me-2"></i>AttendX</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <?php if ($u): ?>
          <?php if ($u['role'] === 'student'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('scan.php')) ?>">Scan &amp; Enter</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('student_portal.php')) ?>">My Attendance</a></li>
          <?php elseif ($u['role'] === 'lecturer'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('lecturer_portal.php')) ?>">Lecturer Portal</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('face_scanner.php')) ?>">Face Scanner</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('register_student.php')) ?>">Register Student</a></li>
          <?php elseif ($u['role'] === 'admin'): ?>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('lecturer_portal.php')) ?>">Admin Portal</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('face_scanner.php')) ?>">Face Scanner</a></li>
            <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('register_student.php')) ?>">Register Student</a></li>
          <?php endif; ?>
          <li class="nav-item ms-lg-3">
            <span class="badge text-bg-light text-dark me-2"><?= htmlspecialchars($u['full_name']) ?> · <?= htmlspecialchars(ucfirst($u['role'])) ?></span>
          </li>
          <li class="nav-item"><a class="btn btn-sm btn-outline-light" href="<?= htmlspecialchars(app_url('logout.php')) ?>">Logout</a></li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="<?= htmlspecialchars(app_url('login.php')) ?>">Login</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
