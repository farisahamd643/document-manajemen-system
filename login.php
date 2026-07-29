<?php
// login.php
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

$db = (new Database())->getConnection();

// Check if already logged in
if (isLoggedIn()) {
    header('Location: index.php');
    exit();
}

// Check remember me
checkRememberMe();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        $stmt = $db->prepare("
            SELECT u.*, r.name as role_name 
            FROM users u
            JOIN roles r ON r.id = u.role_id
            WHERE u.username = ? AND u.is_active = 1
        ");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && verifyPassword($password, $user['password'])) {
            loginUser($user['id'], $user['username'], $user['role_name'], $remember);
            header('Location: index.php');
            exit();
        } else {
            $error = 'Username atau password salah';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Document Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(135deg, #4e73df 0%, #224abe 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.6s ease;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(50px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .login-logo .logo-icon {
            width: 70px;
            height: 70px;
            background: #4e73df;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
        }
        .login-logo h3 {
            margin-top: 10px;
            color: #2d3748;
            font-weight: 700;
        }
        .login-logo p {
            color: #858796;
            font-size: 14px;
        }
        .form-control {
            border-radius: 10px;
            padding: 12px 15px;
            border: 2px solid #e2e7f0;
        }
        .form-control:focus {
            border-color: #4e73df;
            box-shadow: 0 0 0 0.2rem rgba(78,115,223,0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #4e73df, #224abe);
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            color: white;
            width: 100%;
            transition: all 0.3s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(78,115,223,0.4);
        }
        .alert {
            border-radius: 10px;
        }
        .password-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="login-logo">
            <div class="logo-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h3>DocManager</h3>
            <p>Document Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-user me-2"></i>Username</label>
                <input type="text" class="form-control" name="username" placeholder="Masukkan username" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label"><i class="fas fa-lock me-2"></i>Password</label>
                <div class="input-group">
                    <input type="password" class="form-control" name="password" id="password" placeholder="Masukkan password" required>
                    <button class="btn btn-outline-secondary password-toggle" type="button" onclick="togglePassword()">
                        <i class="fas fa-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3 d-flex justify-content-between align-items-center">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" name="remember" id="remember">
                    <label class="form-check-label" for="remember">Remember Me</label>
                </div>
                <a href="forgot-password.php" class="text-decoration-none" style="color: #4e73df;">Lupa Password?</a>
            </div>
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i> Login
            </button>
        </form>
        
        <div class="text-center mt-4">
            <small class="text-muted">© <?php echo date('Y'); ?> DocManager. All rights reserved.</small>
        </div>
    </div>

    <script>
        function togglePassword() {
            const password = document.getElementById('password');
            const icon = document.getElementById('eyeIcon');
            if (password.type === 'password') {
                password.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                password.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
    </script>
</body>
</html>