<?php
/**
 * Main Landing Page
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

session_start();

// Redirect to appropriate page based on login status
if (isset($_SESSION['user_id'])) {
    // User is logged in, redirect to dashboard
    header("Location: modules/dashboard/");
    exit();
} else {
    // User is not logged in, redirect to login page
    header("Location: modules/auth/login.php");
    exit();
}
?>