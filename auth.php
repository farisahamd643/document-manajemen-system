<?php
// includes/auth.php
session_start();

require_once 'functions.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['username']) && isset($_SESSION['role']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role && $_SESSION['role'] !== 'super_admin') {
        http_response_code(403);
        die('Access denied. Required role: ' . $role);
    }
}

function hasRole($role) {
    if (!isLoggedIn()) return false;
    return $_SESSION['role'] === $role || $_SESSION['role'] === 'super_admin';
}

function isSuperAdmin() {
    return isLoggedIn() && $_SESSION['role'] === 'super_admin';
}

function isAdmin() {
    return isLoggedIn() && ($_SESSION['role'] === 'super_admin' || $_SESSION['role'] === 'admin');
}

function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function logActivity($user_id, $action, $description) {
    global $db;
    $ip = getClientIP();
    $user_agent = getUserAgent();
    
    try {
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) 
                              VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
    } catch (PDOException $e) {
        error_log("Activity log error: " . $e->getMessage());
        return false;
    }
}

function loginUser($user_id, $username, $role, $remember = false) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['role'] = $role;
    $_SESSION['login_time'] = time();
    
    // Update last login
    global $db;
    $stmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user_id]);
    
    // Log activity
    logActivity($user_id, 'login', 'User logged in successfully');
    
    // Handle remember me
    if ($remember) {
        $token = generateToken();
        $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
        
        $stmt = $db->prepare("INSERT INTO user_sessions (user_id, session_token, expires_at) VALUES (?, ?, ?)");
        $stmt->execute([$user_id, $token, $expires]);
        
        setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
    }
}

function logoutUser() {
    if (isLoggedIn()) {
        logActivity($_SESSION['user_id'], 'logout', 'User logged out');
    }
    
    // Clear remember me cookie
    if (isset($_COOKIE['remember_token'])) {
        global $db;
        $stmt = $db->prepare("DELETE FROM user_sessions WHERE session_token = ?");
        $stmt->execute([$_COOKIE['remember_token']]);
        setcookie('remember_token', '', time() - 3600, '/');
    }
    
    session_destroy();
    session_start();
}

function checkRememberMe() {
    if (isset($_COOKIE['remember_token']) && !isLoggedIn()) {
        global $db;
        $stmt = $db->prepare("
            SELECT us.*, u.username, u.role_id, r.name as role_name 
            FROM user_sessions us
            JOIN users u ON u.id = us.user_id
            JOIN roles r ON r.id = u.role_id
            WHERE us.session_token = ? AND us.expires_at > NOW() AND u.is_active = 1
        ");
        $stmt->execute([$_COOKIE['remember_token']]);
        $session = $stmt->fetch();
        
        if ($session) {
            loginUser($session['user_id'], $session['username'], $session['role_name']);
            return true;
        }
    }
    return false;
}

function getCurrentUser() {
    if (!isLoggedIn()) return null;
    
    global $db;
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user) {
        unset($user['password']); // Remove password for security
    }
    return $user;
}

function checkPermission($action, $resource_id = null) {
    if (isSuperAdmin()) return true;
    if (isAdmin()) {
        // Admin has most permissions except user management
        if ($action === 'manage_users' || $action === 'manage_roles') {
            return false;
        }
        return true;
    }
    
    // For regular users, check specific permissions
    global $db;
    $user_id = $_SESSION['user_id'];
    
    switch ($action) {
        case 'upload_file':
            return true;
        case 'download_file':
            return true;
        case 'edit_file':
            if ($resource_id) {
                $stmt = $db->prepare("SELECT user_id FROM files WHERE id = ?");
                $stmt->execute([$resource_id]);
                $file = $stmt->fetch();
                return $file && $file['user_id'] == $user_id;
            }
            return false;
        case 'delete_file':
            if ($resource_id) {
                $stmt = $db->prepare("SELECT user_id FROM files WHERE id = ?");
                $stmt->execute([$resource_id]);
                $file = $stmt->fetch();
                return $file && $file['user_id'] == $user_id;
            }
            return false;
        default:
            return false;
    }
}