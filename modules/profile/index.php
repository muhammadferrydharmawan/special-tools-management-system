<?php
/**
 * Profile Management
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Profil Pengguna';
require_once '../../includes/header.php';

// Get current user info
$current_user = getUserInfo();

// Get user's department and branch info
$query_user_detail = "SELECT u.*, d.nama_departemen, c.nama_cabang, c.kode_cabang 
                      FROM pengguna u 
                      JOIN departemen d ON u.id_departemen = d.id_departemen 
                      JOIN cabang c ON u.id_cabang = c.id_cabang 
                      WHERE u.id_pengguna = '" . $current_user['user_id'] . "'";

$result_user = executeQuery($query_user_detail);
$user_detail = fetchArray($result_user);

// Handle form submission for profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $nama_pengguna = sanitizeInput($_POST['nama_pengguna']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = array();
    
    // Validate input
    if (empty($nama_pengguna)) {
        $errors[] = "Nama pengguna tidak boleh kosong";
    }
    
    // If user wants to change password
    if (!empty($current_password) || !empty($new_password)) {
        if (empty($current_password)) {
            $errors[] = "Password saat ini harus diisi";
        } else {
            // Verify current password
            if (md5($current_password) !== $user_detail['password']) {
                $errors[] = "Password saat ini tidak sesuai";
            }
        }
        
        if (empty($new_password)) {
            $errors[] = "Password baru harus diisi";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Password baru minimal 6 karakter";
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = "Konfirmasi password tidak sesuai";
        }
    }
    
    if (empty($errors)) {
        // Update profile
        $update_query = "UPDATE pengguna SET nama_pengguna = '$nama_pengguna'";
        
        // Add password update if provided
        if (!empty($new_password)) {
            $hashed_password = md5($new_password);
            $update_query .= ", password = '$hashed_password'";
        }
        
        $update_query .= " WHERE id_pengguna = '" . $current_user['user_id'] . "'";
        
        if (executeQuery($update_query)) {
            $_SESSION['success_message'] = "Profil berhasil diperbarui!";
            // Refresh user data
            $result_user = executeQuery($query_user_detail);
            $user_detail = fetchArray($result_user);
        } else {
            $errors[] = "Gagal memperbarui profil";
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error_message'] = implode('<br>', $errors);
    }
}

// Get user's activity stats
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM peminjaman WHERE id_pengguna = '" . $current_user['user_id'] . "') as total_peminjaman,
    (SELECT COUNT(*) FROM peminjaman WHERE id_pengguna = '" . $current_user['user_id'] . "' AND status_peminjaman = 'pending') as pending_peminjaman,
    (SELECT COUNT(*) FROM peminjaman WHERE id_pengguna = '" . $current_user['user_id'] . "' AND status_peminjaman = 'disetujui') as approved_peminjaman,
    (SELECT COUNT(*) FROM peminjaman WHERE id_pengguna = '" . $current_user['user_id'] . "' AND status_peminjaman = 'selesai') as completed_peminjaman";

$result_stats = executeQuery($stats_query);
$user_stats = fetchArray($result_stats);

// Get recent borrowing history
$history_query = "SELECT p.*, a.nama_alat, a.kode_alat,
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

$result_history = executeQuery($history_query);
$recent_history = array();
while ($row = fetchArray($result_history)) {
    $recent_history[] = $row;
}
?>

<div class="container-fluid">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-2">
                        <i class="fas fa-user-circle me-2"></i>
                        Profil Pengguna
                    </h4>
                    <p class="card-text text-muted">
                        Kelola informasi profil dan keamanan akun Anda
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Profile Information -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-edit me-2"></i>Edit Profil</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="nama_pengguna" class="form-label">Nama Lengkap</label>
                                    <input type="text" class="form-control" id="nama_pengguna" name="nama_pengguna" 
                                           value="<?php echo htmlspecialchars($user_detail['nama_pengguna']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" 
                                           value="<?php echo htmlspecialchars($user_detail['username']); ?>" readonly>
                                    <small class="text-muted">Username tidak dapat diubah</small>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="jabatan" class="form-label">Jabatan</label>
                                    <input type="text" class="form-control" id="jabatan" 
                                           value="<?php echo ucfirst($user_detail['jabatan']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="departemen" class="form-label">Departemen</label>
                                    <input type="text" class="form-control" id="departemen" 
                                           value="<?php echo htmlspecialchars($user_detail['nama_departemen']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="cabang" class="form-label">Cabang</label>
                                    <input type="text" class="form-control" id="cabang" 
                                           value="<?php echo htmlspecialchars($user_detail['nama_cabang']); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="status" class="form-label">Status</label>
                                    <input type="text" class="form-control" id="status" 
                                           value="<?php echo ucfirst($user_detail['status']); ?>" readonly>
                                </div>
                            </div>
                        </div>

                        <hr>
                        <h6 class="mb-3"><i class="fas fa-lock me-2"></i>Ubah Password</h6>
                        <small class="text-muted mb-3 d-block">Kosongkan jika tidak ingin mengubah password</small>

                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Password Saat Ini</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Password Baru</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" name="update_profile" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Simpan Perubahan
                            </button>
                            <a href="../dashboard/" class="btn btn-secondary">
                                <i class="fas fa-arrow-left me-2"></i>Kembali
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Profile Summary & Stats -->
        <div class="col-md-4">
            <!-- Profile Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informasi Akun</h6>
                </div>
                <div class="card-body text-center">
                    <i class="fas fa-user-circle fa-5x text-muted mb-3"></i>
                    <h5><?php echo htmlspecialchars($user_detail['nama_pengguna']); ?></h5>
                    <p class="text-muted mb-1"><?php echo ucfirst($user_detail['jabatan']); ?></p>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($user_detail['nama_cabang']); ?></p>
                    <small class="text-muted">
                        Bergabung sejak: <?php echo formatDate($user_detail['created_at']); ?>
                    </small>
                </div>
            </div>

            <!-- Statistics -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Statistik Peminjaman</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <h4 class="text-primary"><?php echo $user_stats['total_peminjaman']; ?></h4>
                            <small>Total Peminjaman</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h4 class="text-warning"><?php echo $user_stats['pending_peminjaman']; ?></h4>
                            <small>Pending</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-success"><?php echo $user_stats['approved_peminjaman']; ?></h4>
                            <small>Disetujui</small>
                        </div>
                        <div class="col-6">
                            <h4 class="text-info"><?php echo $user_stats['completed_peminjaman']; ?></h4>
                            <small>Selesai</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Aktivitas Terbaru</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($recent_history)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_history as $history): ?>
                                <div class="list-group-item border-0 px-0">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 small"><?php echo htmlspecialchars($history['nama_alat']); ?></h6>
                                            <p class="mb-1 text-muted small">
                                                <?php echo htmlspecialchars($history['kode_alat']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <?php echo formatDate($history['created_at']); ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-<?php echo $history['badge_class']; ?> small">
                                            <?php echo ucfirst($history['status_peminjaman']); ?>
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
                        <div class="text-center py-3">
                            <i class="fas fa-inbox fa-2x text-muted mb-2"></i>
                            <p class="text-muted small">Belum ada aktivitas</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>