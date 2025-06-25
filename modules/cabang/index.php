<?php
/**
 * Daftar Cabang
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Data Cabang';
require_once '../../includes/header.php';

// Check permission - only admin can manage cabang
if ($current_user['role'] != 'admin') {
    header("Location: ../dashboard/");
    exit();
}

// Get filter parameters
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

// Build query based on filters
$where_conditions = array();

// Status filter
if (!empty($status_filter) && $status_filter != 'all') {
    $where_conditions[] = "c.status = '" . escapeString($status_filter) . "'";
}

// Search filter
if (!empty($search)) {
    $search_escaped = escapeString($search);
    $where_conditions[] = "(c.nama_cabang LIKE '%$search_escaped%' OR c.kode_cabang LIKE '%$search_escaped%' OR c.kota LIKE '%$search_escaped%' OR c.kepala_cabang LIKE '%$search_escaped%')";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total FROM cabang c $where_clause";
$count_result = executeQuery($count_query);
$total_records = fetchArray($count_result)['total'];

// Get cabang data
$query = "SELECT c.*,
          CASE 
              WHEN c.status = 'aktif' THEN 'success'
              WHEN c.status = 'nonaktif' THEN 'danger'
          END as badge_class,
          (SELECT COUNT(*) FROM pengguna p WHERE p.id_cabang = c.id_cabang AND p.status = 'aktif') as total_pengguna,
          (SELECT COUNT(*) FROM alat a WHERE a.id_cabang = c.id_cabang) as total_alat
          FROM cabang c
          $where_clause
          ORDER BY c.created_at DESC 
          LIMIT $offset, $records_per_page";

$result = executeQuery($query);
$cabang_list = array();
while ($row = fetchArray($result)) {
    $cabang_list[] = $row;
}

// Status options
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
            <h4><i class="fas fa-building me-2"></i><?php echo $page_title; ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Data Cabang</li>
                </ol>
            </nav>
        </div>
        
        <div class="btn-group">
            <a href="tambah.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Tambah Cabang
            </a>
        </div>
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
                
                <div class="col-md-6">
                    <label for="search" class="form-label">Pencarian</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Cari nama cabang, kode, kota, atau kepala cabang..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-3">
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
                    SUM(CASE WHEN c.status = 'aktif' THEN 1 ELSE 0 END) as aktif,
                    SUM(CASE WHEN c.status = 'nonaktif' THEN 1 ELSE 0 END) as nonaktif
                    FROM cabang c $where_clause";
    $stats_result = executeQuery($stats_query);
    $stats = fetchArray($stats_result);
    ?>
    
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card text-center bg-primary text-white">
                <div class="card-body">
                    <h4><?php echo $stats['total']; ?></h4>
                    <p class="mb-0">Total Cabang</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h4><?php echo $stats['aktif']; ?></h4>
                    <p class="mb-0">Cabang Aktif</p>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <h4><?php echo $stats['nonaktif']; ?></h4>
                    <p class="mb-0">Cabang Non-Aktif</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Cabang List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Daftar Cabang
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
            <?php if (!empty($cabang_list)): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="d-flex align-items-center">
                    <label class="me-2 text-muted small">Tampilkan:</label>
                    <form method="GET" class="d-inline">
                        <!-- Preserve all current filters -->
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

            <?php if (!empty($cabang_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Cabang</th>
                                <th>Alamat & Kontak</th>
                                <th>Kepala Cabang</th>
                                <th>Statistik</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cabang_list as $cabang): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($cabang['nama_cabang']); ?></strong>
                                            <br>
                                            <span class="badge bg-secondary"><?php echo htmlspecialchars($cabang['kode_cabang']); ?></span>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-map-marker-alt me-1"></i>
                                                <?php echo htmlspecialchars($cabang['kota']); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <i class="fas fa-map me-1"></i>
                                            <?php echo htmlspecialchars($cabang['alamat']); ?>
                                            <br>
                                            <?php if (!empty($cabang['telepon'])): ?>
                                                <i class="fas fa-phone me-1"></i>
                                                <?php echo htmlspecialchars($cabang['telepon']); ?>
                                                <br>
                                            <?php endif; ?>
                                            <?php if (!empty($cabang['email'])): ?>
                                                <i class="fas fa-envelope me-1"></i>
                                                <?php echo htmlspecialchars($cabang['email']); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if (!empty($cabang['kepala_cabang'])): ?>
                                            <strong><?php echo htmlspecialchars($cabang['kepala_cabang']); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <i class="fas fa-users me-1"></i>
                                            <?php echo $cabang['total_pengguna']; ?> Pengguna
                                            <br>
                                            <i class="fas fa-tools me-1"></i>
                                            <?php echo $cabang['total_alat']; ?> Alat
                                        </small>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $cabang['badge_class']; ?>">
                                            <?php echo ucfirst($cabang['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail.php?id=<?php echo $cabang['id_cabang']; ?>" 
                                               class="btn btn-outline-primary" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <a href="edit.php?id=<?php echo $cabang['id_cabang']; ?>" 
                                               class="btn btn-outline-warning" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
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
                    <i class="fas fa-building fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">Tidak ada data cabang</h5>
                    <p class="text-muted">
                        <a href="tambah.php" class="btn btn-primary mt-2">
                            <i class="fas fa-plus me-2"></i>Tambah Cabang Pertama
                        </a>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>