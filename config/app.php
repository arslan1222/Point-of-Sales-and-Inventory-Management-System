<?php
define('APP_NAME', 'POS & Inventory System');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/pos_system');
define('LOW_STOCK_THRESHOLD', 10);
define('DEFAULT_TAX_PERCENT', 0);
define('CURRENCY_SYMBOL', '$');
define('DATE_FORMAT', 'Y-m-d');
define('DATETIME_FORMAT', 'Y-m-d H:i:s');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        redirect(APP_URL . '/modules/auth/login.php');
    }
}

// Check role
function hasRole(...$roles) {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], $roles);
}

// Require specific role
function requireRole(...$roles) {
    requireLogin();
    if (!hasRole(...$roles)) {
        redirect(APP_URL . '/modules/auth/unauthorized.php');
    }
}

// Flash message
function setFlash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Generate Invoice Number
function generateInvoiceNo() {
    return 'INV-' . strtoupper(date('Ymd')) . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

// Sanitize input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

// Format currency
function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format($amount, 2);
}
?>