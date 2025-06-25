<?php
/**
 * Daftar Peminjaman
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Daftar Peminjaman';
require_once '../../includes/header.php';

// Check permission
if (!hasPermission('request_peminjaman') && !hasPermission('approve_peminjaman')) {
    header("Location: ../dashboard/");
    exit();
}

$current_user = getUserInfo();
$user_cabang = getUserCabang();

// Get filter parameters
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$start_date = isset($_GET['start_date']) ? sanitizeInput($_GET['start_date']) : '';
$end_date = isset($_GET['end_date']) ? sanitizeInput($_GET['end_date']) : '';

// Pagination parameters - Enhanced like Data Alat
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$records_per_page = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;

// Validate records per page (only allow specific values)
$allowed_per_page = [10, 25, 50, 100];
if (!in_array($records_per_page, $allowed_per_page)) {
    $records_per_page = 10; // Default value
}

$offset = ($page - 1) * $records_per_page;

// Build query based on user role
$where_conditions = array();
$join_conditions = "FROM peminjaman p 
                   JOIN alat a ON p.id_alat = a.id_alat 
                   JOIN pengguna u ON p.id_pengguna = u.id_pengguna
                   JOIN cabang c ON a.id_cabang = c.id_cabang";

// Role-based filtering
if ($current_user['role'] == 'karyawan') {
    $where_conditions[] = "p.id_pengguna = '" . $current_user['user_id'] . "'";
} elseif ($current_user['role'] == 'manajer') {
    $where_conditions[] = "a.id_cabang = '$user_cabang'";
}
// Admin can see all

// Status filter
if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "p.status_peminjaman = '" . escapeString($status_filter) . "'";
}

// Date range filter
if (!empty($start_date)) {
    $where_conditions[] = "p.tanggal_pinjam >= '" . escapeString($start_date) . "'";
}
if (!empty($end_date)) {
    $where_conditions[] = "p.tanggal_pinjam <= '" . escapeString($end_date) . "'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total $join_conditions $where_clause";
$count_result = executeQuery($count_query);
$total_records = fetchArray($count_result)['total'];

// Get peminjaman data
$query = "SELECT p.*, a.nama_alat, a.kode_alat, u.nama_pengguna, c.nama_cabang,
          admin.nama_pengguna as nama_admin,
          CASE 
              WHEN p.status_peminjaman = 'pending' THEN 'warning'
              WHEN p.status_peminjaman = 'disetujui' THEN 'success'
              WHEN p.status_peminjaman = 'ditolak' THEN 'danger'
              WHEN p.status_peminjaman = 'selesai' THEN 'info'
          END as badge_class
          $join_conditions
          LEFT JOIN pengguna admin ON p.disetujui_oleh = admin.id_pengguna
          $where_clause
          ORDER BY p.created_at DESC 
          LIMIT $offset, $records_per_page";

$result = executeQuery($query);
$peminjaman_list = array();
while ($row = fetchArray($result)) {
    $peminjaman_list[] = $row;
}

// Get status options for filter
$status_options = array(
    'all' => 'Semua Status',
    'pending' => 'Pending',
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak',
    'selesai' => 'Selesai'
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
            <h4><i class="fas fa-hand-holding me-2"></i><?php echo $page_title; ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Peminjaman</li>
                </ol>
            </nav>
        </div>
        
        <?php if (hasPermission('request_peminjaman')): ?>
        <div>
            <a href="form-peminjaman.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Ajukan Peminjaman
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach ($status_options as $value => $label): ?>
                            <option value="<?php echo $value; ?>" <?php echo ($status_filter == $value) ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Tanggal Akhir</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="fas fa-search me-1"></i>Filter
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Reset
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Stats -->
    <?php
    // Get quick stats for current filters
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN p.status_peminjaman = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN p.status_peminjaman = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
                    SUM(CASE WHEN p.status_peminjaman = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
                    SUM(CASE WHEN p.status_peminjaman = 'selesai' THEN 1 ELSE 0 END) as selesai
                    $join_conditions $where_clause";
    $stats_result = executeQuery($stats_query);
    $stats = fetchArray($stats_result);
    ?>
    
    <?php if ($current_user['role'] != 'karyawan'): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center bg-warning text-white">
                <div class="card-body">
                    <h4><?php echo $stats['pending']; ?></h4>
                    <p class="mb-0">Pending</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h4><?php echo $stats['disetujui']; ?></h4>
                    <p class="mb-0">Disetujui</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <h4><?php echo $stats['ditolak']; ?></h4>
                    <p class="mb-0">Ditolak</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h4><?php echo $stats['selesai']; ?></h4>
                    <p class="mb-0">Selesai</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Peminjaman List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Daftar Peminjaman
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
            <?php if (!empty($peminjaman_list)): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <label class="me-2 text-muted small">Tampilkan:</label>
                    <form method="GET" class="d-inline">
                        <!-- Preserve all current filters -->
                        <?php if (!empty($status_filter)): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <?php endif; ?>
                        <?php if (!empty($start_date)): ?>
                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                        <?php endif; ?>
                        <?php if (!empty($end_date)): ?>
                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
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

            <?php if (!empty($peminjaman_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal</th>
                                <th>Alat</th>
                                <?php if ($current_user['role'] != 'karyawan'): ?>
                                    <th>Peminjam</th>
                                    <?php if ($current_user['role'] == 'admin'): ?>
                                        <th>Cabang</th>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <th>Tanggal Kembali</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($peminjaman_list as $peminjaman): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo str_pad($peminjaman['id_peminjaman'], 4, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td><?php echo formatDate($peminjaman['tanggal_pinjam']); ?></td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($peminjaman['nama_alat']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($peminjaman['kode_alat']); ?></small>
                                        </div>
                                    </td>
                                    
                                    <?php if ($current_user['role'] != 'karyawan'): ?>
                                        <td><?php echo htmlspecialchars($peminjaman['nama_pengguna']); ?></td>
                                        <?php if ($current_user['role'] == 'admin'): ?>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($peminjaman['nama_cabang']); ?></span></td>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <td><?php echo formatDate($peminjaman['tanggal_kembali_rencana']); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo $peminjaman['badge_class']; ?>">
                                            <?php echo ucfirst($peminjaman['status_peminjaman']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail.php?id=<?php echo $peminjaman['id_peminjaman']; ?>" 
                                               class="btn btn-outline-primary" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (hasPermission('approve_peminjaman') && $peminjaman['status_peminjaman'] == 'pending'): ?>
                                                <a href="approve.php?id=<?php echo $peminjaman['id_peminjaman']; ?>" 
                                                   class="btn btn-outline-success" title="Setujui"
                                                   onclick="return confirm('Setujui peminjaman ini?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="reject.php?id=<?php echo $peminjaman['id_peminjaman']; ?>" 
                                                   class="btn btn-outline-danger" title="Tolak"
                                                   onclick="return confirm('Tolak peminjaman ini?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
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
                            if (!empty($status_filter) && $status_filter != 'all') {
                                $pagination_params[] = "status=" . urlencode($status_filter);
                            }
                            if (!empty($start_date)) {
                                $pagination_params[] = "start_date=" . urlencode($start_date);
                            }
                            if (!empty($end_date)) {
                                $pagination_params[] = "end_date=" . urlencode($end_date);
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
                    <h5 class="text-muted">Tidak ada data peminjaman</h5>
                    <p class="text-muted">
                        <?php if (hasPermission('request_peminjaman')): ?>
                            <a href="form-peminjaman.php" class="btn btn-primary mt-2">
                                <i class="fas fa-plus me-2"></i>Ajukan Peminjaman Pertama
                            </a>
                        <?php else: ?>
                            Data peminjaman akan muncul di sini.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>