<?php
/**
 * Dashboard Laporan
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

$page_title = 'Laporan';
require_once '../../includes/header.php';

// Check permission
if (!hasPermission('view_reports')) {
    header("Location: ../dashboard/");
    exit();
}

$current_user = getUserInfo();
$user_cabang = getUserCabang();

// Date range for default reports (last 30 days)
$end_date = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));

// Get statistics based on role
if ($current_user['role'] == 'admin') {
    // Manajer can see all branches
    $stats_medan = getDashboardStats(1);
    $stats_batam = getDashboardStats(2);
    $stats_total = getDashboardStats();
    
    $cabang_filter = "";
} else {
    // Admin only sees their branch
    $stats = getDashboardStats($user_cabang);
    $cabang_filter = " AND a.id_cabang = '$user_cabang'";
}

// Get peminjaman statistics for the period
$peminjaman_stats_query = "SELECT 
    COUNT(*) as total_peminjaman,
    SUM(CASE WHEN p.status_peminjaman = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN p.status_peminjaman = 'disetujui' THEN 1 ELSE 0 END) as disetujui,
    SUM(CASE WHEN p.status_peminjaman = 'ditolak' THEN 1 ELSE 0 END) as ditolak,
    SUM(CASE WHEN p.status_peminjaman = 'selesai' THEN 1 ELSE 0 END) as selesai
    FROM peminjaman p 
    JOIN alat a ON p.id_alat = a.id_alat
    WHERE p.created_at BETWEEN '$start_date' AND '$end_date 23:59:59' $cabang_filter";

$peminjaman_result = executeQuery($peminjaman_stats_query);
$peminjaman_stats = fetchArray($peminjaman_result);

// Get pengembalian statistics
$pengembalian_stats_query = "SELECT 
    COUNT(*) as total_pengembalian,
    SUM(CASE WHEN pg.kondisi_kembali = 'baik' THEN 1 ELSE 0 END) as kondisi_baik,
    SUM(CASE WHEN pg.kondisi_kembali = 'rusak' THEN 1 ELSE 0 END) as kondisi_rusak,
    SUM(CASE WHEN pg.kondisi_kembali = 'hilang' THEN 1 ELSE 0 END) as kondisi_hilang,
    SUM(CASE WHEN pg.status_verifikasi = 'pending' THEN 1 ELSE 0 END) as pending_verifikasi
    FROM pengembalian pg
    JOIN peminjaman p ON pg.id_peminjaman = p.id_peminjaman
    JOIN alat a ON p.id_alat = a.id_alat
    WHERE pg.created_at BETWEEN '$start_date' AND '$end_date 23:59:59' $cabang_filter";

$pengembalian_result = executeQuery($pengembalian_stats_query);
$pengembalian_stats = fetchArray($pengembalian_result);

// Get top borrowed tools
$top_tools_query = "SELECT a.nama_alat, a.kode_alat, COUNT(*) as jumlah_peminjaman, c.nama_cabang
                   FROM peminjaman p 
                   JOIN alat a ON p.id_alat = a.id_alat
                   JOIN cabang c ON a.id_cabang = c.id_cabang
                   WHERE p.created_at BETWEEN '$start_date' AND '$end_date 23:59:59' $cabang_filter
                   GROUP BY a.id_alat
                   ORDER BY jumlah_peminjaman DESC
                   LIMIT 5";

$top_tools_result = executeQuery($top_tools_query);
$top_tools = array();
while ($row = fetchArray($top_tools_result)) {
    $top_tools[] = $row;
}

// Get top borrowers
$top_borrowers_query = "SELECT u.nama_pengguna, u.jabatan, COUNT(*) as jumlah_peminjaman, d.nama_departemen
                       FROM peminjaman p 
                       JOIN pengguna u ON p.id_pengguna = u.id_pengguna
                       JOIN departemen d ON u.id_departemen = d.id_departemen
                       JOIN alat a ON p.id_alat = a.id_alat
                       WHERE p.created_at BETWEEN '$start_date' AND '$end_date 23:59:59' $cabang_filter
                       GROUP BY u.id_pengguna
                       ORDER BY jumlah_peminjaman DESC
                       LIMIT 5";

$top_borrowers_result = executeQuery($top_borrowers_query);
$top_borrowers = array();
while ($row = fetchArray($top_borrowers_result)) {
    $top_borrowers[] = $row;
}

// Get daily statistics for chart
$daily_stats_query = "SELECT 
    DATE(p.created_at) as tanggal,
    COUNT(*) as jumlah_peminjaman
    FROM peminjaman p 
    JOIN alat a ON p.id_alat = a.id_alat
    WHERE p.created_at BETWEEN '$start_date' AND '$end_date 23:59:59' $cabang_filter
    GROUP BY DATE(p.created_at)
    ORDER BY tanggal";

$daily_stats_result = executeQuery($daily_stats_query);
$daily_stats = array();
while ($row = fetchArray($daily_stats_result)) {
    $daily_stats[] = $row;
}
?>

<div class="container-fluid">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4><i class="fas fa-chart-bar me-2"></i><?php echo $page_title; ?></h4>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="../dashboard/">Dashboard</a></li>
                    <li class="breadcrumb-item active">Laporan</li>
                </ol>
            </nav>
        </div>
        
        <div class="btn-group">
            <button type="button" class="btn btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-download me-2"></i>Export
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="export.php?type=pdf&period=30"><i class="fas fa-file-pdf me-2"></i>PDF (30 hari)</a></li>
            </ul>
        </div>
    </div>

    <!-- Period Info -->
    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Periode Laporan:</strong> <?php echo formatDate($start_date); ?> s/d <?php echo formatDate($end_date); ?> 
        (30 hari terakhir)
        <?php if ($current_user['role'] == 'manajer'): ?>
            | <strong>Cabang:</strong> <?php echo getCabangName($user_cabang); ?>
        <?php endif; ?>
    </div>

    <!-- Summary Statistics -->
    <?php if ($current_user['role'] == 'admin'): ?>
        <!-- Multi-Branch Summary for Manajer -->
        <div class="row mb-4">
            <div class="col-12">
                <h5><i class="fas fa-building me-2"></i>Ringkasan Multi-Cabang</h5>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h4><?php echo $stats_total['total_alat']; ?></h4>
                        <p class="mb-0">Total Alat</p>
                        <small>Medan: <?php echo $stats_medan['total_alat']; ?> | Batam: <?php echo $stats_batam['total_alat']; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h4><?php echo $stats_total['alat_tersedia']; ?></h4>
                        <p class="mb-0">Alat Tersedia</p>
                        <small>Medan: <?php echo $stats_medan['alat_tersedia']; ?> | Batam: <?php echo $stats_batam['alat_tersedia']; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h4><?php echo $stats_total['alat_dipinjam']; ?></h4>
                        <p class="mb-0">Alat Dipinjam</p>
                        <small>Medan: <?php echo $stats_medan['alat_dipinjam']; ?> | Batam: <?php echo $stats_batam['alat_dipinjam']; ?></small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h4><?php echo $peminjaman_stats['total_peminjaman']; ?></h4>
                        <p class="mb-0">Total Peminjaman</p>
                        <small>Periode 30 hari</small>
                    </div>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Single Branch Summary for Admin -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center bg-primary text-white">
                    <div class="card-body">
                        <h4><?php echo $stats['total_alat']; ?></h4>
                        <p class="mb-0">Total Alat</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-success text-white">
                    <div class="card-body">
                        <h4><?php echo $stats['alat_tersedia']; ?></h4>
                        <p class="mb-0">Alat Tersedia</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-warning text-white">
                    <div class="card-body">
                        <h4><?php echo $stats['alat_dipinjam']; ?></h4>
                        <p class="mb-0">Alat Dipinjam</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center bg-info text-white">
                    <div class="card-body">
                        <h4><?php echo $peminjaman_stats['total_peminjaman']; ?></h4>
                        <p class="mb-0">Total Peminjaman</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Detailed Statistics -->
    <div class="row mb-4">
        <!-- Peminjaman Statistics -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-hand-holding me-2"></i>Statistik Peminjaman</h6>
                </div>
                <div class="card-body">
                    <canvas id="peminjamanChart" width="400" height="200"></canvas>
                    
                    <div class="row text-center mt-3">
                        <div class="col-3">
                            <h6 class="text-warning"><?php echo $peminjaman_stats['pending']; ?></h6>
                            <small>Pending</small>
                        </div>
                        <div class="col-3">
                            <h6 class="text-success"><?php echo $peminjaman_stats['disetujui']; ?></h6>
                            <small>Disetujui</small>
                        </div>
                        <div class="col-3">
                            <h6 class="text-danger"><?php echo $peminjaman_stats['ditolak']; ?></h6>
                            <small>Ditolak</small>
                        </div>
                        <div class="col-3">
                            <h6 class="text-info"><?php echo $peminjaman_stats['selesai']; ?></h6>
                            <small>Selesai</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pengembalian Statistics -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-undo me-2"></i>Statistik Pengembalian</h6>
                </div>
                <div class="card-body">
                    <canvas id="pengembalianChart" width="400" height="200"></canvas>
                    
                    <div class="row text-center mt-3">
                        <div class="col-4">
                            <h6 class="text-success"><?php echo $pengembalian_stats['kondisi_baik']; ?></h6>
                            <small>Kondisi Baik</small>
                        </div>
                        <div class="col-4">
                            <h6 class="text-warning"><?php echo $pengembalian_stats['kondisi_rusak']; ?></h6>
                            <small>Rusak</small>
                        </div>
                        <div class="col-4">
                            <h6 class="text-danger"><?php echo $pengembalian_stats['kondisi_hilang']; ?></h6>
                            <small>Hilang</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Lists -->
    <div class="row mb-4">
        <!-- Top Tools -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Alat Paling Sering Dipinjam</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_tools)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_tools as $index => $tool): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($tool['nama_alat']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo htmlspecialchars($tool['kode_alat']); ?>
                                                    <?php if ($current_user['role'] == 'admin'): ?>
                                                        | <?php echo htmlspecialchars($tool['nama_cabang']); ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge bg-success"><?php echo $tool['jumlah_peminjaman']; ?>x</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Tidak ada data peminjaman</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Top Borrowers -->
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>Peminjam Paling Aktif</h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($top_borrowers)): ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($top_borrowers as $index => $borrower): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <div>
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-primary me-2"><?php echo $index + 1; ?></span>
                                            <div>
                                                <h6 class="mb-0"><?php echo htmlspecialchars($borrower['nama_pengguna']); ?></h6>
                                                <small class="text-muted">
                                                    <?php echo ucfirst($borrower['jabatan']); ?> - 
                                                    <?php echo htmlspecialchars($borrower['nama_departemen']); ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                    <span class="badge bg-info"><?php echo $borrower['jumlah_peminjaman']; ?>x</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted text-center">Tidak ada data peminjaman</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Trend Chart -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Tren Peminjaman Harian</h6>
                </div>
                <div class="card-body">
                    <canvas id="dailyTrendChart" width="400" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Report Links -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-link me-2"></i>Laporan Lainnya</h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <a href="peminjaman.php" class="btn btn-outline-primary w-100 mb-2">
                                <i class="fas fa-hand-holding me-2"></i>Laporan Peminjaman
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="pengembalian.php" class="btn btn-outline-success w-100 mb-2">
                                <i class="fas fa-undo me-2"></i>Laporan Pengembalian
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="inventori.php" class="btn btn-outline-info w-100 mb-2">
                                <i class="fas fa-tools me-2"></i>Laporan Inventori
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="maintenance.php" class="btn btn-outline-warning w-100 mb-2">
                                <i class="fas fa-wrench me-2"></i>Laporan Maintenance
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/3.9.1/chart.min.js"></script>

<script>
// Peminjaman Chart
const peminjamanCtx = document.getElementById('peminjamanChart').getContext('2d');
new Chart(peminjamanCtx, {
    type: 'doughnut',
    data: {
        labels: ['Pending', 'Disetujui', 'Ditolak', 'Selesai'],
        datasets: [{
            data: [
                <?php echo $peminjaman_stats['pending']; ?>,
                <?php echo $peminjaman_stats['disetujui']; ?>,
                <?php echo $peminjaman_stats['ditolak']; ?>,
                <?php echo $peminjaman_stats['selesai']; ?>
            ],
            backgroundColor: ['#ffc107', '#198754', '#dc3545', '#0dcaf0']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Pengembalian Chart
const pengembalianCtx = document.getElementById('pengembalianChart').getContext('2d');
new Chart(pengembalianCtx, {
    type: 'doughnut',
    data: {
        labels: ['Kondisi Baik', 'Rusak', 'Hilang'],
        datasets: [{
            data: [
                <?php echo $pengembalian_stats['kondisi_baik']; ?>,
                <?php echo $pengembalian_stats['kondisi_rusak']; ?>,
                <?php echo $pengembalian_stats['kondisi_hilang']; ?>
            ],
            backgroundColor: ['#198754', '#ffc107', '#dc3545']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Daily Trend Chart
const dailyTrendCtx = document.getElementById('dailyTrendChart').getContext('2d');
new Chart(dailyTrendCtx, {
    type: 'line',
    data: {
        labels: [
            <?php foreach ($daily_stats as $stat): ?>
                '<?php echo date('d/m', strtotime($stat['tanggal'])); ?>',
            <?php endforeach; ?>
        ],
        datasets: [{
            label: 'Jumlah Peminjaman',
            data: [
                <?php foreach ($daily_stats as $stat): ?>
                    <?php echo $stat['jumlah_peminjaman']; ?>,
                <?php endforeach; ?>
            ],
            borderColor: '#0d6efd',
            backgroundColor: 'rgba(13, 110, 253, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    precision: 0
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>