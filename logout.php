<?php
require __DIR__ . '/includes/config.php';
session_destroy();
header('Location: ' . app_url('login.php'));
exit;
