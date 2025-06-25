<?php
/**
 * Daftar Pengembalian
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Daftar Pengembalian';
require_once '../../includes/header.php';

// Check permission
if (!hasPermission('return_tools') && !hasPermission('approve_peminjaman')) {
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
$join_conditions = "FROM pengembalian pg 
                   JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
                   JOIN alat a ON p.id_alat = a.id_alat 
                   JOIN pengguna u ON p.id_pengguna = u.id_pengguna
                   JOIN pengguna admin ON pg.diterima_oleh = admin.id_pengguna
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
    $where_conditions[] = "pg.status_verifikasi = '" . escapeString($status_filter) . "'";
}

// Date range filter
if (!empty($start_date)) {
    $where_conditions[] = "pg.tanggal_kembali >= '" . escapeString($start_date) . "'";
}
if (!empty($end_date)) {
    $where_conditions[] = "pg.tanggal_kembali <= '" . escapeString($end_date) . "'";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Count total records
$count_query = "SELECT COUNT(*) as total $join_conditions $where_clause";
$count_result = executeQuery($count_query);
$total_records = fetchArray($count_result)['total'];

// Get pengembalian data
$query = "SELECT pg.*, p.tanggal_pinjam, p.tanggal_kembali_rencana, p.keperluan,
          a.nama_alat, a.kode_alat, u.nama_pengguna, c.nama_cabang,
          admin.nama_pengguna as nama_admin,
          CASE 
              WHEN pg.status_verifikasi = 'pending' THEN 'warning'
              WHEN pg.status_verifikasi = 'diterima' THEN 'success'
              WHEN pg.status_verifikasi = 'ditolak' THEN 'danger'
          END as badge_class,
          CASE 
              WHEN pg.kondisi_kembali = 'baik' THEN 'success'
              WHEN pg.kondisi_kembali = 'rusak' THEN 'warning'
              WHEN pg.kondisi_kembali = 'hilang' THEN 'danger'
          END as kondisi_class
          $join_conditions
          $where_clause
          ORDER BY pg.created_at DESC 
          LIMIT $offset, $records_per_page";

$result = executeQuery($query);
$pengembalian_list = array();
while ($row = fetchArray($result)) {
    $pengembalian_list[] = $row;
}

// Get status options for filter
$status_options = array(
    'all' => 'Semua Status',
    'pending' => 'Pending',
    'diterima' => 'Diterima',
    'ditolak' => 'Ditolak'
);

// Records per page options
$per_page_options = array(
    10 => '10',
    25 => '25',
    50 => '50',
    100 => '100'
);

// Get alat yang sedang dipinjam untuk quick return
$my_borrowed_tools = array();
if ($current_user['role'] == 'karyawan') {
    $borrowed_query = "SELECT p.id_peminjaman, a.nama_alat, a.kode_alat, p.tanggal_kembali_rencana
                      FROM peminjaman p 
                      JOIN alat a ON p.id_alat = a.id_alat
                      WHERE p.id_pengguna = '" . $current_user['user_id'] . "' 
                      AND p.status_peminjaman = 'disetujui'
                      AND p.id_peminjaman NOT IN (SELECT id_peminjaman FROM pengembalian)
                      ORDER BY p.tanggal_kembali_rencana ASC";
    
    $borrowed_result = executeQuery($borrowed_query);
    while ($row = fetchArray($borrowed_result)) {
        $my_borrowed_tools[] = $row;
    }
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="fas fa-undo me-2"></i><?php echo $page_title; ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Pengembalian</li>
                </ol>
            </nav>
        </div>
        
        <?php if (hasPermission('return_tools') && !empty($my_borrowed_tools)): ?>
        <div class="dropdown">
            <button class="btn btn-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="fas fa-undo me-2"></i>Kembalikan Alat
            </button>
            <ul class="dropdown-menu">
                <?php foreach ($my_borrowed_tools as $tool): ?>
                    <li>
                        <a class="dropdown-item" href="form-pengembalian.php?id=<?php echo $tool['id_peminjaman']; ?>">
                            <div>
                                <strong><?php echo htmlspecialchars($tool['nama_alat']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($tool['kode_alat']); ?></small>
                                <br><small class="text-danger">Kembali: <?php echo formatDate($tool['tanggal_kembali_rencana']); ?></small>
                            </div>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>

    <!-- My Borrowed Tools Alert (for karyawan) -->
    <?php if ($current_user['role'] == 'karyawan' && !empty($my_borrowed_tools)): ?>
    <div class="alert alert-info">
        <h6><i class="fas fa-info-circle me-2"></i>Alat yang Sedang Anda Pinjam</h6>
        <div class="row">
            <?php foreach ($my_borrowed_tools as $tool): ?>
                <div class="col-md-6 col-lg-4 mb-2">
                    <div class="border rounded p-2 bg-white">
                        <strong><?php echo htmlspecialchars($tool['nama_alat']); ?></strong><br>
                        <small class="text-muted"><?php echo htmlspecialchars($tool['kode_alat']); ?></small><br>
                        <small class="text-danger">Kembali: <?php echo formatDate($tool['tanggal_kembali_rencana']); ?></small><br>
                        <a href="form-pengembalian.php?id=<?php echo $tool['id_peminjaman']; ?>" 
                           class="btn btn-sm btn-outline-primary mt-1">
                            <i class="fas fa-undo me-1"></i>Kembalikan
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="status" class="form-label">Status Verifikasi</label>
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

    <!-- Quick Stats - Moved to Top -->
    <?php
    // Get quick stats for current filters
    $stats_query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN pg.status_verifikasi = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN pg.status_verifikasi = 'diterima' THEN 1 ELSE 0 END) as diterima,
                    SUM(CASE WHEN pg.status_verifikasi = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
                    SUM(CASE WHEN pg.kondisi_kembali = 'baik' THEN 1 ELSE 0 END) as baik,
                    SUM(CASE WHEN pg.kondisi_kembali = 'rusak' THEN 1 ELSE 0 END) as rusak,
                    SUM(CASE WHEN pg.kondisi_kembali = 'hilang' THEN 1 ELSE 0 END) as hilang
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
                    <p class="mb-0">Pending Verifikasi</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-success text-white">
                <div class="card-body">
                    <h4><?php echo $stats['diterima']; ?></h4>
                    <p class="mb-0">Diterima</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-info text-white">
                <div class="card-body">
                    <h4><?php echo $stats['baik']; ?></h4>
                    <p class="mb-0">Kondisi Baik</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center bg-danger text-white">
                <div class="card-body">
                    <h4><?php echo ($stats['rusak'] + $stats['hilang']); ?></h4>
                    <p class="mb-0">Rusak/Hilang</p>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Pengembalian List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-list me-2"></i>
                    Daftar Pengembalian
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
            <?php if (!empty($pengembalian_list)): ?>
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

            <?php if (!empty($pengembalian_list)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Tanggal Kembali</th>
                                <th>Alat</th>
                                <?php if ($current_user['role'] != 'karyawan'): ?>
                                    <th>Peminjam</th>
                                    <?php if ($current_user['role'] == 'admin'): ?>
                                        <th>Cabang</th>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <th>Kondisi</th>
                                <th>Status</th>
                                <th>Diterima Oleh</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pengembalian_list as $pengembalian): ?>
                                <tr>
                                    <td>
                                        <strong>#<?php echo str_pad($pengembalian['id_pengembalian'], 4, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td><?php echo formatDate($pengembalian['tanggal_kembali']); ?></td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($pengembalian['nama_alat']); ?></strong>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($pengembalian['kode_alat']); ?></small>
                                        </div>
                                    </td>
                                    
                                    <?php if ($current_user['role'] != 'karyawan'): ?>
                                        <td><?php echo htmlspecialchars($pengembalian['nama_pengguna']); ?></td>
                                        <?php if ($current_user['role'] == 'admin'): ?>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($pengembalian['nama_cabang']); ?></span></td>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <td>
                                        <span class="badge bg-<?php echo $pengembalian['kondisi_class']; ?>">
                                            <?php echo ucfirst($pengembalian['kondisi_kembali']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?php echo $pengembalian['badge_class']; ?>">
                                            <?php echo ucfirst($pengembalian['status_verifikasi']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($pengembalian['nama_admin']); ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="detail.php?id=<?php echo $pengembalian['id_pengembalian']; ?>" 
                                               class="btn btn-outline-primary" title="Detail">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            
                                            <?php if (hasPermission('approve_peminjaman') && $pengembalian['status_verifikasi'] == 'pending'): ?>
                                                <a href="terima.php?id=<?php echo $pengembalian['id_pengembalian']; ?>&action=approve" 
                                                   class="btn btn-outline-success" title="Terima"
                                                   onclick="return confirm('Terima pengembalian ini?')">
                                                    <i class="fas fa-check"></i>
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
                    <h5 class="text-muted">Tidak ada data pengembalian</h5>
                    <p class="text-muted">
                        <?php if (!empty($my_borrowed_tools)): ?>
                            Anda memiliki alat yang sedang dipinjam. Kembalikan untuk membuat record pengembalian.
                        <?php else: ?>
                            Data pengembalian akan muncul di sini setelah ada alat yang dikembalikan.
                        <?php endif; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>