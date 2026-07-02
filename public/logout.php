<?php
require dirname(__DIR__) . '/app/bootstrap.php';

try {
    audit_log(db(), 'auth.logout', 'user', currentUser()['id'] ?? null);
} catch (Throwable) {
}

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}
session_destroy();
header('Location: ' . url('/login.php'));
