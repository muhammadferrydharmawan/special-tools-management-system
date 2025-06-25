<?php
/**
 * Logout Handler
 * Special Tools Management System
 * PT. Sumatera Motor Harley Indonesia
 */

require_once '../../config/session.php';

// Destroy session
destroyUserSession();

// Redirect to login page with logout message
header("Location: login.php?logout=1");
exit();
?>