<?php
ob_start();
require_once '../../config/session.php';
require_once '/functions.php';

// Check if user is logged in
checkLogin();

$current_user = getUserInfo();
$cabang_name = getCabangName($current_user['id_cabang']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Special Tools Management | PT. Sumatera Motor Harley Indonesia</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #ff6600;
            --secondary-color: #000000;
            --accent-color: #f8f9fa;
            --text-color: #333333;
        }
        
        body {
            background-color: var(--accent-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .navbar-brand img {
            height: 40px;
        }
        
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #ff8533);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .sidebar {
            min-height: calc(100vh - 56px);
            background: var(--secondary-color);
            color: white;
        }
        
        .sidebar .nav-link {
            color: #ccc;
            padding: 0.75rem 1.25rem;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            padding: 20px;
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #ff8533);
            color: white;
            border-radius: 10px 10px 0 0 !important;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .btn-primary:hover {
            background-color: #e55a00;
            border-color: #e55a00;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
        }
        
        .badge-status {
            font-size: 0.85em;
        }
        
        .stats-card {
            border-left: 4px solid var(--primary-color);
        }

                /* Sidebar Logo */
        .sidebar-header {
            padding: 0rem 0rem;
            text-align: center;
            border-bottom: 1px solid #333;
            margin-bottom: 1rem;
        }
        
        .sidebar-logo {
            max-height: 150px;
            width: auto;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                top: 56px;
                left: -100%;
                width: 250px;
                height: calc(100vh - 56px);
                z-index: 1000;
                transition: left 0.3s ease;
            }
            
            .sidebar.show {
                left: 0;
            }
            
            .sidebar-overlay {
                position: fixed;
                top: 56px;
                left: 0;
                width: 100%;
                height: calc(100vh - 56px);
                background: rgba(0,0,0,0.5);
                z-index: 999;
                display: none;
            }
            
            .sidebar-overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <button class="navbar-toggler d-lg-none" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <a class="navbar-brand" href="../dashboard/">
                <i class="fas fa-tools me-2"></i>
                Special Tools Management
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        <?php echo htmlspecialchars($current_user['nama_pengguna']); ?>
                        <small class="text-light opacity-75 ms-1">(<?php echo $cabang_name; ?>)</small>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><h6 class="dropdown-header">
                            <div><?php echo htmlspecialchars($current_user['nama_pengguna']); ?></div>
                            <small class="text-muted"><?php echo ucfirst($current_user['role']); ?> - <?php echo $cabang_name; ?></small>
                        </h6></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../profile/"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="../auth/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Overlay for Mobile -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-lg-2 d-lg-block sidebar" id="sidebar">
                <!-- Sidebar Header dengan Logo (Opsional) -->
                <div class="sidebar-header">
                    <img src="../../assets/images/logo.png" alt="Logo" class="sidebar-logo">
                </div>

                <div class="py-3">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/dashboard/') !== false) ? 'active' : ''; ?>" href="../dashboard/">
                                <i class="fas fa-tachometer-alt"></i>Dashboard
                            </a>
                        </li>
                        
                        <?php if (hasPermission('request_peminjaman')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/peminjaman/') !== false) ? 'active' : ''; ?>" href="../peminjaman/">
                                <i class="fas fa-hand-holding"></i>Peminjaman
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('return_tools')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/pengembalian/') !== false) ? 'active' : ''; ?>" href="../pengembalian/">
                                <i class="fas fa-undo"></i>Pengembalian
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('manage_tools')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/alat/') !== false) ? 'active' : ''; ?>" href="../alat/">
                                <i class="fas fa-tools"></i>Data Alat
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('manage_users')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/pengguna/') !== false) ? 'active' : ''; ?>" href="../pengguna/">
                                <i class="fas fa-users"></i>Data Pengguna
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if ($current_user['role'] == 'admin'): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/cabang/') !== false) ? 'active' : ''; ?>" href="../cabang/">
                                <i class="fas fa-building"></i>Data Cabang
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <?php if (hasPermission('view_reports')): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo (strpos($_SERVER['REQUEST_URI'], '/laporan/') !== false) ? 'active' : ''; ?>" href="../laporan/">
                                <i class="fas fa-chart-bar"></i>Laporan
                            </a>
                        </li>
                        <?php endif; ?>
                        
                        <hr class="my-3" style="border-color: #555;">
                        
                        <li class="nav-item">
                            <a class="nav-link" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i>Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>

            <!-- Main Content -->
            <main class="col-lg-10 ms-sm-auto main-content">
                <?php if (isset($_SESSION['message'])): ?>
                    <?php echo showAlert($_SESSION['message'], $_SESSION['message_type']); ?>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>