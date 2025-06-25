<?php
/**
 * Daftar Alat
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Data Alat';
require_once '../../includes/header.php';

// Check permission
if (!hasPermission('manage_tools') && !hasPermission('request_peminjaman')) {
    header("Location: ../dashboard/");
    exit();
}

$current_user = getUserInfo();
$user_cabang = getUserCabang();

// Get filter parameters
$cabang_filter = isset($_GET['cabang']) ? sanitizeInput($_GET['cabang']) : '';
$status_filter = isset($_GET['status']) ? sanitizeInput($_GET['status']) : '';
$search = isset($_GET['search']) ? sanitizeInput($_GET['search']) : '';

// Pagination parameters
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
$join_conditions = "FROM alat a JOIN cabang c ON a.id_cabang = c.id_cabang";

// Role-based filtering
if ($current_user['role'] == 'manajer') {
    // Admin can only see tools from their branch
    $where_conditions[] = "a.id_cabang = '$user_cabang'";
} elseif ($current_user['role'] == 'karyawan') {
    // Karyawan can only see tools from their branch
    $where_conditions[] = "a.id_cabang = '$user_cabang'";
}
// Manajer can see all branches

// Cabang filter (for manajer)
if (!empty($cabang_filter) && $cabang_filter != 'all' && $current_user['role'] == 'admin') {
    $where_conditions[] = "a.id_cabang = '" . escapeString($cabang_filter) . "'";
}

// Status filter
if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "a.status_ketersediaan = '" . escapeString($status_filter) . "'";
}

// Search filter
if (!empty($search)) {
    $search_escaped = escapeString($search);
    $where_conditions[] = "(a.nama_alat LIKE '%$search_escaped%' OR a.kode_alat LIKE '%$search_escaped%' OR a.deskripsi_alat LIKE '%$search_escaped%')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total $join_conditions $where_clause";
$count_result = executeQuery($count_query);
$total_records = fetchArray($count_result)['total'];

// Get alat data
$query = "SELECT a.*, c.nama_cabang,
          CASE 
              WHEN a.status_ketersediaan = 'tersedia' THEN 'success'
              WHEN a.status_ketersediaan = 'dipinjam' THEN 'warning'
              WHEN a.status_ketersediaan = 'maintenance' THEN 'danger'
          END as badge_class
          $join_conditions
          $where_clause
          ORDER BY a.created_at DESC 
          LIMIT $offset, $records_per_page";

$result = executeQuery($query);
$alat_list = array();
while ($row = fetchArray($result)) {
    $alat_list[] = $row;
}

// Get all cabang for filter (only for manajer)
$cabang_list = array();
if ($current_user['role'] == 'admin') {
    $cabang_list = getAllCabang();
}

// Status options for filter
$status_options = array(
    'all' => 'Semua Status',
    'tersedia' => 'Tersedia',
    'dipinjam' => 'Dipinjam',
    'maintenance' => 'Maintenance',
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
            <h4><i class="fas fa-tools me-2"></i><?php echo $page_title; ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Data Alat</li>
                </ol>
            </nav>
        </div>
        
        <?php if (hasPermission('manage_tools')): ?>
        <div class="btn-group">
            <a href="tambah.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tambah Alat
            </a>
        </div>
        <?php endif; ?>
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
                           placeholder="Cari nama, kode, atau deskripsi alat..." value="<?php echo htmlspecialchars($search); ?>">
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
                    SUM(CASE WHEN a.status_ketersediaan = 'tersedia' THEN 1 ELSE 0 END) as tersedia,
                    SUM(CASE WHEN a.status_ketersediaan = 'dipinjam' THEN 1 ELSE 0 END) as dipinjam,
                    SUM(CASE WHEN a.status_ketersediaan = 'maintenance' THEN 1 ELSE 0 END) as maintenance
                    $join_conditions $where_clause";
    $stats_result = executeQuery($stats_query);
    $stats = fetchArray($stats_result);
    ?>
    
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h4><?php echo $stats['total']; ?></h4>
                    <p class="mb-0">Total Alat</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h4><?php echo $stats['tersedia']; ?></h4>
                    <p class="mb-0">Tersedia</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-warning text-white">
                <div class="card-body">
                    <h4><?php echo $stats['dipinjam']; ?></h4>
                    <p class="mb-0">Dipinjam</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <h4><?php echo $stats['maintenance']; ?></h4>
                    <p class="mb-0">Maintenance</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Alat List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Daftar Alat
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
            <?php if (!empty($alat_list)): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <label class="me-2 text-muted small">Tampilkan:</label>
                    <form method="GET" class="d-inline">
                        <!-- Preserve all current filters -->
                        <?php if (!empty($cabang_filter)): ?>
                            <input type="hidden" name="cabang" value="<?php echo htmlspecialchars($cabang_filter); ?>">
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
            <?php if (!empty($alat_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Gambar</th>
                                <th>Nama Alat</th>
                                <th>Kode</th>
                                <?php if ($current_user['role'] == 'admin'): ?>
                                    <th>Cabang</th>
                                <?php endif; ?>
                                <th>Lokasi</th>
                                <th>Status</th>
                                <th>Kondisi</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($alat_list as $alat): ?>
                                <tr>
                                    <td>
                                        <?php if ($alat['gambar_alat'] && file_exists("../../uploads/alat/" . $alat['gambar_alat'])): ?>
                                            <img src="../../uploads/alat/<?php echo $alat['gambar_alat']; ?>" 
                                                 alt="<?php echo htmlspecialchars($alat['nama_alat']); ?>" 
                                                 class="img-thumbnail" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                 style="width: 50px; height: 50px;">
                                                <i class="fas fa-tools text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($alat['nama_alat']); ?></strong>
                                            <?php if ($alat['deskripsi_alat']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($alat['deskripsi_alat'], 0, 50)); ?><?php echo strlen($alat['deskripsi_alat']) > 50 ? '...' : ''; ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($alat['kode_alat']); ?></span>
                                    </td>
                                    
                                    <?php if ($current_user['role'] == 'admin'): ?>
                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($alat['nama_cabang']); ?></span></td>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <?php echo htmlspecialchars($alat['lokasi_simpan']); ?>
                                        <?php if ($alat['rak_lokasi']): ?>
                                            <br><small class="text-muted">Rak: <?php echo htmlspecialchars($alat['rak_lokasi']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $alat['badge_class']; ?>">
                                            <?php echo ucfirst($alat['status_ketersediaan']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo ($alat['kondisi_alat'] == 'baik') ? 'success' : (($alat['kondisi_alat'] == 'rusak') ? 'danger' : 'warning'); ?>">
                                            <?php echo ucfirst($alat['kondisi_alat']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail.php?id=<?php echo $alat['id_alat']; ?>" 
                                               class="btn btn-outline-primary" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (hasPermission('manage_tools')): ?>
                                                <a href="edit.php?id=<?php echo $alat['id_alat']; ?>" 
                                                   class="btn btn-outline-warning" title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                
                                                <?php if ($alat['status_ketersediaan'] == 'tersedia'): ?>
                                                    <a href="hapus.php?id=<?php echo $alat['id_alat']; ?>" 
                                                       class="btn btn-outline-danger" title="Hapus"
                                                       onclick="return confirmDelete('Apakah Anda yakin ingin menghapus alat ini?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php if ($alat['status_ketersediaan'] == 'tersedia' && hasPermission('request_peminjaman')): ?>
                                                <a href="../peminjaman/form-peminjaman.php?alat=<?php echo $alat['id_alat']; ?>" 
                                                   class="btn btn-outline-success" title="Pinjam">
                                                    <i class="fas fa-hand-holding"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php if ($total_records > $records_per_page): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted small">
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
                    <h5 class="text-muted">Tidak ada data alat</h5>
                    <p class="text-muted">
                        <?php if (hasPermission('manage_tools')): ?>
                            <a href="tambah.php" class="btn btn-primary mt-2">
                                <i class="fas fa-plus me-2"></i>Tambah Alat Pertama
                            </a>
                        <?php else: ?>
                            Data alat akan muncul di sini.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>