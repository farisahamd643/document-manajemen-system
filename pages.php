<?php
// pages/dashboard.php
$db = (new Database())->getConnection();
?>

<div id="dashboardContent">
    <!-- Stat Cards -->
    <div class="row g-3 mb-4" id="statCards">
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file"></i></div>
                <div class="stat-number" id="totalFiles">0</div>
                <div class="stat-label">Total File</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card" style="border-left-color: #1cc88a;">
                <div class="stat-icon" style="color: #1cc88a;"><i class="fas fa-upload"></i></div>
                <div class="stat-number" id="uploadsToday">0</div>
                <div class="stat-label">Upload Hari Ini</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card" style="border-left-color: #36b9cc;">
                <div class="stat-icon" style="color: #36b9cc;"><i class="fas fa-download"></i></div>
                <div class="stat-number" id="totalDownloads">0</div>
                <div class="stat-label">Total Download</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card" style="border-left-color: #f6c23e;">
                <div class="stat-icon" style="color: #f6c23e;"><i class="fas fa-users"></i></div>
                <div class="stat-number" id="totalUsers">0</div>
                <div class="stat-label">Total Pengguna</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card" style="border-left-color: #e74a3b;">
                <div class="stat-icon" style="color: #e74a3b;"><i class="fas fa-tags"></i></div>
                <div class="stat-number" id="totalFileTypes">0</div>
                <div class="stat-label">Total Jenis File</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card" style="border-left-color: #858796;">
                <div class="stat-icon" style="color: #858796;"><i class="fas fa-folder"></i></div>
                <div class="stat-number" id="totalFolders">0</div>
                <div class="stat-label">Total Folder</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card" style="border-left-color: #4e73df;">
                <div class="stat-icon" style="color: #4e73df;"><i class="fas fa-database"></i></div>
                <div class="stat-number" id="storageUsed">0 GB</div>
                <div class="stat-label">Kapasitas Penyimpanan</div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-4 col-md-6 col-sm-12">
            <div class="stat-card" style="border-left-color: #1cc88a;">
                <div class="stat-icon" style="color: #1cc88a;"><i class="fas fa-hdd"></i></div>
                <div class="stat-number" id="storageRemaining">0 GB</div>
                <div class="stat-label">Sisa Penyimpanan</div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row g-3">
        <div class="col-md-6">
            <div class="chart-card">
                <h6><i class="fas fa-chart-bar me-2"></i> Grafik Upload Bulanan</h6>
                <canvas id="uploadChart"></canvas>
            </div>
        </div>
        <div class="col-md-6">
            <div class="chart-card">
                <h6><i class="fas fa-chart-bar me-2"></i> Grafik Download Bulanan</h6>
                <canvas id="downloadChart"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-card">
                <h6><i class="fas fa-chart-pie me-2"></i> Jenis File</h6>
                <canvas id="fileTypeChart"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-card">
                <h6><i class="fas fa-database me-2"></i> Penggunaan Storage</h6>
                <canvas id="storageChart"></canvas>
            </div>
        </div>
        <div class="col-md-4">
            <div class="chart-card">
                <h6><i class="fas fa-users me-2"></i> Aktivitas User</h6>
                <canvas id="activityChart"></canvas>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="table-container mt-3">
        <h6><i class="fas fa-clock me-2"></i> Aktivitas Terbaru</h6>
        <div id="recentActivities">
            <p class="text-muted">Loading...</p>
        </div>
    </div>
</div>

<style>
.stat-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    border-left: 4px solid #4e73df;
    position: relative;
    transition: all 0.3s;
}
.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
}
.stat-card .stat-icon {
    position: absolute;
    right: 15px;
    top: 15px;
    font-size: 2rem;
    opacity: 0.2;
    color: #4e73df;
}
.stat-card .stat-number {
    font-size: 1.8rem;
    font-weight: 700;
    color: #2d3748;
}
.stat-card .stat-label {
    color: #858796;
    font-size: 0.9rem;
    font-weight: 500;
}
.chart-card {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    margin-bottom: 15px;
}
.table-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.05);
}
body.dark-mode .stat-card,
body.dark-mode .chart-card,
body.dark-mode .table-container {
    background: #16213e;
}
body.dark-mode .stat-card .stat-number {
    color: white;
}
</style>

<script>
$(document).ready(function() {
    loadDashboardStats();
});

function loadDashboardStats() {
    $.ajax({
        url: 'api/dashboard.php',
        method: 'GET',
        success: function(response) {
            if (response.success) {
                const data = response.data;
                
                // Update stat numbers
                $('#totalFiles').text(data.total_files || 0);
                $('#uploadsToday').text(data.uploads_today || 0);
                $('#totalDownloads').text(data.total_downloads || 0);
                $('#totalUsers').text(data.total_users || 0);
                $('#totalFileTypes').text(data.total_file_types || 0);
                $('#totalFolders').text(data.total_folders || 0);
                $('#storageUsed').text(data.storage_used || '0 GB');
                $('#storageRemaining').text(data.storage_remaining || '0 GB');
                
                // Render charts
                renderCharts(data);
                
                // Render recent activities
                renderActivities(data.recent_activities || []);
            }
        },
        error: function() {
            // Show demo data if API not ready
            showDemoData();
        }
    });
}

