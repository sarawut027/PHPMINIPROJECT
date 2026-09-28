<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../classes/User.php';

function getCurrentUser(): ?array {
    if (isset($_SESSION['user_id'])) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'full_name' => $_SESSION['full_name'],
            'role' => $_SESSION['role']
        ];
    }
    return null;
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']);
}

function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['role'] === 'admin';
}

function isStaff(): bool {
    return isLoggedIn() && ($_SESSION['role'] === 'staff' || $_SESSION['role'] === 'admin');
}

function requireLogin(string $redirect = '../login.php') {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = 'กรุณาเข้าสู่ระบบก่อนเข้าใช้งาน';
        header("Location: $redirect");
        exit();
    }
}

function requireAdmin(string $redirect = '../login.php') {
    requireLogin($redirect);
    if (!isAdmin()) {
        $_SESSION['flash_error'] = 'คุณไม่มีสิทธิ์เข้าถึงหน้านี้ (เฉพาะผู้ดูแลระบบ Admin เท่านั้น)';
        header("Location: $redirect");
        exit();
    }
}
