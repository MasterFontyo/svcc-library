<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Get dashboard statistics
$stats = [];

// Total Books
$result = $conn->query("SELECT COUNT(*) as total FROM books");
$stats['total_books'] = $result->fetch_assoc()['total'];

// Total Available Books
$result = $conn->query("SELECT SUM(available_copies) as available FROM books");
$stats['available_books'] = $result->fetch_assoc()['available'] ?? 0;

// Registered Students
$result = $conn->query("SELECT COUNT(*) as total FROM students");
$stats['total_students'] = $result->fetch_assoc()['total'];

// Books Borrowed Today
$result = $conn->query("SELECT COUNT(*) as total FROM borrowed_books WHERE DATE(borrow_date) = CURDATE() AND status = 'borrowed'");
$stats['borrowed_today'] = $result->fetch_assoc()['total'];

// Currently Borrowed Books
$result = $conn->query("SELECT COUNT(*) as total FROM borrowed_books WHERE status = 'borrowed'");
$stats['currently_borrowed'] = $result->fetch_assoc()['total'];

// Overdue Books (assuming 7 days borrowing period)
$result = $conn->query("SELECT COUNT(*) as total FROM borrowed_books WHERE status = 'borrowed' AND DATE(borrow_date) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
$stats['overdue_books'] = $result->fetch_assoc()['total'];

// Get recent activities
$recent_activities = $conn->query("
    SELECT bb.*, s.firstname, s.lastname, b.title, b.author 
    FROM borrowed_books bb 
    JOIN students s ON bb.student_id = s.student_id 
    JOIN books b ON bb.book_id = b.book_id 
    ORDER BY bb.borrow_date DESC 
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get top borrowed books
$top_books = $conn->query("
    SELECT b.title, b.author, COUNT(*) as borrow_count 
    FROM borrowed_books bb 
    JOIN books b ON bb.book_id = b.book_id 
    GROUP BY bb.book_id 
    ORDER BY borrow_count DESC 
    LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

// Get overdue books details
$overdue_details = $conn->query("
    SELECT bb.*, s.firstname, s.lastname, b.title, b.author,
           DATEDIFF(CURDATE(), bb.borrow_date) as days_overdue
    FROM borrowed_books bb 
    JOIN students s ON bb.student_id = s.student_id 
    JOIN books b ON bb.book_id = b.book_id 
    WHERE bb.status = 'borrowed' AND DATE(bb.borrow_date) < DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY bb.borrow_date ASC
    LIMIT 5
")->fetch_all(MYSQLI_ASSOC);

// Get monthly borrowing data for chart
$monthly_data = $conn->query("
    SELECT DATE_FORMAT(borrow_date, '%Y-%m') as month, COUNT(*) as count 
    FROM borrowed_books 
    WHERE borrow_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(borrow_date, '%Y-%m')
    ORDER BY month
")->fetch_all(MYSQLI_ASSOC);

// Get upcoming events (next 7 days)
$upcoming_events = $conn->query("
    SELECT * FROM library_events 
    WHERE event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY event_date, event_time
    LIMIT 3
")->fetch_all(MYSQLI_ASSOC);

// Get events for current month (for mini calendar)
$current_month_events = $conn->query("
    SELECT event_id, title, event_date 
    FROM library_events 
    WHERE DATE_FORMAT(event_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m')
    ORDER BY event_date
")->fetch_all(MYSQLI_ASSOC);

// Group current month events by day
$events_by_day = [];
foreach ($current_month_events as $event) {
    $day = date('j', strtotime($event['event_date']));
    if (!isset($events_by_day[$day])) {
        $events_by_day[$day] = [];
    }
    $events_by_day[$day][] = $event;
}
?>

<style>
.stat-card {
    transition: transform 0.2s ease-in-out;
}
.stat-card:hover {
    transform: translateY(-5px);
}
.activity-item {
    border-left: 3px solid #007bff;
    padding-left: 15px;
    margin-bottom: 15px;
}
.overdue {
    border-left-color: #dc3545 !important;
}
.returned {
    border-left-color: #28a745 !important;
}
.chart-container {
    position: relative;
    height: 250px;
}

.compact-card {
    min-height: 200px;
}

.scroll-area {
    max-height: 180px;
    overflow-y: auto;
}

.quick-action-btn {
    padding: 8px 12px;
    font-size: 12px;
}
</style>

<div class="container-fluid py-3">
    <!-- Page Header -->
    <div class="row mb-3">
        <div class="col-12">
            <h2 class="mb-1"><i class="bi bi-speedometer2"></i> Library Dashboard</h2>
            <p class="text-muted mb-0">Welcome back, <?= htmlspecialchars($_SESSION['username']) ?>!</p>
        </div>
    </div>

    <!-- KPI Cards - Compact -->
    <div class="row mb-3">
        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-book fs-2 text-primary"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-0">Total Books</h6>
                            <h3 class="text-primary mb-0"><?= number_format($stats['total_books']) ?></h3>
                            <small class="text-muted"><?= number_format($stats['available_books']) ?> available</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-people fs-2 text-success"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-0">Students</h6>
                            <h3 class="text-success mb-0"><?= number_format($stats['total_students']) ?></h3>
                            <small class="text-muted">Registered users</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-calendar-check fs-2 text-info"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-0">Borrowed Today</h6>
                            <h3 class="text-info mb-0"><?= number_format($stats['borrowed_today']) ?></h3>
                            <small class="text-muted"><?= number_format($stats['currently_borrowed']) ?> total borrowed</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-2">
            <div class="card stat-card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="bi bi-exclamation-triangle fs-2 text-danger"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="card-title mb-0">Overdue Books</h6>
                            <h3 class="text-danger mb-0"><?= number_format($stats['overdue_books']) ?></h3>
                            <small class="text-muted">Need attention</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Actions - Compact -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0"><i class="bi bi-lightning"></i> Quick Actions</h6>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-2">
                            <a href="books.php" class="btn btn-outline-primary w-100 quick-action-btn">
                                <i class="bi bi-plus-circle"></i> Add Book
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-2">
                            <a href="borrow.php" class="btn btn-outline-success w-100 quick-action-btn">
                                <i class="bi bi-box-arrow-down"></i> Borrow
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-2">
                            <a href="return.php" class="btn btn-outline-info w-100 quick-action-btn">
                                <i class="bi bi-box-arrow-up"></i> Return
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-2">
                            <a href="calendar.php" class="btn btn-outline-warning w-100 quick-action-btn">
                                <i class="bi bi-calendar3"></i> Calendar
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-2">
                            <a href="students.php" class="btn btn-outline-secondary w-100 quick-action-btn">
                                <i class="bi bi-search"></i> Students
                            </a>
                        </div>
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-2">
                            <a href="scan.php" target="_blank" class="btn btn-outline-dark w-100 quick-action-btn">
                                <i class="bi bi-qr-code-scan"></i> Scan
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content - 3 Columns -->
    <div class="row mb-3">
        <!-- Column 1: Calendar & Events -->
        <div class="col-lg-4 mb-3">
            <!-- Mini Calendar -->
            <div class="card border-0 shadow-sm compact-card mb-3">
                <div class="mini-calendar">
                    <div class="calendar-header">
                        <?= date('F Y') ?>
                    </div>
                    <div class="calendar-grid">
                        <!-- Day headers -->
                        <div class="day-header">S</div>
                        <div class="day-header">M</div>
                        <div class="day-header">T</div>
                        <div class="day-header">W</div>
                        <div class="day-header">T</div>
                        <div class="day-header">F</div>
                        <div class="day-header">S</div>
                        
                        <?php
                        $today = date('j');
                        $current_month = date('n');
                        $current_year = date('Y');
                        $first_day = mktime(0, 0, 0, $current_month, 1, $current_year);
                        $days_in_month = date('t', $first_day);
                        $start_day = date('w', $first_day);
                        
                        // Empty cells for days before month starts
                        for ($i = 0; $i < $start_day; $i++) {
                            echo '<div class="calendar-day empty"></div>';
                        }
                        
                        // Days of the month
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $is_today = ($day == $today);
                            $has_events = isset($events_by_day[$day]);
                            $classes = ['calendar-day'];
                            if ($is_today) $classes[] = 'today';
                            if ($has_events) $classes[] = 'has-events';
                            
                            echo '<div class="' . implode(' ', $classes) . '">';
                            echo $day;
                            if ($has_events) {
                                echo '<div class="event-dot"></div>';
                            }
                            echo '</div>';
                        }
                        ?>
                    </div>
                </div>
                <div class="card-footer bg-white text-center py-2">
                    <a href="calendar.php" class="btn btn-primary btn-sm">
                        <i class="bi bi-calendar3"></i> View Calendar
                    </a>
                </div>
            </div>

            <!-- Upcoming Events -->
            <div class="card border-0 shadow-sm compact-card">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0"><i class="bi bi-calendar-event"></i> Upcoming Events</h6>
                </div>
                <div class="card-body py-2">
                    <?php if (empty($upcoming_events)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-calendar-x fs-3 text-muted"></i>
                            <p class="text-muted mb-2">No upcoming events</p>
                            <a href="calendar.php" class="btn btn-primary btn-sm">Add Event</a>
                        </div>
                    <?php else: ?>
                        <div class="scroll-area">
                            <?php foreach ($upcoming_events as $event): ?>
                                <div class="upcoming-event">
                                    <div class="event-date"><?= date('M j, Y', strtotime($event['event_date'])) ?></div>
                                    <div class="event-title"><?= htmlspecialchars($event['title']) ?></div>
                                    <?php if ($event['event_time']): ?>
                                        <div class="event-time"><?= date('g:i A', strtotime($event['event_time'])) ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Column 2: Charts & Books -->
        <div class="col-lg-4 mb-3">
            <!-- Borrowing Trends Chart -->
            <div class="card border-0 shadow-sm compact-card mb-3">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0"><i class="bi bi-graph-up"></i> Borrowing Trends</h6>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="borrowingChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Borrowed Books -->
            <div class="card border-0 shadow-sm compact-card">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0"><i class="bi bi-star"></i> Top Borrowed Books</h6>
                </div>
                <div class="card-body py-2">
                    <?php if (empty($top_books)): ?>
                        <p class="text-muted text-center py-3">No borrowing data available yet.</p>
                    <?php else: ?>
                        <div class="scroll-area">
                            <?php foreach ($top_books as $index => $book): ?>
                                <div class="d-flex align-items-center mb-3">
                                    <span class="badge bg-primary rounded-pill me-2"><?= $index + 1 ?></span>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0 fs-6"><?= htmlspecialchars($book['title']) ?></h6>
                                        <small class="text-muted">by <?= htmlspecialchars($book['author']) ?></small>
                                    </div>
                                    <span class="badge bg-light text-dark"><?= $book['borrow_count'] ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Column 3: Activity & Overdue -->
        <div class="col-lg-4 mb-3">
            <!-- Recent Activity -->
            <div class="card border-0 shadow-sm compact-card mb-3">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h6>
                </div>
                <div class="card-body py-2">
                    <?php if (empty($recent_activities)): ?>
                        <p class="text-muted text-center py-3">No recent activities.</p>
                    <?php else: ?>
                        <div class="scroll-area">
                            <?php foreach ($recent_activities as $activity): ?>
                                <div class="activity-item <?= $activity['status'] == 'returned' ? 'returned' : '' ?> mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0 fs-6" style="font-size: 13px;"><?= htmlspecialchars($activity['title']) ?></h6>
                                            <small class="text-muted d-block" style="font-size: 11px;">
                                                <?= htmlspecialchars($activity['firstname'] . ' ' . $activity['lastname']) ?> • <?= date('M j', strtotime($activity['borrow_date'])) ?>
                                            </small>
                                        </div>
                                        <?php if ($activity['status'] == 'borrowed'): ?>
                                            <span class="badge bg-warning text-dark" style="font-size: 9px;">OUT</span>
                                        <?php else: ?>
                                            <span class="badge bg-success" style="font-size: 9px;">IN</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Overdue Books -->
            <div class="card border-0 shadow-sm compact-card">
                <div class="card-header bg-white py-2">
                    <h6 class="mb-0"><i class="bi bi-exclamation-triangle text-danger"></i> Overdue Books</h6>
                </div>
                <div class="card-body py-2">
                    <?php if (empty($overdue_details)): ?>
                        <div class="text-center py-3">
                            <i class="bi bi-check-circle fs-3 text-success"></i>
                            <p class="text-success mt-2 mb-0">No overdue books!</p>
                        </div>
                    <?php else: ?>
                        <div class="scroll-area">
                            <?php foreach ($overdue_details as $overdue): ?>
                                <div class="activity-item overdue mb-2">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1 fs-6"><?= htmlspecialchars($overdue['title']) ?></h6>
                                            <p class="mb-1 text-muted small">
                                                Borrowed by <?= htmlspecialchars($overdue['firstname'] . ' ' . $overdue['lastname']) ?>
                                            </p>
                                            <small class="text-muted">
                                                Due: <?= date('M j, Y', strtotime($overdue['borrow_date'] . ' +7 days')) ?>
                                            </small>
                                        </div>
                                        <span class="badge bg-danger small"><?= $overdue['days_overdue'] ?>d</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Borrowing Trends Chart
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('borrowingChart').getContext('2d');
    
    const monthlyData = <?= json_encode($monthly_data) ?>;
    const labels = monthlyData.map(item => {
        const date = new Date(item.month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });
    const data = monthlyData.map(item => item.count);
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Books Borrowed',
                data: data,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                borderWidth: 2,
                fill: true,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
});

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    location.reload();
}, 300000);
</script>

<?php require_once '../includes/footer.php'; ?>
