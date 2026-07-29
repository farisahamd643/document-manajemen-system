<?php
// api/files.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';
requireLogin();

$db = (new Database())->getConnection();
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$method = $_SERVER['REQUEST_METHOD'];

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['id'])) {
                getFile($_GET['id']);
            } else {
                getFiles();
            }
            break;
        case 'POST':
            uploadFile();
            break;
        case 'PUT':
            updateFile();
            break;
        case 'DELETE':
            deleteFile();
            break;
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}

function getFiles() {
    global $db, $user_id, $role;
    
    try {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
        $search = isset($_GET['search']) ? $_GET['search'] : '';
        $folder_id = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;
        $file_type_id = isset($_GET['file_type_id']) ? (int)$_GET['file_type_id'] : null;
        
        $where = ["f.status != 'deleted'"];
        $params = [];
        
        // Filter berdasarkan role
        if ($role !== 'super_admin' && $role !== 'admin') {
            $where[] = "f.user_id = ?";
            $params[] = $user_id;
        }
        
        if ($folder_id) {
            $where[] = "f.folder_id = ?";
            $params[] = $folder_id;
        }
        
        if ($file_type_id) {
            $where[] = "f.file_type_id = ?";
            $params[] = $file_type_id;
        }
        
        if (!empty($search)) {
            $where[] = "(f.title LIKE ? OR f.original_name LIKE ? OR f.description LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        
        $whereClause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM files f $whereClause";
        $stmt = $db->prepare($countSql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Get files with joins
        $sql = "
            SELECT 
                f.*, 
                u.username as uploaded_by, 
                u.full_name as uploaded_by_name,
                ft.name as file_type_name,
                ft.icon as file_type_icon,
                c.name as category_name,
                c.color as category_color,
                fo.name as folder_name
            FROM files f
            JOIN users u ON u.id = f.user_id
            LEFT JOIN file_types ft ON ft.id = f.file_type_id
            LEFT JOIN categories c ON c.id = f.category_id
            LEFT JOIN folders fo ON fo.id = f.folder_id
            $whereClause
            ORDER BY f.uploaded_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $params[] = $limit;
        $params[] = $offset;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $files = $stmt->fetchAll();
        
        // Format file size untuk setiap file
        foreach ($files as &$file) {
            $file['file_size_formatted'] = formatBytes($file['file_size']);
            $file['uploaded_at_formatted'] = date('d/m/Y H:i', strtotime($file['uploaded_at']));
            $file['file_url'] = getFileUrl($file['file_path']);
            $file['thumbnail_url'] = getThumbnailUrl($file['file_path'], $file['mime_type']);
        }
        
        echo json_encode([
            'success' => true,
            'data' => $files,
            'total' => (int)$total,
            'limit' => $limit,
            'offset' => $offset,
            'pages' => ceil($total / $limit)
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function getFile($id) {
    global $db, $user_id, $role;
    
    try {
        $id = (int)$id;
        
        $stmt = $db->prepare("
            SELECT 
                f.*, 
                u.username as uploaded_by, 
                u.full_name as uploaded_by_name,
                ft.name as file_type_name,
                ft.icon as file_type_icon,
                c.name as category_name,
                fo.name as folder_name
            FROM files f
            JOIN users u ON u.id = f.user_id
            LEFT JOIN file_types ft ON ft.id = f.file_type_id
            LEFT JOIN categories c ON c.id = f.category_id
            LEFT JOIN folders fo ON fo.id = f.folder_id
            WHERE f.id = ? AND f.status != 'deleted'
        ");
        $stmt->execute([$id]);
        $file = $stmt->fetch();
        
        if (!$file) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File not found']);
            return;
        }
        
        // Check permission
        if ($role !== 'super_admin' && $role !== 'admin' && $file['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Access denied']);
            return;
        }
        
        // Format data
        $file['file_size_formatted'] = formatBytes($file['file_size']);
        $file['uploaded_at_formatted'] = date('d/m/Y H:i', strtotime($file['uploaded_at']));
        $file['file_url'] = getFileUrl($file['file_path']);
        $file['thumbnail_url'] = getThumbnailUrl($file['file_path'], $file['mime_type']);
        
        echo json_encode([
            'success' => true,
            'data' => $file
        ]);
        
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Database error: ' . $e->getMessage()
        ]);
    }
}

function uploadFile() {
    global $db, $user_id;
    
    try {
        // Cek apakah ada file yang diupload
        if (!isset($_FILES['file']) || $_FILES['file']['error'] === UPLOAD_ERR_NO_FILE) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada file yang diupload']);
            return;
        }
        
        $file = $_FILES['file'];
        
        // Cek error upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File terlalu besar (melebihi upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'File terlalu besar (melebihi MAX_FILE_SIZE)',
                UPLOAD_ERR_PARTIAL => 'File hanya terupload sebagian',
                UPLOAD_ERR_NO_FILE => 'Tidak ada file yang diupload',
                UPLOAD_ERR_NO_TMP_DIR => 'Folder tmp tidak ditemukan',
                UPLOAD_ERR_CANT_WRITE => 'Gagal menulis file ke disk',
                UPLOAD_ERR_EXTENSION => 'Upload dihentikan oleh ekstensi PHP'
            ];
            $error_msg = $error_messages[$file['error']] ?? 'Unknown upload error';
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Upload error: ' . $error_msg]);
            return;
        }
        
        // Cek ukuran file
        $maxSize = (int)getSetting('max_upload_size') * 1024 * 1024;
        if (empty($maxSize) || $maxSize < 1) {
            $maxSize = 100 * 1024 * 1024; // Default 100MB
        }
        
        if ($file['size'] > $maxSize) {
            http_response_code(400);
            echo json_encode([
                'success' => false, 
                'message' => 'File terlalu besar. Maksimal ' . formatBytes($maxSize)
            ]);
            return;
        }
        
        // Validasi tipe file
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 
                               'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
                               'mp4', 'avi', 'mkv', 'mov', 'mp3', 'wav', 'ogg',
                               'zip', 'rar', '7z', 'tar', 'gz', 'txt', 'csv'];
        
        if (!in_array($ext, $allowed_extensions)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tipe file tidak diizinkan. Ekstensi yang diizinkan: ' . implode(', ', $allowed_extensions)
            ]);
            return;
        }
        
        // Cek MIME type untuk keamanan tambahan
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowed_mimes = [
            'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
            'video/mp4', 'video/x-msvideo', 'video/x-matroska', 'video/quicktime',
            'audio/mpeg', 'audio/wav', 'audio/ogg',
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
            'text/plain', 'text/csv'
        ];
        
        if (!in_array($mime_type, $allowed_mimes)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Tipe file tidak diizinkan berdasarkan MIME type: ' . $mime_type
            ]);
            return;
        }
        
        // Tentukan subfolder berdasarkan tipe file
        $sub_folder = getSubFolder($ext);
        $upload_dir = '../assets/uploads/' . $sub_folder;
        
        // Buat folder jika belum ada
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0777, true)) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Gagal membuat folder upload']);
                return;
            }
        }
        
        // Buat folder thumbnail jika gambar
        $thumb_dir = $upload_dir . 'thumbnails/';
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            if (!is_dir($thumb_dir)) {
                mkdir($thumb_dir, 0777, true);
            }
        }
        
        // Generate nama file unik
        $new_filename = uniqid() . '_' . time() . '.' . $ext;
        $upload_path = $upload_dir . $new_filename;
        $relative_path = 'assets/uploads/' . $sub_folder . $new_filename;
        
        // Pindahkan file
        if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan file']);
            return;
        }
        
        // Buat thumbnail jika gambar
        $thumbnail_path = null;
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $thumb_file = $thumb_dir . $new_filename;
            if (createThumbnail($upload_path, $thumb_file, 200, 200)) {
                $thumbnail_path = 'assets/uploads/' . $sub_folder . 'thumbnails/' . $new_filename;
            }
        }
        
        // Ambil input dari POST atau JSON
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        // Data tambahan
        $title = isset($input['title']) && !empty($input['title']) ? $input['title'] : $file['name'];
        $description = isset($input['description']) ? $input['description'] : '';
        $folder_id = isset($input['folder_id']) && !empty($input['folder_id']) ? (int)$input['folder_id'] : null;
        $category_id = isset($input['category_id']) && !empty($input['category_id']) ? (int)$input['category_id'] : null;
        
        // Insert ke database
        $stmt = $db->prepare("
            INSERT INTO files (
                user_id, 
                folder_id, 
                category_id, 
                title, 
                description, 
                filename, 
                original_name, 
                file_path, 
                file_size, 
                mime_type,
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $result = $stmt->execute([
            $user_id,
            $folder_id,
            $category_id,
            $title,
            $description,
            $new_filename,
            $file['name'],
            $relative_path,
            $file['size'],
            $mime_type,
            'active'
        ]);
        
        if ($result) {
            $file_id = $db->lastInsertId();
            
            // Log aktivitas upload
            logActivity($user_id, 'upload', "Upload file: $title (" . formatBytes($file['size']) . ")");
            
            // Log ke tabel uploads
            $ip = getClientIP();
            $user_agent = getUserAgent();
            $stmt = $db->prepare("INSERT INTO uploads (file_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)");
            $stmt->execute([$file_id, $user_id, $ip, $user_agent]);
            
            echo json_encode([
                'success' => true,
                'message' => 'File berhasil diupload',
                'file_id' => $file_id,
                'file' => [
                    'id' => $file_id,
                    'title' => $title,
                    'filename' => $new_filename,
                    'original_name' => $file['name'],
                    'file_size' => $file['size'],
                    'file_size_formatted' => formatBytes($file['size']),
                    'file_path' => $relative_path,
                    'file_url' => getFileUrl($relative_path),
                    'thumbnail_url' => $thumbnail_path ? getFileUrl($thumbnail_path) : null
                ]
            ]);
        } else {
            // Hapus file jika gagal insert database
            if (file_exists($upload_path)) {
                unlink($upload_path);
            }
            if ($thumbnail_path && file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
            
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menyimpan informasi file']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
}

function updateFile() {
    global $db, $user_id, $role;
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }
        
        $id = isset($_GET['id']) ? (int)$_GET['id'] : (isset($input['id']) ? (int)$input['id'] : 0);
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID file diperlukan']);
            return;
        }
        
        // Cek file exists dan permission
        $stmt = $db->prepare("SELECT user_id, status FROM files WHERE id = ?");
        $stmt->execute([$id]);
        $file = $stmt->fetch();
        
        if (!$file) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
            return;
        }
        
        if ($file['status'] === 'deleted') {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File sudah dihapus']);
            return;
        }
        
        if ($role !== 'super_admin' && $role !== 'admin' && $file['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            return;
        }
        
        // Build update query
        $updates = [];
        $params = [];
        
        $allowed_fields = ['title', 'description', 'folder_id', 'category_id', 'status'];
        foreach ($allowed_fields as $field) {
            if (isset($input[$field])) {
                $updates[] = "$field = ?";
                $params[] = $input[$field];
            }
        }
        
        if (empty($updates)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Tidak ada field yang diupdate']);
            return;
        }
        
        $params[] = $id;
        $sql = "UPDATE files SET " . implode(", ", $updates) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($sql);
        $result = $stmt->execute($params);
        
        if ($result) {
            logActivity($user_id, 'update', "Update file ID: $id");
            echo json_encode([
                'success' => true,
                'message' => 'File berhasil diupdate',
                'updated_fields' => $updates
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal mengupdate file']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
}

function deleteFile() {
    global $db, $user_id, $role;
    
    try {
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID file diperlukan']);
            return;
        }
        
        // Cek file exists dan permission
        $stmt = $db->prepare("SELECT user_id, file_path FROM files WHERE id = ? AND status != 'deleted'");
        $stmt->execute([$id]);
        $file = $stmt->fetch();
        
        if (!$file) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'File tidak ditemukan']);
            return;
        }
        
        if ($role !== 'super_admin' && $role !== 'admin' && $file['user_id'] != $user_id) {
            http_response_code(403);
            echo json_encode(['success' => false, 'message' => 'Akses ditolak']);
            return;
        }
        
        // Soft delete (update status)
        $stmt = $db->prepare("UPDATE files SET status = 'deleted', updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$id]);
        
        if ($result) {
            logActivity($user_id, 'delete', "Delete file ID: $id");
            
            // Hapus file fisik (opsional - uncomment jika ingin hard delete)
            // if (file_exists($file['file_path'])) {
            //     unlink($file['file_path']);
            // }
            
            echo json_encode([
                'success' => true,
                'message' => 'File berhasil dihapus'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Gagal menghapus file']);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage()
        ]);
    }
}

// ==================== HELPER FUNCTIONS ====================

function getSetting($key) {
    global $db;
    try {
        $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    } catch (Exception $e) {
        return null;
    }
}

function formatBytes($bytes, $precision = 2) {
    if (!$bytes || $bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

function getSubFolder($extension) {
    $extension = strtolower($extension);
    
    if (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'rtf', 'odt'])) {
        return 'documents/';
    } elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'tiff'])) {
        return 'images/';
    } elseif (in_array($extension, ['mp4', 'avi', 'mkv', 'mov', 'wmv', 'flv', 'webm', 'm4v'])) {
        return 'videos/';
    } elseif (in_array($extension, ['mp3', 'wav', 'ogg', 'flac', 'aac', 'm4a', 'wma'])) {
        return 'audios/';
    } elseif (in_array($extension, ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'xz'])) {
        return 'archives/';
    } else {
        return 'others/';
    }
}