function renderCharts(data) {
    // Upload Chart
    const uploadCtx = document.getElementById('uploadChart').getContext('2d');
    new Chart(uploadCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [{
                label: 'Upload',
                data: data.monthly_uploads || Array(12).fill(0),
                backgroundColor: 'rgba(78, 115, 223, 0.8)',
                borderColor: '#4e73df',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } }
        }
    });
    
    // Download Chart
    const downloadCtx = document.getElementById('downloadChart').getContext('2d');
    new Chart(downloadCtx, {
        type: 'bar',
        data: {
            labels: ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'],
            datasets: [{
                label: 'Download',
                data: data.monthly_downloads || Array(12).fill(0),
                backgroundColor: 'rgba(28, 200, 138, 0.8)',
                borderColor: '#1cc88a',
                borderWidth: 2
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } }
        }
    });
    
    // File Type Chart
    const fileTypeCtx = document.getElementById('fileTypeChart').getContext('2d');
    const fileTypes = data.file_types || {};
    new Chart(fileTypeCtx, {
        type: 'pie',
        data: {
            labels: Object.keys(fileTypes),
            datasets: [{
                data: Object.values(fileTypes),
                backgroundColor: [
                    '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
                    '#858796', '#5a5c69', '#f8f9fc', '#2d3748', '#6c757d'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
    
    // Storage Chart
    const storageCtx = document.getElementById('storageChart').getContext('2d');
    const used = parseFloat(data.storage_used) || 0;
    const remaining = parseFloat(data.storage_remaining) || 0;
    new Chart(storageCtx, {
        type: 'doughnut',
        data: {
            labels: ['Terpakai', 'Tersisa'],
            datasets: [{
                data: [used, remaining],
                backgroundColor: ['#4e73df', '#1cc88a'],
                borderWidth: 3
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
    
    // Activity Chart
    const activityCtx = document.getElementById('activityChart').getContext('2d');
    const users = data.user_activity || [];
    new Chart(activityCtx, {
        type: 'bar',
        data: {
            labels: users.map(u => u.username),
            datasets: [
                {
                    label: 'Upload',
                    data: users.map(u => u.uploads || 0),
                    backgroundColor: 'rgba(78, 115, 223, 0.8)'
                },
                {
                    label: 'Download',
                    data: users.map(u => u.downloads || 0),
                    backgroundColor: 'rgba(28, 200, 138, 0.8)'
                }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });
}

function renderActivities(activities) {
    let html = '';
    if (activities.length === 0) {
        html = '<p class="text-muted">Belum ada aktivitas</p>';
    } else {
        html = '<ul class="list-unstyled">';
        activities.forEach(activity => {
            const time = new Date(activity.created_at).toLocaleString('id-ID');
            html += `
                <li class="py-2 border-bottom">
                    <div class="d-flex justify-content-between">
                        <span>
                            <strong>${activity.username}</strong> 
                            <span class="text-muted">${activity.action}</span>
                        </span>
                        <small class="text-muted">${time}</small>
                    </div>
                    <div class="text-muted small">${activity.description}</div>
                </li>
            `;
        });
        html += '</ul>';
    }
    $('#recentActivities').html(html);
}

function showDemoData() {
    // Show sample data for demonstration
    const demoData = {
        total_files: 1234,
        uploads_today: 45,
        total_downloads: 892,
        total_users: 28,
        total_file_types: 15,
        total_folders: 12,
        storage_used: '17.5 GB',
        storage_remaining: '32.5 GB',
        monthly_uploads: [65, 78, 90, 85, 102, 95, 110, 120, 115, 130, 140, 150],
        monthly_downloads: [45, 58, 70, 65, 82, 75, 90, 100, 95, 110, 120, 130],
        file_types: {
            'PDF': 25, 'DOC': 18, 'DOCX': 15, 'XLS': 12, 'XLSX': 10,
            'PPT': 8, 'PPTX': 7, 'JPG': 6, 'PNG': 5, 'ZIP': 4,
            'RAR': 3, 'MP4': 2, 'MP3': 2, 'CSV': 1, 'TXT': 1, 'Lainnya': 1
        },
        user_activity: [
            { username: 'Admin', uploads: 45, downloads: 35 },
            { username: 'User1', uploads: 30, downloads: 40 },
            { username: 'User2', uploads: 25, downloads: 20 },
            { username: 'User3', uploads: 20, downloads: 25 },
            { username: 'User4', uploads: 15, downloads: 30 }
        ],
        recent_activities: [
            { username: 'Admin', action: 'upload', description: 'Uploaded file: Laporan_2025.pdf', created_at: new Date().toISOString() },
            { username: 'User1', action: 'download', description: 'Downloaded file: Foto_Profil.jpg', created_at: new Date(Date.now() - 3600000).toISOString() },
            { username: 'Admin', action: 'login', description: 'User logged in successfully', created_at: new Date(Date.now() - 7200000).toISOString() }
        ]
    };
    
    // Update stats
    $('#totalFiles').text(demoData.total_files);
    $('#uploadsToday').text(demoData.uploads_today);
    $('#totalDownloads').text(demoData.total_downloads);
    $('#totalUsers').text(demoData.total_users);
    $('#totalFileTypes').text(demoData.total_file_types);
    $('#totalFolders').text(demoData.total_folders);
    $('#storageUsed').text(demoData.storage_used);
    $('#storageRemaining').text(demoData.storage_remaining);
    
    // Render charts with demo data
    renderCharts(demoData);
    renderActivities(demoData.recent_activities);
}
</script>