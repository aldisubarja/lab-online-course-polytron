<?php
require_once '../../config/env.php';

startSession();

//Vulnerable: no csrf

// 3. Destroy session completely
$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

startSession();
session_regenerate_id(true);

// 4. Safe redirect
$redirect = $_GET['redirect'] ?? BASE_URL . '/';
if (!preg_match('#^/#', $redirect)) {
    $redirect = BASE_URL . '/';
}

header("Location: $redirect");
exit;