<?php
/**
 * Daftar Pengguna
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Data Pengguna';
require_once '../../includes/header.php';

// Check permission
if (!hasPermission('manage_users')) {
    header("Location: ../dashboard/");
    exit();
}

$current_user = getUserInfo();
$user_cabang = getUserCabang();

// Get filter parameters
$cabang_filter = isset($_GET['cabang']) ? sanitizeInput($_GET['cabang']) : '';
$jabatan_filter = isset($_GET['jabatan']) ? sanitizeInput($_GET['jabatan']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Pagination parameters - Enhanced like other templates
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

// Validate records per page (only allow specific values)
$allowed_per_page = [10, 25, 50, 100];
if (!in_array($records_per_page, $allowed_per_page)) {
    $records_per_page = 10; // Default value
}

$offset = ($page - 1) * $records_per_page;

// Build query based on user role and filters
$where_conditions = array();
$join_conditions = "FROM pengguna p 
                   JOIN departemen d ON p.id_departemen = d.id_departemen
                   JOIN cabang c ON p.id_cabang = c.id_cabang";

// Role-based filtering
if ($current_user['role'] == 'manajer') {
    // Admin can only manage users from their branch
    $where_conditions[] = "p.id_cabang = '$user_cabang'";
}
// Admin can see all branches

// Cabang filter (for admin)
if (!empty($cabang_filter) && $cabang_filter != 'all' && $current_user['role'] == 'admin') {
    $where_conditions[] = "p.id_cabang = '" . escapeString($cabang_filter) . "'";
}

// Jabatan filter
if (!empty($jabatan_filter) && $jabatan_filter != 'all') {
    $where_conditions[] = "p.jabatan = '" . escapeString($jabatan_filter) . "'";
}

// Status filter
if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "p.status = '" . escapeString($status_filter) . "'";
}

// Search filter
if (!empty($search)) {
    $search_escaped = escapeString($search);
    $where_conditions[] = "(p.nama_pengguna LIKE '%$search_escaped%' OR p.username LIKE '%$search_escaped%' OR d.nama_departemen LIKE '%$search_escaped%')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total $join_conditions $where_clause";
$count_result = executeQuery($count_query);
$total_records = fetchArray($count_result)['total'];

// Get pengguna data
$query = "SELECT p.*, d.nama_departemen, c.nama_cabang,
          CASE 
              WHEN p.status = 'aktif' THEN 'success'
              WHEN p.status = 'nonaktif' THEN 'danger'
          END as badge_class,
          CASE 
              WHEN p.jabatan = 'admin' THEN 'primary'
              WHEN p.jabatan = 'manajer' THEN 'warning'
              WHEN p.jabatan = 'karyawan' THEN 'info'
          END as role_class
          $join_conditions
          $where_clause
          ORDER BY p.created_at DESC 
          LIMIT $offset, $records_per_page";

$result = executeQuery($query);
$pengguna_list = array();
while ($row = fetchArray($result)) {
    $pengguna_list[] = $row;
}

// Get all cabang for filter (only for admin)
$cabang_list = array();
if ($current_user['role'] == 'admin') {
    $cabang_list = getAllCabang();
}

// Filter options
$jabatan_options = array(
    'all' => 'Semua Jabatan',
    'admin' => 'Admin',
    'manajer' => 'Manajer',
    'karyawan' => 'Karyawan'
);

$status_options = array(
    'all' => 'Semua Status',
    'aktif' => 'Aktif',
    'nonaktif' => 'Non-Aktif'
);

// Records per page options
$per_page_options = array(
    10 => '10',
    25 => '25',
    50 => '50',
    100 => '100'
);
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="fas fa-users me-2"></i><?php echo $page_title; ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Data Pengguna</li>
                </ol>
            </nav>
        </div>
        
        <div class="btn-group">
            <a href="tambah.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tambah Pengguna
            </a>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <?php if ($current_user['role'] == 'admin' && !empty($cabang_list)): ?>
                <div class="col-md-2">
                    <label for="cabang" class="form-label">Cabang</label>
                    <select class="form-select" id="cabang" name="cabang">
                        <option value="all">Semua Cabang</option>
                        <?php foreach ($cabang_list as $cabang): ?>
                            <option value="<?php echo $cabang['id_cabang']; ?>" <?php echo ($cabang_filter == $cabang['id_cabang']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cabang['nama_cabang']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="col-md-2">
                    <label for="jabatan" class="form-label">Jabatan</label>
                    <select class="form-select" id="jabatan" name="jabatan">
                        <?php foreach ($jabatan_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($jabatan_filter == $value) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($status_filter == $value) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Pencarian</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Cari nama, username, atau departemen..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search me-1"></i>Cari
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Statistics Cards -->
    <?php
    // Get quick stats for current filters
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN p.status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN p.jabatan = 'admin' THEN 1 ELSE 0 END) as admin,
                    SUM(CASE WHEN p.jabatan = 'manajer' THEN 1 ELSE 0 END) as manajer,
                    SUM(CASE WHEN p.jabatan = 'karyawan' THEN 1 ELSE 0 END) as karyawan
                    $join_conditions $where_clause";
    $stats_result = executeQuery($stats_query);
    $stats = fetchArray($stats_result);
    ?>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h4><?php echo $stats['total']; ?></h4>
                    <p class="mb-0">Total Pengguna</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h4><?php echo $stats['aktif']; ?></h4>
                    <p class="mb-0">Pengguna Aktif</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-warning text-white">
                <div class="card-body">
                    <h4><?php echo $stats['admin']; ?></h4>
                    <p class="mb-0">Admin</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h4><?php echo $stats['karyawan']; ?></h4>
                    <p class="mb-0">Karyawan</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Users List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Daftar Pengguna
                    <span class="badge bg-primary ms-2"><?php echo $total_records; ?> Total</span>
                </h6>
                <div class="text-muted small">
                    <?php 
                    $start_record = $total_records > 0 ? ($offset + 1) : 0;
                    $end_record = min($offset + $records_per_page, $total_records);
                    echo "Menampilkan $start_record - $end_record dari $total_records data";
                    ?>
                </div>
            </div>
        </div>
        <div class="card-body">
            <!-- Table Controls -->
            <?php if (!empty($pengguna_list)): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <label class="me-2 text-muted small">Tampilkan:</label>
                    <form method="GET" class="d-inline">
                        <!-- Preserve all current filters -->
                        <?php if (!empty($cabang_filter)): ?>
                            <input type="hidden" name="cabang" value="<?php echo htmlspecialchars($cabang_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($jabatan_filter)): ?>
                            <input type="hidden" name="jabatan" value="<?php echo htmlspecialchars($jabatan_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($status_filter)): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($search)): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                        
                        <select class="form-select form-select-sm" name="per_page" onchange="this.form.submit()" style="width: auto;">
                            <?php foreach ($per_page_options as $value => $label): ?>
                                <option value="<?php echo $value; ?>" <?php echo ($records_per_page == $value) ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                    <span class="ms-2 text-muted small">data per halaman</span>
                </div>
                
            </div>
            <?php endif; ?>

            <?php if (!empty($pengguna_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nama Pengguna</th>
                                <th>Username</th>
                                <th>Jabatan</th>
                                <th>Departemen</th>
                                <?php if ($current_user['role'] == 'admin'): ?>
                                    <th>Cabang</th>
                                <?php endif; ?>
                                <th>Status</th>
                                <th>Terakhir Login</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pengguna_list as $pengguna): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle me-2">
                                                <i class="fas fa-user"></i>
                                            </div>
                                            <div>
                                                <strong><?php echo htmlspecialchars($pengguna['nama_pengguna']); ?></strong>
                                                <br><small class="text-muted">ID: <?php echo $pengguna['id_pengguna']; ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($pengguna['username']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $pengguna['role_class']; ?>">
                                            <?php echo ucfirst($pengguna['jabatan']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($pengguna['nama_departemen']); ?></td>
                                    
                                    <?php if ($current_user['role'] == 'admin'): ?>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($pengguna['nama_cabang']); ?></span></td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <span class="badge bg-<?php echo $pengguna['badge_class']; ?>">
                                            <?php echo ucfirst($pengguna['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo formatDateTime($pengguna['updated_at']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail.php?id=<?php echo $pengguna['id_pengguna']; ?>" 
                                               class="btn btn-outline-primary" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if ($pengguna['id_pengguna'] != $current_user['user_id']): ?>
                                                <a href="edit.php?id=<?php echo $pengguna['id_pengguna']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <a href="reset-password.php?id=<?php echo $pengguna['id_pengguna']; ?>" 
                                                   class="btn btn-outline-info" title="Reset Password"
                                                   onclick="return confirm('Reset password pengguna ini?')">
                                                    <i class="fas fa-key"></i>
                                                </a>
                                                
                                                <?php if ($pengguna['status'] == 'aktif'): ?>
                                                    <a href="toggle-status.php?id=<?php echo $pengguna['id_pengguna']; ?>&action=deactivate" 
                                                       class="btn btn-outline-danger" title="Non-aktifkan"
                                                       onclick="return confirm('Non-aktifkan pengguna ini?')">
                                                        <i class="fas fa-ban"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <a href="toggle-status.php?id=<?php echo $pengguna['id_pengguna']; ?>&action=activate" 
                                                       class="btn btn-outline-success" title="Aktifkan"
                                                       onclick="return confirm('Aktifkan pengguna ini?')">
                                                        <i class="fas fa-check"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge bg-warning">You</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Enhanced Pagination with Orange Background -->
                <?php if ($total_records > $records_per_page): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4 p-3 rounded border" style="background-color: #ff9500;">
                        <div class="small" style="color: #17a2b8;">
                            <?php 
                            $start_record = $total_records > 0 ? ($offset + 1) : 0;
                            $end_record = min($offset + $records_per_page, $total_records);
                            echo "Menampilkan $start_record - $end_record dari $total_records data";
                            ?>
                        </div>
                        <div>
                            <?php
                            // Build pagination URL with all current parameters except 'page'
                            $pagination_params = array();
                            if (!empty($cabang_filter) && $cabang_filter != 'all') {
                                $pagination_params[] = "cabang=" . urlencode($cabang_filter);
                            }
                            if (!empty($jabatan_filter) && $jabatan_filter != 'all') {
                                $pagination_params[] = "jabatan=" . urlencode($jabatan_filter);
                            }
                            if (!empty($status_filter) && $status_filter != 'all') {
                                $pagination_params[] = "status=" . urlencode($status_filter);
                            }
                            if (!empty($search)) {
                                $pagination_params[] = "search=" . urlencode($search);
                            }
                            $pagination_params[] = "per_page=" . $records_per_page;
                            
                            $base_url = 'index.php';
                            if (!empty($pagination_params)) {
                                $base_url .= '?' . implode('&', $pagination_params);
                                $separator = '&';
                            } else {
                                $separator = '?';
                            }
                            
                            // Custom pagination with proper URL building
                            $total_pages = ceil($total_records / $records_per_page);
                            echo '<nav aria-label="Page navigation">';
                            echo '<ul class="pagination pagination-sm mb-0">';
                            
                            // Previous button
                            if ($page > 1) {
                                $prev_url = $base_url . $separator . 'page=' . ($page - 1);
                                echo '<li class="page-item"><a class="page-link" href="' . $prev_url . '">‹ Previous</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">‹ Previous</span></li>';
                            }
                            
                            // Page numbers
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            if ($start_page > 1) {
                                $first_url = $base_url . $separator . 'page=1';
                                echo '<li class="page-item"><a class="page-link" href="' . $first_url . '">1</a></li>';
                                if ($start_page > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }
                            
                            for ($i = $start_page; $i <= $end_page; $i++) {
                                if ($i == $page) {
                                    echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                } else {
                                    $page_url = $base_url . $separator . 'page=' . $i;
                                    echo '<li class="page-item"><a class="page-link" href="' . $page_url . '">' . $i . '</a></li>';
                                }
                            }
                            
                            if ($end_page < $total_pages) {
                                if ($end_page < $total_pages - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                $last_url = $base_url . $separator . 'page=' . $total_pages;
                                echo '<li class="page-item"><a class="page-link" href="' . $last_url . '">' . $total_pages . '</a></li>';
                            }
                            
                            // Next button
                            if ($page < $total_pages) {
                                $next_url = $base_url . $separator . 'page=' . ($page + 1);
                                echo '<li class="page-item"><a class="page-link" href="' . $next_url . '">Next ›</a></li>';
                            } else {
                                echo '<li class="page-item disabled"><span class="page-link">Next ›</span></li>';
                            }
                            
                            echo '</ul>';
                            echo '</nav>';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada data pengguna</h5>
                    <p class="text-muted">
                        <a href="tambah.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i>Tambah Pengguna Pertama
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #ff6600, #ff8533);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}
</style>

<?php require_once '../../includes/footer.php'; ?>