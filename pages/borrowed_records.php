<?php
// borrowed_records.php
include "../includes/header.php";
include "../includes/db.php";

// Only show books that are currently borrowed (status = 'borrowed')
// Sort by how long the book has been borrowed (oldest first - most overdue)
$sql = "SELECT 
            bb.id, 
            b.title, 
            b.author,
            s.lastname, 
            s.firstname, 
            s.student_id,
            bb.borrow_date, 
            bb.return_date, 
            bb.status,
            DATEDIFF(CURDATE(), bb.borrow_date) as days_borrowed,
            CASE 
                WHEN DATEDIFF(CURDATE(), bb.borrow_date) > 3 THEN 'overdue'
                WHEN DATEDIFF(CURDATE(), bb.borrow_date) = 3 THEN 'due_today'
                ELSE 'active'
            END as borrow_status
        FROM borrowed_books bb
        JOIN books b ON bb.book_id = b.book_id
        JOIN students s ON bb.student_id = s.student_id
        WHERE bb.status = 'borrowed'
        ORDER BY bb.borrow_date ASC";

$result = $conn->query($sql);

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_borrowed,
                SUM(CASE WHEN DATEDIFF(CURDATE(), borrow_date) > 3 THEN 1 ELSE 0 END) as overdue_count,
                SUM(CASE WHEN DATEDIFF(CURDATE(), borrow_date) = 3 THEN 1 ELSE 0 END) as due_today_count,
                AVG(DATEDIFF(CURDATE(), borrow_date)) as avg_days_borrowed
              FROM borrowed_books 
              WHERE status = 'borrowed'";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();
?>

<div class="borrowed-records-container">
    <!-- Header with Back Button -->
    <div class="page-header">
        <div class="header-left">
            <a href="borrow.php" class="btn btn-back">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>
        <div class="header-center">
            <h2><i class="bi bi-book-half"></i> Currently Borrowed Books</h2>
        </div>
        <div class="header-right">
            <span class="last-updated">Last updated: <?= date('M j, Y g:i A') ?></span>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="borrowed-stats">
        <div class="stat-card total">
            <div class="stat-icon">
                <i class="bi bi-books"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['total_borrowed'] ?? 0) ?></h3>
                <p>Total Borrowed</p>
            </div>
        </div>
        
        <div class="stat-card overdue">
            <div class="stat-icon">
                <i class="bi bi-exclamation-triangle"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['overdue_count'] ?? 0) ?></h3>
                <p>Overdue Books</p>
            </div>
        </div>
        
        <div class="stat-card due-today">
            <div class="stat-icon">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="stat-content">
                <h3><?= number_format($stats['due_today_count'] ?? 0) ?></h3>
                <p>Due Today</p>
            </div>
        </div>
        
        <div class="stat-card average">
            <div class="stat-icon">
                <i class="bi bi-calendar-range"></i>
            </div>
            <div class="stat-content">
                <h3><?= round($stats['avg_days_borrowed'] ?? 0, 1) ?></h3>
                <p>Avg Days Borrowed</p>
            </div>
        </div>
    </div>

    <!-- Records Table -->
    <div class="table-container">
        <table class="borrowed-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Book Details</th>
                    <th>Borrower</th>
                    <th>Borrow Date</th>
                    <th>Days Borrowed</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while($row = $result->fetch_assoc()): ?>
                        <tr class="record-row <?= $row['borrow_status'] ?>">
                            <td class="record-id"><?= htmlspecialchars($row['id']) ?></td>
                            <td class="book-details">
                                <div class="book-title"><?= htmlspecialchars($row['title']) ?></div>
                                <div class="book-author">by <?= htmlspecialchars($row['author']) ?></div>
                            </td>
                            <td class="borrower-info">
                                <div class="borrower-name"><?= htmlspecialchars($row['lastname'] . ', ' . $row['firstname']) ?></div>
                                <div class="borrower-id">ID: <?= htmlspecialchars($row['student_id']) ?></div>
                            </td>
                            <td class="borrow-date"><?= date('M j, Y', strtotime($row['borrow_date'])) ?></td>
                            <td class="days-borrowed">
                                <span class="days-count"><?= $row['days_borrowed'] ?></span>
                                <span class="days-label">day<?= $row['days_borrowed'] != 1 ? 's' : '' ?></span>
                                <?php if ($row['days_borrowed'] > 3): ?>
                                    <div class="overdue-badge">
                                        <?= $row['days_borrowed'] - 3 ?> days overdue
                                    </div>
                                <?php elseif ($row['days_borrowed'] == 3): ?>
                                    <div class="due-badge">Due today</div>
                                <?php endif; ?>
                            </td>
                            <td class="status-cell">
                                <span class="status-badge <?= $row['borrow_status'] ?>">
                                    <?php
                                    switch($row['borrow_status']) {
                                        case 'overdue':
                                            echo '<i class="bi bi-exclamation-triangle"></i> Overdue';
                                            break;
                                        case 'due_today':
                                            echo '<i class="bi bi-clock"></i> Due Today';
                                            break;
                                        default:
                                            echo '<i class="bi bi-check-circle"></i> Active';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="return.php?id=<?= $row['id'] ?>" class="btn btn-return">
                                    <i class="bi bi-arrow-return-left"></i> Return
                                </a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-records">
                            <div class="empty-state">
                                <i class="bi bi-inbox"></i>
                                <h4>No borrowed books found</h4>
                                <p>All books have been returned or no books are currently borrowed.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
include '../includes/footer.php';
$conn->close();
?>