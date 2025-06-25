<?php
/**
 * Main Dashboard
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Dashboard';
require_once '../../includes/header.php'; // FIXED: Changed from '../includes/header.php'

// Get current user info
$current_user = getUserInfo();
$user_cabang = getUserCabang();

// Get dashboard statistics
if ($current_user['role'] == 'admin') {
    // Manajer can see all branches
    $stats_medan = getDashboardStats(1);
    $stats_batam = getDashboardStats(2);
    $stats_total = getDashboardStats();
} else {
    // Other roles only see their branch
    $stats = getDashboardStats($user_cabang);
}

// Get recent activities based on user role
$recent_activities = array();

if ($current_user['role'] == 'karyawan') {
    // Show user's own peminjaman
    $query_activities = "SELECT p.*, a.nama_alat, a.kode_alat,
                        CASE 
                            WHEN p.status_peminjaman = 'pending' THEN 'warning'
                            WHEN p.status_peminjaman = 'disetujui' THEN 'success'
                            WHEN p.status_peminjaman = 'ditolak' THEN 'danger'
                            WHEN p.status_peminjaman = 'selesai' THEN 'info'
                        END as badge_class
                        FROM peminjaman p 
                        JOIN alat a ON p.id_alat = a.id_alat 
                        WHERE p.id_pengguna = '" . $current_user['user_id'] . "'
                        ORDER BY p.created_at DESC 
                        LIMIT 5";
} else {
    // Show branch activities for admin, all activities for manajer
    $cabang_filter = ($current_user['role'] == 'admin') ? '' : " WHERE a.id_cabang = '$user_cabang'";
    
    $query_activities = "SELECT p.*, a.nama_alat, a.kode_alat, u.nama_pengguna,
                        CASE 
                            WHEN p.status_peminjaman = 'pending' THEN 'warning'
                            WHEN p.status_peminjaman = 'disetujui' THEN 'success'
                            WHEN p.status_peminjaman = 'ditolak' THEN 'danger'
                            WHEN p.status_peminjaman = 'selesai' THEN 'info'
                        END as badge_class
                        FROM peminjaman p 
                        JOIN alat a ON p.id_alat = a.id_alat 
                        JOIN pengguna u ON p.id_pengguna = u.id_pengguna
                        $cabang_filter
                        ORDER BY p.created_at DESC 
                        LIMIT 10";
}

$result_activities = executeQuery($query_activities);
while ($row = fetchArray($result_activities)) {
    $recent_activities[] = $row;
}

// Get pending approvals for admin/manajer
$pending_approvals = array();
if (hasPermission('approve_peminjaman')) {
    $approval_filter = ($current_user['role'] == 'admin') ? '' : " AND a.id_cabang = '$user_cabang'";
    
    $query_pending = "SELECT p.*, a.nama_alat, a.kode_alat, u.nama_pengguna, c.nama_cabang
                     FROM peminjaman p 
                     JOIN alat a ON p.id_alat = a.id_alat 
                     JOIN pengguna u ON p.id_pengguna = u.id_pengguna
                     JOIN cabang c ON a.id_cabang = c.id_cabang
                     WHERE p.status_peminjaman = 'pending'
                     $approval_filter
                     ORDER BY p.created_at ASC 
                     LIMIT 5";
    
    $result_pending = executeQuery($query_pending);
    while ($row = fetchArray($result_pending)) {
        $pending_approvals[] = $row;
    }
}
?>

<div class="container-fluid">
    <!-- Welcome Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-2">
                        <i class="fas fa-home me-2"></i>
                        Selamat Datang, <?php echo htmlspecialchars($current_user['nama_pengguna']); ?>!
                    </h4>
                    <p class="card-text text-muted">
                        <?php echo ucfirst($current_user['role']); ?> - 
                        <?php echo getCabangName($current_user['id_cabang']); ?> | 
                        <?php echo date('l, d F Y'); ?>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <?php if ($current_user['role'] == 'admin'): ?>
        <!-- Manajer View - Multi Branch -->
        <div class="row mb-4">
            <div class="col-12">
                <h5><i class="fas fa-chart-bar me-2"></i>Statistik Keseluruhan</h5>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-tools fa-2x text-primary mb-2"></i>
                        <h4 class="text-primary"><?php echo $stats_total['total_alat']; ?></h4>
                        <p class="card-text">Total Alat</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4 class="text-success"><?php echo $stats_total['alat_tersedia']; ?></h4>
                        <p class="card-text">Alat Tersedia</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-hand-holding fa-2x text-info mb-2"></i>
                        <h4 class="text-info"><?php echo $stats_total['alat_dipinjam']; ?></h4>
                        <p class="card-text">Alat Dipinjam</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4 class="text-warning"><?php echo $stats_total['peminjaman_pending']; ?></h4>
                        <p class="card-text">Pending Approval</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branch Comparison -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-building me-2"></i>Cabang Medan</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="text-primary"><?php echo $stats_medan['total_alat']; ?></h5>
                                <small>Total Alat</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success"><?php echo $stats_medan['alat_tersedia']; ?></h5>
                                <small>Tersedia</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-building me-2"></i>Cabang Batam</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-6">
                                <h5 class="text-primary"><?php echo $stats_batam['total_alat']; ?></h5>
                                <small>Total Alat</small>
                            </div>
                            <div class="col-6">
                                <h5 class="text-success"><?php echo $stats_batam['alat_tersedia']; ?></h5>
                                <small>Tersedia</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Single Branch View -->
        <div class="row mb-4">
            <div class="col-12">
                <h5><i class="fas fa-chart-bar me-2"></i>Statistik <?php echo getCabangName($user_cabang); ?></h5>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-tools fa-2x text-primary mb-2"></i>
                        <h4 class="text-primary"><?php echo $stats['total_alat']; ?></h4>
                        <p class="card-text">Total Alat</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <h4 class="text-success"><?php echo $stats['alat_tersedia']; ?></h4>
                        <p class="card-text">Alat Tersedia</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-hand-holding fa-2x text-info mb-2"></i>
                        <h4 class="text-info"><?php echo $stats['alat_dipinjam']; ?></h4>
                        <p class="card-text">Alat Dipinjam</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card text-center">
                    <div class="card-body">
                        <i class="fas fa-clock fa-2x text-warning mb-2"></i>
                        <h4 class="text-warning"><?php echo $stats['peminjaman_pending']; ?></h4>
                        <p class="card-text">Pending Approval</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recent Activities & Pending Approvals - Hide from Admin -->
<?php if ($current_user['role'] != 'manajer'): ?>
<div class="row">
    <!-- Recent Activities -->
    <div class="col-md-<?php echo (hasPermission('approve_peminjaman') && !empty($pending_approvals)) ? '6' : '12'; ?>">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-history me-2"></i>
                    <?php echo ($current_user['role'] == 'karyawan') ? 'Riwayat Peminjaman Saya' : 'Aktivitas Terbaru'; ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_activities)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recent_activities as $activity): ?>
                            <div class="list-group-item border-0 px-0">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($activity['nama_alat']); ?></h6>
                                        <p class="mb-1 text-muted small">
                                            <strong>Kode:</strong> <?php echo htmlspecialchars($activity['kode_alat']); ?>
                                            <?php if ($current_user['role'] != 'karyawan'): ?>
                                                <br><strong>Peminjam:</strong> <?php echo htmlspecialchars($activity['nama_pengguna']); ?>
                                            <?php endif; ?>
                                        </p>
                                        <small class="text-muted">
                                            <?php echo formatDateTime($activity['created_at']); ?>
                                        </small>
                                    </div>
                                    <span class="badge bg-<?php echo $activity['badge_class']; ?>">
                                        <?php echo ucfirst($activity['status_peminjaman']); ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="text-center mt-3">
                        <a href="../peminjaman/" class="btn btn-outline-primary btn-sm">
                            Lihat Semua <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Belum ada aktivitas</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Pending Approvals -->
    <?php if (hasPermission('approve_peminjaman') && !empty($pending_approvals)): ?>
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-clipboard-check me-2"></i>
                    Menunggu Persetujuan
                    <span class="badge bg-warning ms-2"><?php echo count($pending_approvals); ?></span>
                </h6>
            </div>
            <div class="card-body">
                <div class="list-group list-group-flush">
                    <?php foreach ($pending_approvals as $pending): ?>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?php echo htmlspecialchars($pending['nama_alat']); ?></h6>
                                    <p class="mb-1 text-muted small">
                                        <strong>Peminjam:</strong> <?php echo htmlspecialchars($pending['nama_pengguna']); ?>
                                        <?php if ($current_user['role'] == 'admin'): ?>
                                            <br><strong>Cabang:</strong> <?php echo htmlspecialchars($pending['nama_cabang']); ?>
                                        <?php endif; ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo formatDateTime($pending['created_at']); ?>
                                    </small>
                                </div>
                                <div class="d-flex gap-1">
                                    <a href="../peminjaman/detail.php?id=<?php echo $pending['id_peminjaman']; ?>" 
                                       class="btn btn-outline-primary btn-sm" title="Lihat Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="text-center mt-3">
                    <a href="../peminjaman/?status=pending" class="btn btn-warning btn-sm">
                        Lihat Semua Pending <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

    <!-- Quick Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-bolt me-2"></i>Aksi Cepat</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php if (hasPermission('request_peminjaman')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="../peminjaman/form-peminjaman.php" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Ajukan Peminjaman
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('return_tools')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="../pengembalian/" class="btn btn-success w-100">
                                <i class="fas fa-undo me-2"></i>Kembalikan Alat
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('manage_tools')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="../alat/" class="btn btn-info w-100">
                                <i class="fas fa-tools me-2"></i>Kelola Alat
                            </a>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('view_reports')): ?>
                        <div class="col-md-3 mb-2">
                            <a href="../laporan/" class="btn btn-secondary w-100">
                                <i class="fas fa-chart-bar me-2"></i>Lihat Laporan
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; // FIXED: Changed from '../includes/footer.php' ?>