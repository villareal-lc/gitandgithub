<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function home_for($role) {
    return $role === 'admin'
        ? '/shuttlecore/admin/dashboard.php'
        : '/shuttlecore/cashier/pos.php';
}

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: /shuttlecore/login.php');
        exit;
    }
}

function require_role($role) {
    require_login();
    if ($_SESSION['role'] !== $role) {
        header('Location: ' . home_for($_SESSION['role']));
        exit;
    }
}