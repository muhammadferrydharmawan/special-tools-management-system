<?php
/**
 * Login Page - PHP Version Compatible
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

session_start();

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard/");
    exit();
}

require_once '../../config/database.php';
require_once '../../config/session.php';

$error_message = '';
$success_message = '';

// Helper function for PHP version compatibility
function starts_with($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = sanitizeInput($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error_message = 'Username dan password harus diisi!';
    } else {
        // Use prepared statement for security
        $query = "SELECT p.*, d.nama_departemen, c.nama_cabang 
                  FROM pengguna p 
                  LEFT JOIN departemen d ON p.id_departemen = d.id_departemen 
                  LEFT JOIN cabang c ON p.id_cabang = c.id_cabang 
                  WHERE p.username = ? AND p.status = 'aktif'";
        
        $result = executeSecureQuery($query, [$username]);
        
        if (countRows($result) > 0) {
            $user = fetchArray($result);
            $stored_password = $user['password'];
            $password_valid = false;
            
            // IMPROVED: Multiple password verification methods (PHP version compatible)
            
            // Method 1: Check if it's PHP password_hash format (bcrypt)
            if (strlen($stored_password) >= 60 && 
                (starts_with($stored_password, '$2y$') || 
                 starts_with($stored_password, '$2a$') || 
                 starts_with($stored_password, '$2x$'))) {
                
                if (function_exists('password_verify')) {
                    $password_valid = password_verify($password, $stored_password);
                }
            }
            
            // Method 2: Check if it's MD5 (32 characters)
            elseif (strlen($stored_password) == 32) {
                $password_valid = (md5($password) === $stored_password);
            }
            
            // Method 3: Check if it's SHA1 (40 characters)
            elseif (strlen($stored_password) == 40) {
                $password_valid = (sha1($password) === $stored_password);
            }
            
            // Method 4: Check plain text (NOT RECOMMENDED)
            elseif ($stored_password === $password) {
                $password_valid = true;
                // Log this for security audit
                error_log("WARNING: Plain text password detected for user: " . $username);
            }
            
            // Method 5: Try legacy password_verify for any other bcrypt variants
            elseif (function_exists('password_verify')) {
                $password_valid = password_verify($password, $stored_password);
            }
            
            if ($password_valid) {
                // Set session data
                setUserSession($user);
                
                // Redirect ke dashboard utama untuk semua jabatan
header("Location: ../dashboard/");
exit();
            } else {
                $error_message = 'Password salah!';
            }
        } else {
            $error_message = 'Username tidak ditemukan atau akun tidak aktif!';
        }
    }
}

// Check for timeout message
if (isset($_GET['timeout'])) {
    $error_message = 'Sesi Anda telah berakhir. Silakan login kembali.';
}

// Check for logout message
if (isset($_GET['logout'])) {
    $success_message = 'Anda telah berhasil logout.';
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Special Tools Management | PT. Sumatera Motor Harley Indonesia</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #ff6600;
            --secondary-color: #000000;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body, html {
            height: 100%;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            overflow: hidden;
        }
        
        .login-wrapper {
            display: flex;
            height: 100vh;
        }
        
        /* Background Section - Kiri */
        .background-section {
            flex: 1;
            background-image: url('../../assets/images/bg.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        
        .background-content {
            position: relative;
            z-index: 2;
            text-align: center;
            color: white;
            padding: 2rem;
        }
        
        .background-content h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        .background-content p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
        }
        
        .background-content .logo {
            font-size: 4rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
        }
        
        /* Form Section - Kanan */
        .form-section {
            flex: 0 0 450px;
            background: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 2rem 2rem 2rem;
            box-shadow: -5px 0 20px rgba(0,0,0,0.1);
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
            background: transparent;
            color: #000000;
            padding: 2rem 1.5rem;
            border-radius: 15px;
            margin: 0 -1rem 2rem -1rem;
        }
        
        .login-header .logo-icon {
            font-size: 3rem;
            color: #d2691e;
            margin-bottom: 1rem;
        }
        
        .login-header h2 {
            color: #000000;
            font-weight: 700;
            font-size: 1.8rem;
            margin-bottom: 0.5rem;
        }
        
        .login-header .system-name {
            color: #666666;
            font-size: 1rem;
            margin-bottom: 0.5rem;
            font-weight: 400;
        }
        
        .login-header .company-name {
            color: #333333;
            font-size: 0.95rem;
            font-weight: 600;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 0.75rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.2rem rgba(255, 102, 0, 0.25);
        }
        
        .btn-login {
            background: linear-gradient(135deg, var(--primary-color), #ff8533);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            width: 100%;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 102, 0, 0.4);
        }
        
        .input-group-text {
            border-radius: 10px 0 0 10px;
            border: 2px solid #e9ecef;
            border-right: none;
            background: #f8f9fa;
        }
        
        .input-group .form-control {
            border-radius: 0 10px 10px 0;
            border-left: none;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .login-wrapper {
                flex-direction: column;
            }
            
            .background-section {
                flex: 0 0 30%;
            }
            
            .background-content h1 {
                font-size: 2rem;
            }
            
            .background-content .logo {
                font-size: 2.5rem;
            }
            
            .form-section {
                flex: 1;
                padding: 2rem 1.5rem 1.5rem 1.5rem;
            }
            
            .login-header {
                margin: 0 -0.5rem 2rem -0.5rem;
                padding: 1.5rem 1rem;
            }
            
            .login-header h2 {
                font-size: 1.5rem;
            }
        }
        
        @media (max-width: 576px) {
            .form-section {
                padding: 1.5rem 1rem 1rem 1rem;
            }
            
            .background-section {
                flex: 0 0 25%;
            }
            
            .background-content {
                padding: 1rem;
            }
            
            .background-content h1 {
                font-size: 1.5rem;
            }
            
            .background-content p {
                font-size: 1rem;
            }
            
            .login-header {
                margin: 0 -0.5rem 1.5rem -0.5rem;
                padding: 1.2rem 0.8rem;
            }
            
            .login-header h2 {
                font-size: 1.3rem;
            }
            
            .login-header .system-name {
                font-size: 0.9rem;
            }
            
            .login-header .company-name {
                font-size: 0.85rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <!-- Background Section - Kiri -->
        <div class="background-section">
            <div class="background-content">
            </div>
        </div>
        
        <!-- Form Section - Kanan -->
        <div class="form-section">
            <div class="login-container">
                <div class="login-header">
                    <div class="logo-icon">
                        <i class="fas fa-tools mb-3"></i>
                    </div>
                    <h2>Special Tools Management</h2>
                    <div class="system-name">Sistem Manajemen Alat Khusus</div>
                    <div class="company-name">PT. Sumatera Motor Harley Indonesia</div>
                </div>
                
                <?php if (!empty($error_message)): ?>
                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-3">
                        <label for="username" class="form-label">Username</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-user"></i>
                            </span>
                            <input type="text" class="form-control" id="username" name="username" 
                                   placeholder="Masukkan username" required 
                                   value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label">Password</label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="fas fa-lock"></i>
                            </span>
                            <input type="password" class="form-control" id="password" name="password" 
                                   placeholder="Masukkan password" required>
                            <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="fas fa-sign-in-alt me-2"></i>Masuk
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });
        
        // Auto focus on username
        document.getElementById('username').focus();
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 5000);
        
        // Add smooth animations
        document.addEventListener('DOMContentLoaded', function() {
            const formSection = document.querySelector('.form-section');
            const backgroundSection = document.querySelector('.background-section');
            
            formSection.style.opacity = '0';
            formSection.style.transform = 'translateX(50px)';
            
            backgroundSection.style.opacity = '0';
            backgroundSection.style.transform = 'translateX(-50px)';
            
            setTimeout(() => {
                formSection.style.transition = 'all 0.8s ease';
                backgroundSection.style.transition = 'all 0.8s ease';
                
                formSection.style.opacity = '1';
                formSection.style.transform = 'translateX(0)';
                
                backgroundSection.style.opacity = '1';
                backgroundSection.style.transform = 'translateX(0)';
            }, 100);
        });
    </script>
</body>
</html>