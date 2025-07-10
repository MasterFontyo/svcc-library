<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : null;
$student = null;
$borrowed_books = [];

if ($student_id) {
    // Fetch student
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();

    if ($student) {
        // Fetch borrowed books
        $stmt_borrow = $conn->prepare("
            SELECT bb.*, b.title, b.author
            FROM borrowed_books bb
            JOIN books b ON bb.book_id = b.book_id
            WHERE bb.student_id = ?
            ORDER BY bb.borrow_date DESC
        ");
        $stmt_borrow->bind_param("s", $student_id);
        $stmt_borrow->execute();
        $borrowed_books = $stmt_borrow->get_result();
    }
}
?>

<h2>Student Profile</h2>

<?php if (!$student): ?>
  <p>No student found with ID: <?= htmlspecialchars($student_id) ?></p>
<?php else: ?>
  <div style="display: flex; gap: 2rem;">
    <div>
      <p><strong>Student ID:</strong> <?= htmlspecialchars($student['student_id']) ?></p>
      <p><strong>Name:</strong> <?= htmlspecialchars($student['lastname']) ?>, <?= htmlspecialchars($student['firstname']) ?> <?= htmlspecialchars($student['middlename']) ?></p>
      <p><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></p>
      <p><strong>Year Level:</strong> <?= htmlspecialchars($student['year_level']) ?></p>
    </div>
    <div>
      <?php if (!empty($student['qr_path']) && file_exists('../' . $student['qr_path'])): ?>
        <img src="../<?= htmlspecialchars($student['qr_path']) ?>" alt="QR Code" width="100">
      <?php else: ?>
        <span>No QR code available</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- Print ID Card Button -->
  <a 
    href="print_lib_card.php?student_id=<?= urlencode($student['student_id']) ?>" 
    target="_blank" 
    class="btn btn-primary" 
    style="margin-bottom:16px;"
  >
    Print Library Card
  </a>

  
  <h3>Borrowed Books</h3>
  <table>
    <thead>
      <tr>
        <th>Title</th>
        <th>Author</th>
        <th>Borrowed On</th>
        <th>Returned On</th>
        <th>Status</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($borrowed_books->num_rows > 0): ?>
        <?php while ($row = $borrowed_books->fetch_assoc()): ?>
          <tr>
            <td><?= htmlspecialchars($row['title']) ?></td>
            <td><?= htmlspecialchars($row['author']) ?></td>
            <td><?= date('Y-m-d', strtotime($row['borrow_date'])) ?></td>
            <td><?= $row['return_date'] ? date('Y-m-d', strtotime($row['return_date'])) : '-' ?></td>
            <td><?= ucfirst($row['status']) ?></td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr><td colspan="5">No borrowed books.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>

  

  <?php
  // Fetch attendance records
  $stmt = $conn->prepare("SELECT time_in, time_out FROM attendance WHERE student_id=? ORDER BY time_in DESC");
  $stmt->bind_param("s", $student_id);
  $stmt->execute();
  $result = $stmt->get_result();
  echo "<h4>Library Time Records</h4><table><tr><th>Time In</th><th>Time Out</th></tr>";
  while($row = $result->fetch_assoc()) {
      echo "<tr><td>{$row['time_in']}</td><td>{$row['time_out']}</td></tr>";
  }
  echo "</table>";
  ?>

<?php endif; ?>

<?php include '../includes/footer.php'; ?>
