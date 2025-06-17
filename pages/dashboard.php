<?php
session_start();
require_once '../includes/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

require_once '../includes/header.php';

// Fetch counts
$studentCount = $conn->query("SELECT COUNT(*) AS total FROM students")->fetch_assoc()['total'];
$bookCount = $conn->query("SELECT COUNT(*) AS total FROM books")->fetch_assoc()['total'];
$borrowedToday = $conn->query("SELECT COUNT(*) AS total FROM borrowed_books WHERE borrow_date = CURDATE()")->fetch_assoc()['total'];
$visitsToday = $conn->query("SELECT COUNT(*) AS total FROM library_log WHERE DATE(time_in) = CURDATE()")->fetch_assoc()['total'];
?>

<h1>Dashboard</h1>
<div class="card-grid">
  <div class="card">
    <h2><?= $studentCount ?></h2>
    <p>Registered Students</p>
  </div>
  <div class="card">
    <h2><?= $bookCount ?></h2>
    <p>Books in Inventory</p>
  </div>
  <div class="card">
    <h2><?= $borrowedToday ?></h2>
    <p>Books Borrowed Today</p>
  </div>
  <div class="card">
    <h2><?= $visitsToday ?></h2>
    <p>Student Visits Today</p>
  </div>
</div>

<?php require_once '../includes/footer.php'; ?>
