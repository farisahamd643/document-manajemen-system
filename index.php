<?php
// index.php
require_once 'includes/auth.php';
requireLogin();

$page = $_GET['page'] ?? 'dashboard';
$allowed_pages = [
    'dashboard', 'upload', 'files', 'file-types', 'folders', 'categories',
    'users', 'settings', 'profile', 'statistics', 'graphics',
    'history-upload', 'history-download', 'backup'
];

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}

// Get user info
$user = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo getSetting('site_name') ?: 'DocManager'; ?> - Dashboard</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * { font-family: 'Poppins', sans-serif; }
        :root {
            --primary: #4e73df;
            --primary-dark: #224abe;
            --sidebar-width: 260px;
        }
        
        body {
            background: #f8f9fc;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: linear-gradient(180deg, #4e73df 0%, #224abe 100%);
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        
        .sidebar-brand {
            padding: 25px 20px;
            text-align: center;
            color: white;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-brand .logo-icon {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 10px;
        }
        
        .sidebar-brand h5 {
            font-weight: 700;
            margin: 0;
        }
        
        .sidebar-brand small {
            opacity: 0.7;
        }
        
        .sidebar-nav {
            padding: 15px 0;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 25px;
            margin: 3px 10px;
            border-radius: 10px;
            transition: all 0.3s;
            font-weight: 500;
            display: flex;
            align-items: center;
        }
        
        .sidebar-nav .nav-link:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar-nav .nav-link.active {
            background: white;
            color: #4e73df;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        .sidebar-nav .nav-link i {
            width: 25px;
            text-align: center;
            margin-right: 12px;
        }
        
        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 20px;
            min-height: 100vh;
        }
        
        .navbar-top {
            background: white;
            border-radius: 15px;
            padding: 15px 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }
        ::-webkit-scrollbar-thumb {
            background: #4e73df;
            border-radius: 10px;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="logo-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <h5>DocManager</h5>
            <small>v2.0.0</small>
        </div>
        <div class="sidebar-nav">
            <a href="index.php?page=dashboard" class="nav-link <?php echo $page == 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-th-large"></i> Dashboard
            </a>
            <a href="index.php?page=upload" class="nav-link <?php echo $page == 'upload' ? 'active' : ''; ?>">
                <i class="fas fa-upload"></i> Upload File
            </a>
            <a href="index.php?page=files" class="nav-link <?php echo $page == 'files' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i> Semua File
            </a>
            <a href="index.php?page=file-types" class="nav-link <?php echo $page == 'file-types' ? 'active' : ''; ?>">
                <i class="fas fa-tag"></i> Jenis File
            </a>
            <a href="index.php?page=folders" class="nav-link <?php echo $page == 'folders' ? 'active' : ''; ?>">
                <i class="fas fa-folder"></i> Folder
            </a>
            <a href="index.php?page=categories" class="nav-link <?php echo $page == 'categories' ? 'active' : ''; ?>">
                <i class="fas fa-list"></i> Kategori
            </a>
            <a href="index.php?page=statistics" class="nav-link <?php echo $page == 'statistics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i> Statistik
            </a>
            <a href="index.php?page=graphics" class="nav-link <?php echo $page == 'graphics' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i> Grafik
            </a>
            <a href="index.php?page=history-upload" class="nav-link <?php echo $page == 'history-upload' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Riwayat Upload
            </a>
            <a href="index.php?page=history-download" class="nav-link <?php echo $page == 'history-download' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i> Riwayat Download
            </a>
            <?php if (isAdmin()): ?>
            <a href="index.php?page=users" class="nav-link <?php echo $page == 'users' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> User Management
            </a>
            <?php endif; ?>
            <a href="index.php?page=backup" class="nav-link <?php echo $page == 'backup' ? 'active' : ''; ?>">
                <i class="fas fa-database"></i> Backup Database
            </a>
            <a href="index.php?page=settings" class="nav-link <?php echo $page == 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i> Pengaturan
            </a>
            <a href="index.php?page=profile" class="nav-link <?php echo $page == 'profile' ? 'active' : ''; ?>">
                <i class="fas fa-user-circle"></i> Profil
            </a>
            <a href="logout.php" class="nav-link text-danger">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navbar -->
        <div class="navbar-top">
            <div>
                <button class="btn btn-link d-md-none" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="fw-bold" id="pageTitle">
                    <?php 
                        $titles = [
                            'dashboard' => 'Dashboard',
                            'upload' => 'Upload File',
                            'files' => 'Semua File',
                            'file-types' => 'Jenis File',
                            'folders' => 'Folder',
                            'categories' => 'Kategori',
                            'statistics' => 'Statistik',
                            'graphics' => 'Grafik',
                            'history-upload' => 'Riwayat Upload',
                            'history-download' => 'Riwayat Download',
                            'users' => 'User Management',
                            'backup' => 'Backup Database',
                            'settings' => 'Pengaturan',
                            'profile' => 'Profil Admin'
                        ];
                        echo $titles[$page] ?? 'Dashboard';
                    ?>
                </span>
            </div>
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-link" onclick="toggleDarkMode()">
                    <i class="fas fa-moon" id="darkModeIcon"></i>
                </button>
                <button class="btn btn-link" onclick="refreshDashboard()">
                    <i class="fas fa-sync-alt"></i>
                </button>
                <div class="dropdown">
                    <a href="#" class="text-decoration-none dropdown-toggle" data-bs-toggle="dropdown">
                        <img src="<?php echo $user['photo'] ?: 'https://ui-avatars.com/api/?name=' . urlencode($user['full_name'] ?? $user['username']); ?>" 
                             class="rounded-circle" width="40" height="40">
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="index.php?page=profile">
                            <i class="fas fa-user me-2"></i> Profil
                        </a></li>
                        <li><a class="dropdown-item" href="index.php?page=settings">
                            <i class="fas fa-cog me-2"></i> Pengaturan
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> Logout
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Page Content -->
        <?php include "pages/{$page}.php"; ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        function toggleDarkMode() {
            document.body.classList.toggle('dark-mode');
            const icon = document.getElementById('darkModeIcon');
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'fas fa-sun';
                // Save preference via AJAX
                $.post('api/settings.php', {
                    action: 'update',
                    key: 'dark_mode',
                    value: '1'
                });
            } else {
                icon.className = 'fas fa-moon';
                $.post('api/settings.php', {
                    action: 'update',
                    key: 'dark_mode',
                    value: '0'
                });
            }
        }

        function refreshDashboard() {
            if (window.location.search.includes('page=dashboard')) {
                location.reload();
            }
        }

        // Load dark mode preference
        <?php if (getSetting('dark_mode') == '1'): ?>
        document.body.classList.add('dark-mode');
        document.getElementById('darkModeIcon').className = 'fas fa-sun';
        <?php endif; ?>

        // Toast notification
        function showToast(type, title, message) {
            const colors = {
                success: '#1cc88a',
                error: '#e74a3b',
                info: '#36b9cc',
                warning: '#f6c23e'
            };
            
            const toast = $(`
                <div class="toast show" style="position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;">
                    <div class="toast-header" style="border-left: 4px solid ${colors[type] || '#4e73df'};">
                        <strong class="me-auto" style="color: ${colors[type] || '#4e73df'};">${title}</strong>
                        <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
                    </div>
                    <div class="toast-body">${message}</div>
                </div>
            `);
            
            $('body').append(toast);
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        // SweetAlert2 confirmation
        function confirmAction(title, text, callback) {
            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#e74a3b',
                confirmButtonText: 'Ya, lanjutkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed && callback) {
                    callback();
                }
            });
        }

        // CSRF token
        const csrfToken = '<?php echo generateCSRFToken(); ?>';
    </script>
</body>
</html>