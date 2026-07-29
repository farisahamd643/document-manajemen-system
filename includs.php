<?php
// includes/functions.php

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

function generateSafeFilename($original) {
    $ext = getFileExtension($original);
    $name = pathinfo($original, PATHINFO_FILENAME);
    $name = preg_replace('/[^a-zA-Z0-9]/', '_', $name);
    $name = substr($name, 0, 50);
    return $name . '_' . time() . '.' . $ext;
}

function isAllowedFileType($filename, $allowed_types = null) {
    global $db;
    
    if (!$allowed_types) {
        $stmt = $db->query("SELECT extension FROM file_types WHERE is_active = 1");
        $allowed_types = [];
        while ($row = $stmt->fetch()) {
            $exts = array_map('trim', explode(',', $row['extension']));
            $allowed_types = array_merge($allowed_types, $exts);
        }
    }
    
    $ext = getFileExtension($filename);
    return in_array($ext, $allowed_types);
}

function createThumbnail($source, $destination, $width = 200, $height = 200) {
    $info = getimagesize($source);
    if (!$info) return false;
    
    list($src_w, $src_h, $type) = $info;
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $src = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $src = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $src = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    $dst = imagecreatetruecolor($width, $height);
    
    // Maintain aspect ratio
    $ratio = min($width / $src_w, $height / $src_h);
    $new_w = $src_w * $ratio;
    $new_h = $src_h * $ratio;
    $x = ($width - $new_w) / 2;
    $y = ($height - $new_h) / 2;
    
    imagecopyresampled($dst, $src, $x, $y, 0, 0, $new_w, $new_h, $src_w, $src_h);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            imagejpeg($dst, $destination, 85);
            break;
        case IMAGETYPE_PNG:
            imagepng($dst, $destination, 9);
            break;
        case IMAGETYPE_GIF:
            imagegif($dst, $destination);
            break;
    }
    
    imagedestroy($src);
    imagedestroy($dst);
    return true;
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'];
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}

function getSetting($key) {
    global $db;
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings[$key] ?? null;
}

function updateSetting($key, $value) {
    global $db;
    $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) 
                          ON DUPLICATE KEY UPDATE setting_value = ?");
    return $stmt->execute([$key, $value, $value]);
}

function sendNotification($user_id, $title, $message, $type = 'info') {
    global $db;
    $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type]);
}

function getNotifications($user_id, $limit = 10) {
    global $db;
    $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$user_id, $limit]);
    return $stmt->fetchAll();
}

function markNotificationRead($notification_id) {
    global $db;
    $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    return $stmt->execute([$notification_id]);
}

function getUnreadCount($user_id) {
    global $db;
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetch()['count'];
}

function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function validatePassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 && 
           preg_match('/[A-Z]/', $password) && 
           preg_match('/[a-z]/', $password) && 
           preg_match('/[0-9]/', $password);
}

function generateToken() {
    return bin2hex(random_bytes(32));
}

function encryptData($data, $key) {
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $key, 0, $iv);
    return base64_encode($iv . $encrypted);
}

function decryptData($data, $key) {
    $data = base64_decode($data);
    $iv_length = openssl_cipher_iv_length('aes-256-cbc');
    $iv = substr($data, 0, $iv_length);
    $encrypted = substr($data, $iv_length);
    return openssl_decrypt($encrypted, 'aes-256-cbc', $key, 0, $iv);
}