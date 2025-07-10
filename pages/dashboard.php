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

// Overdue Books (assuming 3 days borrowing period)
$result = $conn->query("SELECT COUNT(*) as total FROM borrowed_books WHERE status = 'borrowed' AND DATE(borrow_date) < DATE_SUB(CURDATE(), INTERVAL 3 DAY)");
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
    WHERE bb.status = 'borrowed' AND DATE(bb.borrow_date) < DATE_SUB(CURDATE(), INTERVAL 3 DAY)
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

/* Modal styles */
.modal-sm {
    max-width: 400px;
}

.alert-sm {
    padding: 0.5rem 0.75rem;
    margin-bottom: 1rem;
    font-size: 0.875rem;
}

.modal-body .form-label {
    font-weight: 500;
}

.modal-body .form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

/* Fix modal backdrop and z-index issues */
#passwordModal {
    z-index: 1060 !important;
}

#passwordModal .modal-backdrop {
    z-index: 1055 !important;
}

.modal-backdrop.show {
    opacity: 0.5;
}

/* Ensure modal content is above backdrop */
.modal-dialog {
    z-index: 1061 !important;
    position: relative;
}

/* Mini Calendar Styles */
.mini-calendar {
    padding: 1rem;
}

.calendar-header {
    text-align: center;
    font-weight: 600;
    margin-bottom: 1rem;
    color: #495057;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
}

.day-header {
    text-align: center;
    font-weight: 600;
    padding: 0.5rem;
    background-color: #f8f9fa;
    color: #6c757d;
    font-size: 0.75rem;
}

.calendar-day {
    text-align: center;
    padding: 0.5rem;
    cursor: pointer;
    position: relative;
    min-height: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    border: 1px solid transparent;
}

.calendar-day:hover {
    background-color: #f8f9fa;
}

.calendar-day.today {
    background-color: #007bff;
    color: white;
    font-weight: 600;
    border-radius: 50%;
}

.calendar-day.has-events {
    background-color: #e7f3ff;
    border: 1px solid #007bff;
}

.calendar-day.has-events.today {
    background-color: #007bff;
}

.calendar-day.empty {
    cursor: default;
}

.event-dot {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 4px;
    height: 4px;
    background-color: #28a745;
    border-radius: 50%;
}

.today .event-dot {
    background-color: #fff;
}

/* Upcoming Events */
.upcoming-event {
    padding: 0.5rem 0;
    border-bottom: 1px solid #e9ecef;
}

.upcoming-event:last-child {
    border-bottom: none;
}

.event-date {
    font-size: 0.75rem;
    color: #007bff;
    font-weight: 600;
}

.event-title {
    font-size: 0.875rem;
    color: #495057;
    margin: 0.25rem 0;
}

.event-time {
    font-size: 0.75rem;
    color: #6c757d;
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
                        <div class="col-lg-2 col-md-3 col-sm-4 col-6 mb-2">
                            <button type="button" class="btn btn-outline-danger w-100 quick-action-btn" onclick="accessActivityLogs()">
                                <i class="bi bi-activity"></i> Activity Logs
                            </button>
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

<!-- Password Verification Modal for Activity Logs -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalLabel">
                    <i class="bi bi-shield-lock"></i> Admin Verification Required
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning alert-sm">
                    <i class="bi bi-info-circle"></i> Enter your password to access Activity Logs.
                </div>
                <form id="passwordForm" onsubmit="return false;">
                    <div class="mb-3">
                        <label for="adminPassword" class="form-label">Password</label>
                        <input type="password" class="form-control" id="adminPassword" required autocomplete="current-password">
                        <div class="invalid-feedback" id="passwordError"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary btn-sm" onclick="verifyPassword()">
                    <i class="bi bi-unlock"></i> Verify & Access
                </button>
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

// Debug function to clean up modal issues
function cleanupModals() {
    // Remove all modal backdrops
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.remove();
    });
    
    // Reset body classes and styles
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    
    // Reset modal state
    const modal = document.getElementById('passwordModal');
    modal.classList.remove('show');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    modal.removeAttribute('aria-modal');
    
    console.log('Modal cleanup completed');
}

// Activity Logs Access Function
function accessActivityLogs() {
    // Remove any existing modal backdrops first
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.remove();
    });
    
    // Reset modal state
    const modalElement = document.getElementById('passwordModal');
    modalElement.classList.remove('show');
    modalElement.style.display = 'none';
    modalElement.setAttribute('aria-hidden', 'true');
    modalElement.removeAttribute('aria-modal');
    
    // Create new modal instance with proper configuration
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: false,
        focus: true
    });
    
    // Clear previous error states
    document.getElementById('adminPassword').value = '';
    document.getElementById('adminPassword').classList.remove('is-invalid');
    document.getElementById('passwordError').textContent = '';
    
    // Show modal
    modal.show();
    
    // Focus on password input after modal is shown
    modalElement.addEventListener('shown.bs.modal', function() {
        document.getElementById('adminPassword').focus();
    }, { once: true });
}

// Password Verification Function
function verifyPassword() {
    const password = document.getElementById('adminPassword').value;
    const passwordInput = document.getElementById('adminPassword');
    const errorDiv = document.getElementById('passwordError');
    
    if (!password) {
        passwordInput.classList.add('is-invalid');
        errorDiv.textContent = 'Password is required.';
        return;
    }
    
    // Show loading state
    const verifyBtn = document.querySelector('[onclick="verifyPassword()"]');
    const originalText = verifyBtn.innerHTML;
    verifyBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Verifying...';
    verifyBtn.disabled = true;
    
    // Send AJAX request to verify password
    fetch('verify_admin_password.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            password: password
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Password is correct, close modal and redirect
            const modal = bootstrap.Modal.getInstance(document.getElementById('passwordModal'));
            if (modal) {
                modal.hide();
            }
            
            // Clean up any remaining backdrops
            setTimeout(() => {
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                    backdrop.remove();
                });
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('overflow');
                document.body.style.removeProperty('padding-right');
                
                // Redirect to activity logs
                window.location.href = 'activity_log.php';
            }, 100);
        } else {
            // Password is incorrect
            passwordInput.classList.add('is-invalid');
            errorDiv.textContent = data.message || 'Invalid password.';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        passwordInput.classList.add('is-invalid');
        errorDiv.textContent = 'An error occurred. Please try again.';
    })
    .finally(() => {
        // Reset button state
        verifyBtn.innerHTML = originalText;
        verifyBtn.disabled = false;
    });
}

// Handle Enter key press in password modal
document.addEventListener('DOMContentLoaded', function() {
    // Clean up any existing modal backdrops on page load
    document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
        backdrop.remove();
    });
    document.body.classList.remove('modal-open');
    document.body.style.removeProperty('overflow');
    document.body.style.removeProperty('padding-right');
    
    // Handle Enter key in password field
    document.getElementById('adminPassword').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyPassword();
        }
    });
    
    // Handle modal close events
    const passwordModal = document.getElementById('passwordModal');
    passwordModal.addEventListener('hidden.bs.modal', function() {
        // Clean up modal state
        document.getElementById('adminPassword').value = '';
        document.getElementById('adminPassword').classList.remove('is-invalid');
        document.getElementById('passwordError').textContent = '';
        
        // Ensure backdrop is removed
        setTimeout(() => {
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                backdrop.remove();
            });
            document.body.classList.remove('modal-open');
            document.body.style.removeProperty('overflow');
            document.body.style.removeProperty('padding-right');
        }, 100);
    });
});
</script>

<?php require_once '../includes/footer.php'; ?>
