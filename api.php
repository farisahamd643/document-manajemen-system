<?php
// api/dashboard.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// Total files
$stmt = $db->query("SELECT COUNT(*) as total FROM files WHERE status = 'active'");
$stats['total_files'] = $stmt->fetch()['total'];

// Uploads today
$stmt = $db->query("SELECT COUNT(*) as total FROM files WHERE DATE(uploaded_at) = CURDATE()");
$stats['uploads_today'] = $stmt->fetch()['total'];

// Total downloads
$stmt = $db->query("SELECT SUM(download_count) as total FROM files");
$stats['total_downloads'] = $stmt->fetch()['total'] ?? 0;

// Total users
$stmt = $db->query("SELECT COUNT(*) as total FROM users WHERE is_active = 1");
$stats['total_users'] = $stmt->fetch()['total'];

// Total file types
$stmt = $db->query("SELECT COUNT(*) as total FROM file_types WHERE is_active = 1");
$stats['total_file_types'] = $stmt->fetch()['total'];

// Total folders
$stmt = $db->query("SELECT COUNT(*) as total FROM folders");
$stats['total_folders'] = $stmt->fetch()['total'];

// Storage used
$stmt = $db->query("SELECT SUM(file_size) as total FROM files WHERE status = 'active'");
$storage_used = $stmt->fetch()['total'] ?? 0;
$max_storage = 50 * 1024 * 1024 * 1024; // 50 GB in bytes
$stats['storage_used'] = formatBytes($storage_used);
$stats['storage_remaining'] = formatBytes(max(0, $max_storage - $storage_used));
$stats['storage_percentage'] = round(($storage_used / $max_storage) * 100, 2);

// Monthly uploads
$stmt = $db->query("
    SELECT MONTH(uploaded_at) as month, COUNT(*) as total 
    FROM files 
    WHERE YEAR(uploaded_at) = YEAR(CURDATE())
    GROUP BY MONTH(uploaded_at)
");
$monthly_uploads = array_fill(1, 12, 0);
while ($row = $stmt->fetch()) {
    $monthly_uploads[$row['month']] = $row['total'];
}
$stats['monthly_uploads'] = array_values($monthly_uploads);

// Monthly downloads
$stmt = $db->query("
    SELECT MONTH(downloaded_at) as month, COUNT(*) as total 
    FROM downloads 
    WHERE YEAR(downloaded_at) = YEAR(CURDATE())
    GROUP BY MONTH(downloaded_at)
");
$monthly_downloads = array_fill(1, 12, 0);
while ($row = $stmt->fetch()) {
    $monthly_downloads[$row['month']] = $row['total'];
}
$stats['monthly_downloads'] = array_values($monthly_downloads);

// File types distribution
$stmt = $db->query("
    SELECT ft.name, COUNT(f.id) as total 
    FROM file_types ft
    LEFT JOIN files f ON f.file_type_id = ft.id AND f.status = 'active'
    GROUP BY ft.id
");
$file_types = [];
while ($row = $stmt->fetch()) {
    $file_types[$row['name']] = $row['total'];
}
$stats['file_types'] = $file_types;

// User activity
$stmt = $db->query("
    SELECT u.username, 
           COUNT(DISTINCT f.id) as uploads,
           COUNT(DISTINCT d.id) as downloads
    FROM users u
    LEFT JOIN files f ON f.user_id = u.id
    LEFT JOIN downloads d ON d.user_id = u.id
    WHERE u.is_active = 1
    GROUP BY u.id
    LIMIT 10
");
$user_activity = [];
while ($row = $stmt->fetch()) {
    $user_activity[] = $row;
}
$stats['user_activity'] = $user_activity;

// Recent activities
$stmt = $db->query("
    SELECT al.*, u.username 
    FROM activity_logs al
    JOIN users u ON u.id = al.user_id
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stats['recent_activities'] = $stmt->fetchAll();

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

echo json_encode([
    'success' => true,
    'data' => $stats
]);