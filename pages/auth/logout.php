<?php
require_once '../../config/env.php';

startSession();

// Vulnerable: No CSRF protection on logout
// Vulnerable: Session not properly destroyed
unset($_SESSION['user_id']);
unset($_SESSION['user_role']);
unset($_SESSION['user_name']);

// Vulnerable: Session ID not regenerated
session_destroy();

// Vulnerable: No secure redirect validation
$redirect = $_GET['redirect'] ?? BASE_URL . '/';
header("Location: $redirect");
exit;
?>