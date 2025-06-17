<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

$student = null;
$borrowed_books = [];
$error = '';
$success = '';

// Handle return action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['return_book_id'], $_POST['return_student_id'])) {
    $return_book_id = intval($_POST['return_book_id']);
    $return_student_id = trim($_POST['return_student_id']);

    // Check if the borrow record exists and is not yet returned
    $stmt = $conn->prepare("SELECT * FROM borrowed_books WHERE book_id = ? AND student_id = ? AND status = 'borrowed'");
    $stmt->bind_param("is", $return_book_id, $return_student_id);
    $stmt->execute();
    $borrow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($borrow) {
        // Update borrowed_books status and set return_date
        $stmt = $conn->prepare("UPDATE borrowed_books SET status = 'returned', return_date = NOW() WHERE id = ?");
        $stmt->bind_param("i", $borrow['id']);
        $stmt->execute();
        $stmt->close();

        // Increase available_copies in books
        $stmt = $conn->prepare("UPDATE books SET available_copies = available_copies + 1 WHERE book_id = ?");
        $stmt->bind_param("i", $return_book_id);
        $stmt->execute();
        $stmt->close();

        $success = "Book returned successfully.";
    } else {
        $error = "Borrow record not found or already returned.";
    }
}

// Handle student search
if (isset($_GET['student_id']) && $_GET['student_id'] !== '') {
    $student_id = trim($_GET['student_id']);
    // Fetch student details
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $student = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($student) {
        // Fetch borrowed books (not yet returned)
        $stmt = $conn->prepare(
            "SELECT b.book_id, b.title, b.author, bb.borrow_date, bb.id AS borrow_id
             FROM borrowed_books bb
             JOIN books b ON bb.book_id = b.book_id
             WHERE bb.student_id = ? AND bb.status = 'borrowed'"
        );
        $stmt->bind_param("s", $student_id);
        $stmt->execute();
        $borrowed_books = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $error = "Student not found.";
    }
}
?>

<h2>Return Book</h2>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="get" action="" style="margin-bottom: 1.5rem;">
    <label for="student_id">Scan or Enter Student ID:</label>
    <div style="display:flex;gap:8px;align-items:center;">
        <input type="text" name="student_id" id="student_id" value="<?= isset($_GET['student_id']) ? htmlspecialchars($_GET['student_id']) : '' ?>" required autofocus>
        <button type="button" class="btn btn-secondary" onclick="openScannerModal()">Scan</button>
    </div>
    <button type="submit" class="btn">Search</button>
</form>

<!-- Scanner Modal -->
<div id="scannerModal" class="modal" style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background:rgba(0,0,0,0.4);">
  <div class="modal-content" style="background:#fff; margin:10% auto; padding:20px; border-radius:8px; max-width:400px; position:relative;">
    <span class="close" id="closeScannerModalBtn" style="position:absolute; right:16px; top:8px; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
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
          // Automatically submit the form to show profile and table
          document.querySelector('form[method="get"]').submit();
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
</script>

<?php if ($student): ?>
    <div style="margin-bottom:1rem;">
        <strong>Student:</strong> <?= htmlspecialchars($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename']) ?><br>
        <strong>ID:</strong> <?= htmlspecialchars($student['student_id']) ?><br>
        <strong>Course:</strong> <?= htmlspecialchars($student['course']) ?> | <strong>Year:</strong> <?= htmlspecialchars($student['year_level']) ?>
    </div>

    <h3>Borrowed Books</h3>
    <?php if (count($borrowed_books) > 0): ?>
        <table>
            <thead>
                <tr>
                    <th>Title</th>
                    <th>Author</th>
                    <th>Borrow Date</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($borrowed_books as $book): ?>
                <tr>
                    <td><?= htmlspecialchars($book['title']) ?></td>
                    <td><?= htmlspecialchars($book['author']) ?></td>
                    <td><?= date('Y-m-d H:i', strtotime($book['borrow_date'])) ?></td>
                    <td>
                        <button 
                            type="submit" 
                            class="btn btn-secondary" 
                            onclick="
                                event.preventDefault();
                                if(confirm('Return this book?')) {
                                    const form = document.createElement('form');
                                    form.method = 'post';
                                    form.style.display = 'none';
                                    form.innerHTML = `
                                        <input type='hidden' name='return_book_id' value='<?= $book['book_id'] ?>'>
                                        <input type='hidden' name='return_student_id' value='<?= htmlspecialchars($student['student_id']) ?>'>
                                    `;
                                    document.body.appendChild(form);
                                    form.submit();
                                }
                            "
                        >Return</button>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-info">No borrowed books found for this student.</div>
    <?php endif; ?>
<?php elseif (isset($_GET['student_id'])): ?>
    <div class="alert alert-error">Student not found.</div>
<?php endif; ?>