<?php
// borrowed_records.php
include "../includes/header.php";
include "../includes/db.php";

// Only show books that are currently borrowed (status = 'borrowed')
$sql = "SELECT 
            bb.id, 
            b.title, 
            s.lastname, 
            s.firstname, 
            bb.borrow_date, 
            bb.return_date, 
            bb.status
        FROM borrowed_books bb
        JOIN books b ON bb.book_id = b.book_id
        JOIN students s ON bb.student_id = s.student_id
        WHERE bb.status = 'borrowed'
        ORDER BY bb.borrow_date DESC";

$result = $conn->query($sql);
?>

    <h2>Borrowed Books Records</h2>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Book Title</th>
                <th>Borrower</th>
                <th>Borrow Date</th>
                <th>Return Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['id']); ?></td>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td><?php echo htmlspecialchars($row['lastname'] . ', ' . $row['firstname']); ?></td>
                        <td><?php echo htmlspecialchars($row['borrow_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['return_date']); ?></td>
                        <td><?php echo htmlspecialchars($row['status']); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No borrowed records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
<?php
include '../includes/footer.php';
$conn->close();
?>