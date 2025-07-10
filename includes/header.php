<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Library System</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <!-- Select2 -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <!-- Chart.js for graphs -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <!-- Select2 JS -->
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Custom CSS - MUST BE LAST to override Bootstrap -->
  <link rel="stylesheet" href="../assets/css/style.css">
  
</head>
<body>

<div class="navbar">
  <div><strong>📚 SVCC Library System</strong></div>
  <div>
    <a href="../pages/dashboard.php">Dashboard</a>
    <a href="../pages/students.php">Users Records</a>
    <a href="../pages/books.php">Books</a>
    <a href="../pages/borrow.php">Borrow</a>
    <a href="../pages/return.php">Return</a>
    <a href="../pages/calendar.php">Calendar</a>
    <a href="../pages/scan.php" target="_blank">Scan</a>
    <a href="../pages/reports.php">Reports</a>
    &nbsp;|&nbsp;
    Welcome, <?= htmlspecialchars($_SESSION['username']) ?> |
    <a href="../logout.php">Logout</a>
  </div>
</div>

<div class="container">
