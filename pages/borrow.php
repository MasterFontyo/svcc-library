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
        $student = $student_result->fetch_assoc();
        $book = $book_result->fetch_assoc();
        $student_type = $student['type'];

        // Check borrow limits based on student type
        $borrow_limit = 0;
        if ($student_type === 'Admin' || $student_type === 'Faculty') {
            $borrow_limit = 999; // No limit for Admin and Faculty
        } elseif ($student_type === 'College' || $student_type === 'BasicEd') {
            $borrow_limit = 3; // Max 3 books for College and BasicEd
        }

        // Count current borrowed books for this student
        $count_check = $conn->prepare("SELECT COUNT(*) as borrowed_count FROM borrowed_books WHERE student_id = ? AND status = 'borrowed'");
        $count_check->bind_param("s", $student_id);
        $count_check->execute();
        $count_result = $count_check->get_result();
        $current_borrowed = $count_result->fetch_assoc()['borrowed_count'];

        if ($book['available_copies'] <= 0) {
            $error = "No available copies left for this book.";
        } elseif ($current_borrowed >= $borrow_limit && $borrow_limit < 999) {
            $error = "Borrowing limit reached. " . ucfirst($student_type) . " students can borrow a maximum of " . $borrow_limit . " books.";
        } else {
            // Check if student already borrowed this book
            $duplicate_check = $conn->prepare("SELECT * FROM borrowed_books WHERE student_id = ? AND book_id = ? AND status = 'borrowed'");
            $duplicate_check->bind_param("si", $student_id, $book_id);
            $duplicate_check->execute();
            $duplicate_result = $duplicate_check->get_result();

            if ($duplicate_result->num_rows > 0) {
                $error = "Student has already borrowed this book.";
            } else {
                // Record borrow with proper columns
                $stmt = $conn->prepare("INSERT INTO borrowed_books (student_id, book_id, borrow_date, status) VALUES (?, ?, NOW(), 'borrowed')");
                $stmt->bind_param("si", $student_id, $book_id);

                if ($stmt->execute()) {
                    // Decrease available copies and update status if needed
                    $update = $conn->prepare("UPDATE books SET available_copies = available_copies - 1, status = CASE WHEN available_copies - 1 = 0 THEN 'Borrowed' ELSE 'Available' END WHERE book_id = ?");
                    $update->bind_param("i", $book_id);
                    $update->execute();

                    $success = "Book borrowed successfully. Current borrowed books: " . ($current_borrowed + 1);
                    if ($borrow_limit < 999) {
                        $success .= " (Limit: " . $borrow_limit . ")";
                    }
                } else {
                    $error = "Error saving borrow record.";
                }
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

  <!-- Show student info when scanned/typed -->
  <div id="student_info" style="margin: 10px 0; padding: 10px; background: #f0f8ff; border-radius: 4px; display: none;">
    <strong>Student:</strong> <span id="student_name"></span><br>
    <strong>Type:</strong> <span id="student_type"></span><br>
    <strong>Currently Borrowed:</strong> <span id="borrowed_count">0</span> books
    <div id="limit_warning" style="margin-top: 5px;"></div>
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

  <button type="submit" class="btn" id="borrow_btn">Borrow</button>
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
        checkStudentInfo(decodedText);
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

// Check student info when student ID is entered
document.getElementById('student_id').addEventListener('blur', function() {
  const studentId = this.value.trim();
  if (studentId) {
    checkStudentInfo(studentId);
  } else {
    document.getElementById('student_info').style.display = 'none';
  }
});

function checkStudentInfo(studentId) {
  fetch('check_student.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'student_id=' + encodeURIComponent(studentId)
  })
  .then(response => response.json())
  .then(data => {
    if (data.found) {
      document.getElementById('student_name').textContent = data.name;
      document.getElementById('student_type').textContent = data.type;
      document.getElementById('borrowed_count').textContent = data.borrowed_count;
      document.getElementById('student_info').style.display = 'block';
      
      const warningDiv = document.getElementById('limit_warning');
      const borrowBtn = document.getElementById('borrow_btn');
      
      // Show warning based on borrowed count and limits
      if (data.at_limit) {
        document.getElementById('student_info').style.background = '#ffebee';
        warningDiv.innerHTML = '<strong style="color:red;">⚠️ BORROWING LIMIT REACHED! Cannot borrow more books.</strong>';
        borrowBtn.disabled = true;
        borrowBtn.style.opacity = '0.5';
        borrowBtn.style.cursor = 'not-allowed';
      } else if (data.near_limit) {
        document.getElementById('student_info').style.background = '#fff3e0';
        warningDiv.innerHTML = '<strong style="color:orange;">⚠️ Warning: Near borrowing limit! (' + data.borrowed_count + '/' + data.limit + ')</strong>';
        borrowBtn.disabled = false;
        borrowBtn.style.opacity = '1';
        borrowBtn.style.cursor = 'pointer';
      } else {
        document.getElementById('student_info').style.background = '#f0f8ff';
        if (data.limit < 999) {
          warningDiv.innerHTML = '<span style="color:green;">✓ Can borrow ' + (data.limit - data.borrowed_count) + ' more books</span>';
        } else {
          warningDiv.innerHTML = '<span style="color:green;">✓ No borrowing limit</span>';
        }
        borrowBtn.disabled = false;
        borrowBtn.style.opacity = '1';
        borrowBtn.style.cursor = 'pointer';
      }
    } else {
      document.getElementById('student_info').style.display = 'none';
      const borrowBtn = document.getElementById('borrow_btn');
      borrowBtn.disabled = false;
      borrowBtn.style.opacity = '1';
      borrowBtn.style.cursor = 'pointer';
    }
  })
  .catch(error => {
    console.error('Error checking student:', error);
    document.getElementById('student_info').style.display = 'none';
  });
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

<?php include '../includes/footer.php'; ?>
