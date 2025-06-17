<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = trim($_POST['student_id']);
    $book_id = intval($_POST['book_id']);

    // Validate student
    $student_check = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $student_check->bind_param("s", $student_id);
    $student_check->execute();
    $student_result = $student_check->get_result();

    // Validate book
    $book_check = $conn->prepare("SELECT * FROM books WHERE book_id = ?");
    $book_check->bind_param("i", $book_id);
    $book_check->execute();
    $book_result = $book_check->get_result();

    if ($student_result->num_rows === 0) {
        $error = "Student not found.";
    } elseif ($book_result->num_rows === 0) {
        $error = "Book not found.";
    } else {
        $book = $book_result->fetch_assoc();

        if ($book['available_copies'] <= 0) {
            $error = "No available copies left for this book.";
        } else {
            // Record borrow
            $stmt = $conn->prepare("INSERT INTO borrowed_books (student_id, book_id) VALUES (?, ?)");
            $stmt->bind_param("si", $student_id, $book_id);

            if ($stmt->execute()) {
                // Decrease available copies
                $update = $conn->prepare("UPDATE books SET available_copies = available_copies - 1 WHERE book_id = ?");
                $update->bind_param("i", $book_id);
                $update->execute();

                $success = "Book borrowed successfully.";
            } else {
                $error = "Error saving borrow record.";
            }
        }
    }
}
?>

<h2>Borrow Book</h2>

<?php if ($success): ?>
  <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>
<?php if ($error): ?>
  <div class="alert alert-error"><?= $error ?></div>
<?php endif; ?>

<form method="POST" action="">
  <label for="student_id">Student ID (Scan or Type) *</label>
  <div style="display:flex;gap:8px;align-items:center;">
    <input type="text" name="student_id" id="student_id" required>
    <button type="button" class="btn btn-secondary" onclick="openScannerModal()">Scan</button>
  </div>

  <label for="book_id">Select or Search Book *</label>
  <select name="book_id" id="book_id" style="width:100%;" required>
    <option value="">-- Choose a Book --</option>
    <?php
    $books = $conn->query("SELECT book_id, title, author, available_copies, total_copies FROM books ORDER BY title ASC");
    while ($book = $books->fetch_assoc()):
    ?>
      <option value="<?= $book['book_id'] ?>" <?= $book['available_copies'] <= 0 ? 'disabled' : '' ?>>
        <?= htmlspecialchars($book['title']) ?> by <?= htmlspecialchars($book['author']) ?>
        (<?= $book['available_copies'] ?>/<?= $book['total_copies'] ?> available)
      </option>
    <?php endwhile; ?>
  </select>

  <button type="submit" class="btn">Borrow</button>
  <a href="borrowed_records.php" class="btn btn-secondary">View Borrowed List</a>
</form>

<!-- Scanner Modal -->
<div id="scannerModal" class="modal">
  <div class="modal-content">
    <span class="close" id="closeScannerModalBtn">&times;</span>
    <h3>Scan Student QR Code</h3>
    <div id="qr-reader" style="width:100%;"></div>
    <div id="qr-reader-result" style="margin-top:10px;color:#800000;"></div>
  </div>
</div>

<script src="https://unpkg.com/html5-qrcode"></script>
<script>
let qrReaderInstance = null;

function openScannerModal() {
  document.getElementById('scannerModal').style.display = 'block';
  if (!qrReaderInstance) {
    qrReaderInstance = new Html5Qrcode("qr-reader");
    qrReaderInstance.start(
      { facingMode: "environment" },
      { fps: 10, qrbox: 250 },
      (decodedText) => {
        document.getElementById('student_id').value = decodedText;
        document.getElementById('qr-reader-result').innerText = "Scanned: " + decodedText;
        setTimeout(() => {
          closeScannerModal();
        }, 800);
      },
      (error) => { /* ignore scan errors */ }
    );
  }
}

function closeScannerModal() {
  document.getElementById('scannerModal').style.display = 'none';
  if (qrReaderInstance) {
    qrReaderInstance.stop().then(() => {
      document.getElementById('qr-reader').innerHTML = "";
      qrReaderInstance = null;
    }).catch(() => {
      document.getElementById('qr-reader').innerHTML = "";
      qrReaderInstance = null;
    });
  }
}

document.getElementById('closeScannerModalBtn').onclick = closeScannerModal;

window.onclick = function(event) {
  var modal = document.getElementById('scannerModal');
  if (event.target == modal) {
    closeScannerModal();
  }
};

// Initialize Select2 for searchable book dropdown
$(document).ready(function() {
  $('#book_id').select2({
    placeholder: "Type to search books...",
    allowClear: true,
    width: 'resolve'
  });
});
</script>

<?php include '../includes/footer.php';
