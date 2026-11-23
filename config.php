<?php
// ============================================================
// Laundry Management System - Configuration
// Change the four constants below if your MySQL setup differs.
// Default is XAMPP: host localhost, user root, no password.
// ============================================================

define('DB_HOST', 'localhost');
define('DB_NAME', 'Laundry_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Start the session on every page that includes this file.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Connect to the database with PDO. All queries in this app use
// prepared statements, so user input is never put straight into SQL.
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die('Database connection failed. Check that MySQL is running and that '
        . 'Laundry_db has been imported. (' . htmlspecialchars($e->getMessage()) . ')');
}

// ------------------------------------------------------------
// Small helpers used by every page.
// ------------------------------------------------------------

// Print text safely (prevents cross-site scripting).
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

// Redirect to another page and stop.
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// One-time message shown on the next page (success or error).
function flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ------------------------------------------------------------
// Who is logged in? The session holds:
//   user_id   - Customer_id or Employee_id
//   user_type - 'customer' or 'employee'
//   user_role - 'customer', 'Staff' or 'Admin'
//   user_name - display name
// ------------------------------------------------------------
function current_user() {
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id'    => $_SESSION['user_id'],
        'type'  => $_SESSION['user_type'],
        'role'  => $_SESSION['user_role'],
        'name'  => $_SESSION['user_name'],
        'email' => $_SESSION['user_email'] ?? null,
    ];
}

function is_logged_in() {
    return current_user() !== null;
}

// Are we inside the admin/ folder? Used to build correct links.
function in_admin_folder() {
    $path = $_SERVER['SCRIPT_NAME'] ?? '';
    return strpos($path, '/admin/') !== false;
}

// URL to a page at the project root, from wherever we are.
function root_url($page) {
    return (in_admin_folder() ? '../' : '') . $page;
}

// URL to a page inside admin/, from wherever we are.
function admin_url($page) {
    return (in_admin_folder() ? '' : 'admin/') . $page;
}

// Send the visitor to the login page unless they are logged in.
function require_login() {
    if (!is_logged_in()) {
        flash('error', 'Please log in first.');
        redirect(root_url('login.php'));
    }
}

// Only a logged-in customer may open the page.
function require_customer() {
    require_login();
    if ($_SESSION['user_type'] !== 'customer') {
        flash('error', 'This page is for customers only.');
        redirect(root_url('index.php'));
    }
}

// Only a logged-in Admin may open the page.
function require_admin() {
    require_login();
    if ($_SESSION['user_role'] !== 'Admin') {
        flash('error', 'This page is for administrators only.');
        redirect(admin_url('index.php'));
    }
}

// Staff or Admin may open the page (day-to-day work: orders,
// payments, deliveries, reports). Managing branches, employees
// and services is Admin only and uses require_admin().
function require_staff_or_admin() {
    require_login();
    if ($_SESSION['user_type'] !== 'employee') {
        flash('error', 'This page is for staff only.');
        redirect(root_url('index.php'));
    }
}

// The valid order statuses, in the order they move forward.
function order_statuses() {
    return ['Pending', 'Picked Up', 'Washing', 'Ready', 'Collected'];
}

// The next status after the given one, or null if it is the last.
function next_order_status($current) {
    $list = order_statuses();
    $i = array_search($current, $list, true);
    if ($i === false || $i >= count($list) - 1) {
        return null;
    }
    return $list[$i + 1];
}

// Short label class for a status badge.
function status_class($status) {
    switch ($status) {
        case 'Pending':    return 'badge-pending';
        case 'Picked Up':  return 'badge-picked';
        case 'Washing':    return 'badge-washing';
        case 'Ready':      return 'badge-ready';
        case 'Collected':  return 'badge-collected';
        case 'Cancelled':  return 'badge-cancelled';
        case 'Paid':       return 'badge-paid';
        case 'Unpaid':     return 'badge-unpaid';
        case 'Failed':     return 'badge-cancelled';
        default:           return 'badge-plain';
    }
}
