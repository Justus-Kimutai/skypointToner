<?php
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', '1');
    if (!empty($_SERVER['HTTPS'])) {
        ini_set('session.cookie_secure', '1');
    }
    session_start();
}
require_once __DIR__ . '/functions.php';

function require_login() {
    if (empty($_SESSION['admin_logged_in'])) {
        header('Location: login.php');
        exit;
    }
}

function require_setup_not_done() {
    if (load_credentials() !== null) {
        header('Location: login.php');
        exit;
    }
}

function login_throttled() {
    $attempts = $_SESSION['login_attempts'] ?? 0;
    $last = $_SESSION['login_last_attempt'] ?? 0;
    return $attempts >= 5 && (time() - $last) < 30;
}

function register_failed_login() {
    $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
    $_SESSION['login_last_attempt'] = time();
}

function attempt_login($username, $password) {
    $creds = load_credentials();
    if (!$creds || empty($creds['username']) || empty($creds['password_hash'])) return false;
    if (!hash_equals($creds['username'], $username)) return false;
    if (!password_verify($password, $creds['password_hash'])) return false;

    session_regenerate_id(true);
    $_SESSION['admin_logged_in'] = true;
    $_SESSION['admin_username'] = $username;
    unset($_SESSION['login_attempts'], $_SESSION['login_last_attempt']);
    return true;
}
