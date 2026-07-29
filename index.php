<?php
require __DIR__ . '/includes/config.php';
$u = currentUser();
if ($u) {
    header('Location: ' . app_url($u['role'] === 'student' ? 'scan.php' : 'lecturer_portal.php'));
    exit;
}
header('Location: ' . app_url('login.php'));
exit;
