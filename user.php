<?php
// api/users.php
header('Content-Type: application/json');
require_once '../config/database.php';
require_once '../includes/auth.php';

// Only super_admin and admin can access user management
if (!hasRole('super_admin') && !hasRole('admin')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit();
}

$db = (new Database())->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        if (isset($_GET['id'])) {
            getUser($_GET['id']);
        } else {
            getUsers();
        }
        break;
    case 'POST':
        'createUser'();
        break;
    case 'PUT':
        'updateUser'();
        break;
    case 'DELETE':
        'deleteUser'();
        break;
}

function getUsers() {
    global $db;
    
    $limit = $_GET['limit'] ?? 10;
    $offset = $_GET['offset'] ?? 0;
    $search = $_GET['search'] ?? '';
    
    $where = [];
    $params = [];
    
    if ($search) {
        $where[] = "(u.username LIKE ? OR u.full_name LIKE ? OR u.email LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }
    
    $whereClause = $where ? "WHERE " . implode(" AND ", $where) : "";
    
    // Get total count
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM users u $whereClause");
    $stmt->execute($params);
    $total = $stmt->fetch()['total'];
    
    // Get users
    $sql = "
        SELECT u.*, r.name as role_name, r.description as role_description
        FROM users u
        JOIN roles r ON r.id = u.role_id
        $whereClause
        ORDER BY u.id DESC
        LIMIT ? OFFSET ?
    ";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'data' => $users,
        'total' => $total,
        'limit' => $limit,
        'offset' => $offset
    ]);
}

function getUser($id) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT u.*, r.name as role_name
        FROM users u
        JOIN roles r ON r.id = u.role_id
        WHERE u.id = ?
    ");
    $stmt->execute([$id]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        return;
    }
    
    // Remove password
    unset($user['password']);
    
    echo json_encode(['success' => true, 'data' => $user]);
}

function createUser() {
    global $db, $user_id;
    
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate input
    if (!isset($input['username']) || !isset($input['password']) || !isset($input['email'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        return;
    }
    
    // Check if username exists
    $stmt = $db->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$input['username']]);
    if ($stmt->fetch()) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        return;
    }
    
    // Check if email exists
    $stmt = $db->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$input['email']]);
}