function getFileUrl($path) {
    if (empty($path)) return null;
    // Remove '../' from path for web access
    $web_path = str_replace('../', '', $path);
    // Get base URL from server
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    return $protocol . $host . '/' . $web_path;
}

function getThumbnailUrl($path, $mime_type) {
    if (empty($path)) return null;
    
    // Cek apakah file adalah gambar
    if (strpos($mime_type, 'image/') !== 0) {
        return null;
    }
    
    // Cek apakah thumbnail ada
    $thumb_path = str_replace('.', '_thumb.', $path);
    $thumb_web_path = str_replace('../', '', $thumb_path);
    
    // Cek file thumbnail di server
    $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $thumb_web_path;
    if (file_exists($full_path)) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'];
        return $protocol . $host . '/' . $thumb_web_path;
    }
    
    return null;
}

function createThumbnail($source, $destination, $width = 200, $height = 200) {
    try {
        $info = getimagesize($source);
        if (!$info) return false;
        
        list($src_w, $src_h, $type) = $info;
        
        // Create image from source
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
            case IMAGETYPE_WEBP:
                if (function_exists('imagecreatefromwebp')) {
                    $src = imagecreatefromwebp($source);
                } else {
                    return false;
                }
                break;
            default:
                return false;
        }
        
        if (!$src) return false;
        
        // Create thumbnail
        $dst = imagecreatetruecolor($width, $height);
        
        // Maintain aspect ratio
        $ratio = min($width / $src_w, $height / $src_h);
        $new_w = (int)($src_w * $ratio);
        $new_h = (int)($src_h * $ratio);
        $x = (int)(($width - $new_w) / 2);
        $y = (int)(($height - $new_h) / 2);
        
        // White background
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefill($dst, 0, 0, $white);
        
        // Copy and resize
        imagecopyresampled($dst, $src, $x, $y, 0, 0, $new_w, $new_h, $src_w, $src_h);
        
        // Save thumbnail
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
            case IMAGETYPE_WEBP:
                if (function_exists('imagewebp')) {
                    imagewebp($dst, $destination, 85);
                } else {
                    imagejpeg($dst, $destination, 85);
                }
                break;
        }
        
        imagedestroy($src);
        imagedestroy($dst);
        return true;
        
    } catch (Exception $e) {
        error_log('Thumbnail creation error: ' . $e->getMessage());
        return false;
    }
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        $ip = trim($ips[0]);
    }
    
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? '';
}
?>