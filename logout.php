<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/activity_logger.php';

// Log logout activity before destroying session
if (isset($_SESSION['user_id']) && isset($_SESSION['username'])) {
    logLogout($conn, $_SESSION['user_id'], $_SESSION['username']);
}

session_unset();
session_destroy();
header("Location: index.php");
exit();