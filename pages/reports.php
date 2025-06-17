<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// --- Date filter ---
$from = isset($_GET['from']) ? $_GET['from'] : date('Y-m-01');
$to = isset($_GET['to']) ? $_GET['to'] : date('Y-m-d');
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'books';

// --- Borrowed Books Report ---
$borrowed_sql = "SELECT 
    bb.id, 
    bb.borrow_date, 
    bb.return_date, 
    bb.status,
    b.title, 
    b.author, 
    s.student_id, 
    s.lastname, 
    s.firstname, 
    s.middlename
FROM borrowed_books bb
JOIN books b ON bb.book_id = b.book_id
JOIN students s ON bb.student_id = s.student_id
WHERE DATE(bb.borrow_date) BETWEEN ? AND ?
ORDER BY bb.borrow_date DESC";
$stmt = $conn->prepare($borrowed_sql);
$stmt->bind_param("ss", $from, $to);
$stmt->execute();
$borrowed_result = $stmt->get_result();

// --- Attendance Report ---
$attendance_sql = "SELECT 
    a.id,
    a.time_in,
    a.time_out,
    s.student_id,
    s.lastname,
    s.firstname,
    s.middlename
FROM attendance a
JOIN students s ON a.student_id = s.student_id
WHERE DATE(a.time_in) BETWEEN ? AND ?
ORDER BY a.time_in DESC";
$att_stmt = $conn->prepare($attendance_sql);
$att_stmt->bind_param("ss", $from, $to);
$att_stmt->execute();
$attendance_result = $att_stmt->get_result();
?>

<h2>Library Reports</h2>

<form method="get"">
    <input type="hidden" name="tab" value="<?= htmlspecialchars($active_tab) ?>">
    <label for="from">From:</label>
    <input type="date" name="from" id="from" value="<?= htmlspecialchars($from) ?>">
    <label for="to">To:</label>
    <input type="date" name="to" id="to" value="<?= htmlspecialchars($to) ?>">
    <button type="submit" class="btn">Filter</button>
    <button type="button" class="btn btn-secondary" onclick="window.print()">Print</button>
</form>

<ul class="report-tabs" style="list-style:none;display:flex;gap:10px;padding:0;margin-bottom:20px;">
    <li>
        <a href="?tab=books&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>" 
           class="btn <?= $active_tab === 'books' ? 'btn-primary' : 'btn-secondary' ?>">Borrowed Books</a>
    </li>
    <li>
        <a href="?tab=attendance&from=<?= htmlspecialchars($from) ?>&to=<?= htmlspecialchars($to) ?>" 
           class="btn <?= $active_tab === 'attendance' ? 'btn-primary' : 'btn-secondary' ?>">Student Visits</a>
    </li>
</ul>

<div id="print-area">
    <?php if ($active_tab === 'books'): ?>
        <h3>Borrowed Books Report</h3>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Borrow Date</th>
                    <th>Return Date</th>
                    <th>Status</th>
                    <th>Book Title</th>
                    <th>Author</th>
                    <th>Student ID</th>
                    <th>Borrower Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($borrowed_result->num_rows > 0): ?>
                    <?php while($row = $borrowed_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($row['borrow_date'])) ?></td>
                            <td><?= $row['return_date'] ? date('Y-m-d H:i', strtotime($row['return_date'])) : '-' ?></td>
                            <td><?= ucfirst($row['status']) ?></td>
                            <td><?= htmlspecialchars($row['title']) ?></td>
                            <td><?= htmlspecialchars($row['author']) ?></td>
                            <td><?= htmlspecialchars($row['student_id']) ?></td>
                            <td><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname'] . ' ' . $row['middlename']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8">No records found for selected dates.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php elseif ($active_tab === 'attendance'): ?>
        <h3>Attendance Report</h3>
        <table border="1" cellpadding="6" cellspacing="0">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Time In</th>
                    <th>Time Out</th>
                    <th>Student ID</th>
                    <th>Student Name</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($attendance_result->num_rows > 0): ?>
                    <?php while($row = $attendance_result->fetch_assoc()): ?>
                        <tr>
                            <td><?= $row['id'] ?></td>
                            <td><?= date('Y-m-d H:i', strtotime($row['time_in'])) ?></td>
                            <td><?= $row['time_out'] ? date('Y-m-d H:i', strtotime($row['time_out'])) : '-' ?></td>
                            <td><?= htmlspecialchars($row['student_id']) ?></td>
                            <td><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname'] . ' ' . $row['middlename']) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="5" style="text-align:center;">No attendance records found for selected dates.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>


<?php require_once '../includes/footer.php'; ?>