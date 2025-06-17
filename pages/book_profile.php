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
<?php else: ?>
    <div style="display: flex; gap: 2rem;">
        <div>
            <p><strong>Title:</strong> <?= htmlspecialchars($book['title']) ?></p>
            <p><strong>Author:</strong> <?= htmlspecialchars($book['author']) ?></p>
            <p><strong>Publisher:</strong> <?= htmlspecialchars($book['publisher']) ?></p>
            <p><strong>Year Published:</strong> <?= htmlspecialchars($book['year_published']) ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($book['status']) ?></p>
            <p><strong>Available/Total Copies:</strong> <?= $book['available_copies'] ?> / <?= $book['total_copies'] ?></p>
        </div>
    </div>

    <h3>Borrowers</h3>
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
                        <td><?= date('Y-m-d H:i', strtotime($row['borrow_date'])) ?></td>
                        <td><?= $row['return_date'] ? date('Y-m-d H:i', strtotime($row['return_date'])) : '-' ?></td>
                        <td><?= ucfirst($row['status']) ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="5">No borrow records for this book.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>