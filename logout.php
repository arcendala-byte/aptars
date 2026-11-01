<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    logActivity((int)$_SESSION['user_id'], 'logout', 'User logged out');
}
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();
session_start();
setFlash('success', 'You have been logged out.');
redirect(baseUrl('login.php'));