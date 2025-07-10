<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

$book_id = isset($_GET['book_id']) ? intval($_GET['book_id']) : 0;
$book = null;
$borrowers = [];

if ($book_id) {
    // Fetch book details
    $stmt = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $stmt->bind_param("i", $book_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $book = $result->fetch_assoc();

    // Fetch borrowers
    $stmt_borrowers = $conn->prepare("
        SELECT bb.*, s.lastname, s.firstname, s.middlename
        FROM borrowed_books bb
        JOIN students s ON bb.student_id = s.student_id
        WHERE bb.book_id = ?
        ORDER BY bb.borrow_date DESC
    ");
    $stmt_borrowers->bind_param("i", $book_id);
    $stmt_borrowers->execute();
    $borrowers = $stmt_borrowers->get_result();
}
?>

<h2>Book Profile</h2>

<?php if (!$book): ?>
    <div class="alert alert-error">Book not found.</div>
    <a href="books.php" class="btn btn-secondary">Back to Books</a>
<?php else: ?>
    <div style="background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 2rem;">
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
            <div>
                <h3 style="color: #800000; margin-bottom: 1rem;">Book Details</h3>
                <p><strong>ISBN:</strong> <?= htmlspecialchars($book['isbn'] ?? 'N/A') ?></p>
                <p><strong>Control Number:</strong> <?= htmlspecialchars($book['ctrl_number'] ?? 'N/A') ?></p>
                <p><strong>Title:</strong> <?= htmlspecialchars($book['title']) ?></p>
                <p><strong>Author:</strong> <?= htmlspecialchars($book['author'] ?? 'N/A') ?></p>
                <p><strong>Publisher:</strong> <?= htmlspecialchars($book['publisher'] ?? 'N/A') ?></p>
            </div>
            <div>
                <h3 style="color: #800000; margin-bottom: 1rem;">Publication Info</h3>
                <p><strong>Year Published:</strong> <?= htmlspecialchars($book['year_published'] ?? 'N/A') ?></p>
                <p><strong>Copyright Year:</strong> <?= htmlspecialchars($book['copyright'] ?? 'N/A') ?></p>
                <p><strong>Status:</strong> 
                    <span style="color: <?= $book['available_copies'] > 0 ? 'green' : 'red' ?>;">
                        <?= htmlspecialchars($book['status']) ?>
                    </span>
                </p>
                <p><strong>Available Copies:</strong> <?= $book['available_copies'] ?></p>
                <p><strong>Total Copies:</strong> <?= $book['total_copies'] ?></p>
            </div>
        </div>
        
        <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #eee;">
            <a href="books.php" class="btn btn-secondary">Back to Books</a>
            <button class="btn btn-primary" onclick='openEditBookModal(<?= json_encode($book) ?>)'>Edit Book</button>
        </div>
    </div>

    <div style="background: #fff; padding: 2rem; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
        <h3 style="color: #800000; margin-bottom: 1rem;">Borrowing History</h3>
        <table>
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Student ID</th>
                    <th>Borrowed On</th>
                    <th>Returned On</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($borrowers->num_rows > 0): ?>
                    <?php while ($row = $borrowers->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname'] . ' ' . $row['middlename']) ?></td>
                            <td><?= htmlspecialchars($row['student_id']) ?></td>
                            <td><?= date('M d, Y H:i', strtotime($row['borrow_date'])) ?></td>
                            <td><?= $row['return_date'] ? date('M d, Y H:i', strtotime($row['return_date'])) : '-' ?></td>
                            <td>
                                <span style="color: <?= $row['status'] === 'returned' ? 'green' : 'orange' ?>;">
                                    <?= ucfirst($row['status']) ?>
                                </span>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align: center; color: #666;">No borrowing records found for this book.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- Edit Book Modal (copied from books.php) -->
<div id="editBookModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close" id="closeEditBookModalBtn" style="cursor:pointer;">&times;</span>
    <h2>Edit Book</h2>
    <form method="post" id="editBookForm" autocomplete="off">
      <input type="hidden" name="edit_book_id" id="edit_book_id">
      <div class="form-group">
        <label for="edit_book_isbn">ISBN</label>
        <input type="text" name="edit_book_isbn" id="edit_book_isbn">
      </div>
      <div class="form-group">
        <label for="edit_book_ctrl_number">Control Number</label>
        <input type="text" name="edit_book_ctrl_number" id="edit_book_ctrl_number">
      </div>
      <div class="form-group">
        <label for="edit_book_title">Title *</label>
        <input type="text" name="edit_book_title" id="edit_book_title" required>
      </div>
      <div class="form-group">
        <label for="edit_book_author">Author</label>
        <input type="text" name="edit_book_author" id="edit_book_author">
      </div>
      <div class="form-group">
        <label for="edit_book_publisher">Publisher</label>
        <input type="text" name="edit_book_publisher" id="edit_book_publisher">
      </div>
      <div class="form-group">
        <label for="edit_book_year">Year Published</label>
        <input type="number" name="edit_book_year" id="edit_book_year" min="1000" max="9999">
      </div>
      <div class="form-group">
        <label for="edit_book_copyright">Copyright Year</label>
        <input type="number" name="edit_book_copyright" id="edit_book_copyright" min="1000" max="9999">
      </div>
      <div class="form-group">
        <label for="edit_book_total">Total Copies</label>
        <input type="number" name="edit_book_total" id="edit_book_total" min="1" required>
      </div>
      <div class="form-group">
        <label for="edit_book_available">Available Copies</label>
        <input type="number" name="edit_book_available" id="edit_book_available" min="0" required>
      </div>
      <button type="submit" class="btn-primary">Save Changes</button>
    </form>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  // Close Edit Book Modal
  var closeEditBtn = document.getElementById('closeEditBookModalBtn');
  if (closeEditBtn) {
    closeEditBtn.onclick = function() {
      document.getElementById('editBookModal').style.display = 'none';
    };
  }

  // Close modal when clicking outside modal content
  window.onclick = function(event) {
    var editModal = document.getElementById('editBookModal');
    if (event.target == editModal) {
      editModal.style.display = 'none';
    }
  };
});

// Show Edit Book Modal and populate fields
function openEditBookModal(book) {
  document.getElementById('editBookModal').style.display = 'block';
  document.getElementById('edit_book_id').value = book.book_id;
  document.getElementById('edit_book_isbn').value = book.isbn || '';
  document.getElementById('edit_book_ctrl_number').value = book.ctrl_number || '';
  document.getElementById('edit_book_title').value = book.title;
  document.getElementById('edit_book_author').value = book.author || '';
  document.getElementById('edit_book_publisher').value = book.publisher || '';
  document.getElementById('edit_book_year').value = book.year_published || '';
  document.getElementById('edit_book_copyright').value = book.copyright || '';
  document.getElementById('edit_book_total').value = book.total_copies;
  document.getElementById('edit_book_available').value = book.available_copies;
}
</script>

<?php
// Handle Edit Book POST (same as in books.php)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_book_id'])) {
  $book_id = intval($_POST['edit_book_id']);
  $isbn = trim($_POST['edit_book_isbn']);
  $ctrl_number = trim($_POST['edit_book_ctrl_number']);
  $title = trim($_POST['edit_book_title']);
  $author = trim($_POST['edit_book_author']);
  $publisher = trim($_POST['edit_book_publisher']);
  $year = !empty($_POST['edit_book_year']) ? intval($_POST['edit_book_year']) : null;
  $copyright = !empty($_POST['edit_book_copyright']) ? intval($_POST['edit_book_copyright']) : null;
  $total = intval($_POST['edit_book_total']);
  $available = intval($_POST['edit_book_available']);
  $status = ($available > 0) ? 'Available' : 'Borrowed';

  $stmt = $conn->prepare("UPDATE books SET isbn=?, ctrl_number=?, title=?, author=?, publisher=?, year_published=?, copyright=?, status=?, total_copies=?, available_copies=? WHERE book_id=?");
  $stmt->bind_param("sssssiisiii", $isbn, $ctrl_number, $title, $author, $publisher, $year, $copyright, $status, $total, $available, $book_id);
  if ($stmt->execute()) {
    echo "<script>window.location.href=window.location.pathname+'?book_updated=1&book_id=" . $book_id . "';</script>";
    exit;
  } else {
    echo "<p style='color:red;'>Error updating book.</p>";
  }
  $stmt->close();
}
?>

<?php require_once '../includes/footer.php'; ?>