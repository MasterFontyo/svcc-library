<?php
require_once '../includes/header.php';
require_once '../includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Pagination settings
$records_per_page = 20;
$current_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($current_page - 1) * $records_per_page;

// Filter settings
$filter_user = isset($_GET['user']) ? $_GET['user'] : '';
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_activity_type = isset($_GET['activity_type']) ? $_GET['activity_type'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build filter conditions for different activity types
$system_conditions = [];
$borrow_conditions = [];
$return_conditions = [];
$attendance_in_conditions = [];
$attendance_out_conditions = [];

// Date filters (apply to all activity types)
if (!empty($filter_date_from)) {
    $system_conditions[] = "DATE(al.timestamp) >= '" . mysqli_real_escape_string($conn, $filter_date_from) . "'";
    $borrow_conditions[] = "DATE(bb.borrow_date) >= '" . mysqli_real_escape_string($conn, $filter_date_from) . "'";
    $return_conditions[] = "DATE(bb.return_date) >= '" . mysqli_real_escape_string($conn, $filter_date_from) . "'";
    $attendance_in_conditions[] = "DATE(a.time_in) >= '" . mysqli_real_escape_string($conn, $filter_date_from) . "'";
    $attendance_out_conditions[] = "DATE(a.time_out) >= '" . mysqli_real_escape_string($conn, $filter_date_from) . "'";
}

if (!empty($filter_date_to)) {
    $system_conditions[] = "DATE(al.timestamp) <= '" . mysqli_real_escape_string($conn, $filter_date_to) . "'";
    $borrow_conditions[] = "DATE(bb.borrow_date) <= '" . mysqli_real_escape_string($conn, $filter_date_to) . "'";
    $return_conditions[] = "DATE(bb.return_date) <= '" . mysqli_real_escape_string($conn, $filter_date_to) . "'";
    $attendance_in_conditions[] = "DATE(a.time_in) <= '" . mysqli_real_escape_string($conn, $filter_date_to) . "'";
    $attendance_out_conditions[] = "DATE(a.time_out) <= '" . mysqli_real_escape_string($conn, $filter_date_to) . "'";
}

// User/Student name filter
if (!empty($filter_user)) {
    $escaped_user = mysqli_real_escape_string($conn, $filter_user);
    $system_conditions[] = "u.username LIKE '%" . $escaped_user . "%'";
    $borrow_conditions[] = "(CONCAT(s.firstname, ' ', s.lastname) LIKE '%" . $escaped_user . "%' OR s.student_id LIKE '%" . $escaped_user . "%')";
    $return_conditions[] = "(CONCAT(s.firstname, ' ', s.lastname) LIKE '%" . $escaped_user . "%' OR s.student_id LIKE '%" . $escaped_user . "%')";
    $attendance_in_conditions[] = "(CONCAT(s.firstname, ' ', s.lastname) LIKE '%" . $escaped_user . "%' OR s.student_id LIKE '%" . $escaped_user . "%')";
    $attendance_out_conditions[] = "(CONCAT(s.firstname, ' ', s.lastname) LIKE '%" . $escaped_user . "%' OR s.student_id LIKE '%" . $escaped_user . "%')";
}

// Action filter
if (!empty($filter_action)) {
    $escaped_action = mysqli_real_escape_string($conn, $filter_action);
    $system_conditions[] = "al.action LIKE '%" . $escaped_action . "%'";
    $borrow_conditions[] = "CONCAT('Book borrowed: ', b.title) LIKE '%" . $escaped_action . "%'";
    $return_conditions[] = "CONCAT('Book returned: ', b.title) LIKE '%" . $escaped_action . "%'";
    $attendance_in_conditions[] = "'Student check-in to library' LIKE '%" . $escaped_action . "%'";
    $attendance_out_conditions[] = "'Student check-out from library' LIKE '%" . $escaped_action . "%'";
}

// Build WHERE clauses
$system_where = !empty($system_conditions) ? 'WHERE ' . implode(' AND ', $system_conditions) : '';
$borrow_where = 'WHERE bb.borrow_date IS NOT NULL' . (!empty($borrow_conditions) ? ' AND ' . implode(' AND ', $borrow_conditions) : '');
$return_where = 'WHERE bb.return_date IS NOT NULL AND bb.status = \'returned\'' . (!empty($return_conditions) ? ' AND ' . implode(' AND ', $return_conditions) : '');
$attendance_in_where = 'WHERE a.time_in IS NOT NULL' . (!empty($attendance_in_conditions) ? ' AND ' . implode(' AND ', $attendance_in_conditions) : '');
$attendance_out_where = 'WHERE a.time_out IS NOT NULL' . (!empty($attendance_out_conditions) ? ' AND ' . implode(' AND ', $attendance_out_conditions) : '');

// Activity type filter - exclude entire activity types if specific type is selected
$include_system = empty($filter_activity_type) || $filter_activity_type === 'system';
$include_borrow = empty($filter_activity_type) || $filter_activity_type === 'borrow';
$include_return = empty($filter_activity_type) || $filter_activity_type === 'return';
$include_attendance_in = empty($filter_activity_type) || $filter_activity_type === 'attendance_in';
$include_attendance_out = empty($filter_activity_type) || $filter_activity_type === 'attendance_out';

// Get total count for pagination - Include all activities with filters
$count_parts = [];

if ($include_system) {
    $count_parts[] = "
        SELECT al.log_id as id
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        $system_where
    ";
}

if ($include_borrow) {
    $count_parts[] = "
        SELECT bb.id
        FROM borrowed_books bb
        LEFT JOIN books b ON bb.book_id = b.book_id
        LEFT JOIN students s ON bb.student_id = s.student_id
        $borrow_where
    ";
}

if ($include_return) {
    $count_parts[] = "
        SELECT bb.id
        FROM borrowed_books bb
        LEFT JOIN books b ON bb.book_id = b.book_id
        LEFT JOIN students s ON bb.student_id = s.student_id
        $return_where
    ";
}

if ($include_attendance_in) {
    $count_parts[] = "
        SELECT a.id
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.student_id
        $attendance_in_where
    ";
}

if ($include_attendance_out) {
    $count_parts[] = "
        SELECT a.id
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.student_id
        $attendance_out_where
    ";
}

// If no activity types are selected, set count to 0
if (empty($count_parts)) {
    $total_records = 0;
} else {
    $count_query = "SELECT COUNT(*) as total FROM (" . implode(' UNION ALL ', $count_parts) . ") AS all_activities";
    $count_result = $conn->query($count_query);
    $total_records = $count_result ? $count_result->fetch_assoc()['total'] : 0;
}

$total_pages = ceil($total_records / $records_per_page);

// Get activity logs with pagination - Include student activities with filters
$query_parts = [];

if ($include_system) {
    $query_parts[] = "
        SELECT 
            al.log_id as id,
            'system' as activity_type,
            al.user_id,
            al.action,
            al.timestamp,
            u.username,
            NULL as student_id,
            NULL as student_name,
            NULL as book_title,
            NULL as additional_info
        FROM activity_logs al
        LEFT JOIN users u ON al.user_id = u.user_id
        $system_where
    ";
}

if ($include_borrow) {
    $query_parts[] = "
        SELECT 
            bb.id as id,
            'borrow' as activity_type,
            NULL as user_id,
            CONCAT('Book borrowed: ', b.title) as action,
            bb.borrow_date as timestamp,
            NULL as username,
            bb.student_id,
            CONCAT(s.firstname, ' ', s.lastname) as student_name,
            b.title as book_title,
            'Student borrowed a book' as additional_info
        FROM borrowed_books bb
        LEFT JOIN books b ON bb.book_id = b.book_id
        LEFT JOIN students s ON bb.student_id = s.student_id
        $borrow_where
    ";
}

if ($include_return) {
    $query_parts[] = "
        SELECT 
            (bb.id + 100000) as id,
            'return' as activity_type,
            NULL as user_id,
            CONCAT('Book returned: ', b.title) as action,
            bb.return_date as timestamp,
            NULL as username,
            bb.student_id,
            CONCAT(s.firstname, ' ', s.lastname) as student_name,
            b.title as book_title,
            'Student returned a book' as additional_info
        FROM borrowed_books bb
        LEFT JOIN books b ON bb.book_id = b.book_id
        LEFT JOIN students s ON bb.student_id = s.student_id
        $return_where
    ";
}

if ($include_attendance_in) {
    $query_parts[] = "
        SELECT 
            (a.id + 200000) as id,
            'attendance_in' as activity_type,
            NULL as user_id,
            'Student check-in to library' as action,
            a.time_in as timestamp,
            NULL as username,
            a.student_id,
            CONCAT(s.firstname, ' ', s.lastname) as student_name,
            NULL as book_title,
            'Student entered library' as additional_info
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.student_id
        $attendance_in_where
    ";
}

if ($include_attendance_out) {
    $query_parts[] = "
        SELECT 
            (a.id + 300000) as id,
            'attendance_out' as activity_type,
            NULL as user_id,
            'Student check-out from library' as action,
            a.time_out as timestamp,
            NULL as username,
            a.student_id,
            CONCAT(s.firstname, ' ', s.lastname) as student_name,
            NULL as book_title,
            'Student left library' as additional_info
        FROM attendance a
        LEFT JOIN students s ON a.student_id = s.student_id
        $attendance_out_where
    ";
}

// If no activity types are selected, return empty result
if (empty($query_parts)) {
    $activity_logs = [];
} else {
    $query = "(" . implode(") UNION ALL (", $query_parts) . ") ORDER BY timestamp DESC LIMIT $offset, $records_per_page";
    $result = $conn->query($query);
    $activity_logs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

// Get unique actions for filter dropdown
$actions_query = "SELECT DISTINCT action FROM activity_logs WHERE action IS NOT NULL ORDER BY action";
$actions_result = $conn->query($actions_query);
$available_actions = $actions_result->fetch_all(MYSQLI_ASSOC);

// Get activity statistics - Include all activities
$stats_query = "
    SELECT 
        (
            (SELECT COUNT(*) FROM activity_logs) +
            (SELECT COUNT(*) FROM borrowed_books WHERE borrow_date IS NOT NULL) +
            (SELECT COUNT(*) FROM borrowed_books WHERE return_date IS NOT NULL AND status = 'returned') +
            (SELECT COUNT(*) FROM attendance WHERE time_in IS NOT NULL) +
            (SELECT COUNT(*) FROM attendance WHERE time_out IS NOT NULL)
        ) as total_activities,
        (
            (SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE user_id IS NOT NULL) +
            (SELECT COUNT(DISTINCT student_id) FROM borrowed_books WHERE student_id IS NOT NULL) +
            (SELECT COUNT(DISTINCT student_id) FROM attendance WHERE student_id IS NOT NULL)
        ) as unique_users,
        (
            (SELECT COUNT(*) FROM activity_logs WHERE DATE(timestamp) = CURDATE()) +
            (SELECT COUNT(*) FROM borrowed_books WHERE DATE(borrow_date) = CURDATE()) +
            (SELECT COUNT(*) FROM borrowed_books WHERE DATE(return_date) = CURDATE() AND status = 'returned') +
            (SELECT COUNT(*) FROM attendance WHERE DATE(time_in) = CURDATE()) +
            (SELECT COUNT(*) FROM attendance WHERE DATE(time_out) = CURDATE())
        ) as today_activities,
        (
            (SELECT COUNT(*) FROM activity_logs WHERE timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
            (SELECT COUNT(*) FROM borrowed_books WHERE borrow_date >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
            (SELECT COUNT(*) FROM borrowed_books WHERE return_date >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND status = 'returned') +
            (SELECT COUNT(*) FROM attendance WHERE time_in >= DATE_SUB(NOW(), INTERVAL 7 DAY)) +
            (SELECT COUNT(*) FROM attendance WHERE time_out >= DATE_SUB(NOW(), INTERVAL 7 DAY))
        ) as week_activities
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Function to format action for display
function formatAction($action, $activity_type = 'system') {
    // Convert action to a more readable format
    $action = str_replace('_', ' ', $action);
    $action = ucwords(strtolower($action));
    
    // Add icons based on activity type and action
    switch ($activity_type) {
        case 'borrow':
            return '<i class="bi bi-arrow-up-circle text-success"></i> ' . $action;
        case 'return':
            return '<i class="bi bi-arrow-down-circle text-info"></i> ' . $action;
        case 'attendance_in':
            return '<i class="bi bi-box-arrow-in-right text-success"></i> Student Check-in';
        case 'attendance_out':
            return '<i class="bi bi-box-arrow-left text-warning"></i> Student Check-out';
        default:
            // Original system activity formatting
            if (stripos($action, 'login') !== false) {
                return '<i class="bi bi-box-arrow-in-right text-success"></i> ' . $action;
            } elseif (stripos($action, 'logout') !== false) {
                return '<i class="bi bi-box-arrow-left text-warning"></i> ' . $action;
            } elseif (stripos($action, 'book') !== false) {
                return '<i class="bi bi-book text-primary"></i> ' . $action;
            } elseif (stripos($action, 'student') !== false) {
                return '<i class="bi bi-person text-info"></i> ' . $action;
            } elseif (stripos($action, 'event') !== false) {
                return '<i class="bi bi-calendar3 text-purple"></i> ' . $action;
            } elseif (stripos($action, 'borrow') !== false) {
                return '<i class="bi bi-arrow-up-circle text-success"></i> ' . $action;
            } elseif (stripos($action, 'return') !== false) {
                return '<i class="bi bi-arrow-down-circle text-info"></i> ' . $action;
            } elseif (stripos($action, 'delete') !== false) {
                return '<i class="bi bi-trash text-danger"></i> ' . $action;
            } elseif (stripos($action, 'add') !== false || stripos($action, 'create') !== false) {
                return '<i class="bi bi-plus-circle text-success"></i> ' . $action;
            } elseif (stripos($action, 'edit') !== false || stripos($action, 'update') !== false) {
                return '<i class="bi bi-pencil text-warning"></i> ' . $action;
            } else {
                return '<i class="bi bi-activity text-secondary"></i> ' . $action;
            }
    }
}

// Function to get action badge class
function getActionBadgeClass($action, $activity_type = 'system') {
    switch ($activity_type) {
        case 'borrow':
            return 'badge bg-success';
        case 'return':
            return 'badge bg-info';
        case 'attendance_in':
            return 'badge bg-success';
        case 'attendance_out':
            return 'badge bg-warning';
        default:
            // Original system activity classification
            if (stripos($action, 'login') !== false) {
                return 'badge bg-success';
            } elseif (stripos($action, 'logout') !== false) {
                return 'badge bg-warning';
            } elseif (stripos($action, 'delete') !== false) {
                return 'badge bg-danger';
            } elseif (stripos($action, 'add') !== false || stripos($action, 'create') !== false) {
                return 'badge bg-success';
            } elseif (stripos($action, 'edit') !== false || stripos($action, 'update') !== false) {
                return 'badge bg-warning';
            } else {
                return 'badge bg-secondary';
            }
    }
}
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="bi bi-activity"></i> Activity Logs</h2>
                <div>
                    <button type="button" class="btn btn-outline-secondary" onclick="printActivityLogs()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                    <button type="button" class="btn btn-outline-success" onclick="exportToCSV()">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                </div>
            </div>

            <!-- Activity Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-primary"><?= number_format($stats['total_activities']) ?></h5>
                            <p class="card-text text-muted">Total Activities</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-success"><?= number_format($stats['unique_users']) ?></h5>
                            <p class="card-text text-muted">Active Users</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-warning"><?= number_format($stats['today_activities']) ?></h5>
                            <p class="card-text text-muted">Today's Activities</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <h5 class="card-title text-info"><?= number_format($stats['week_activities']) ?></h5>
                            <p class="card-text text-muted">This Week</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="bi bi-funnel"></i> Filters</h6>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label for="user" class="form-label">User/Student</label>
                            <input type="text" class="form-control" id="user" name="user" 
                                   value="<?= htmlspecialchars($filter_user) ?>" 
                                   placeholder="Search by name">
                        </div>
                        <div class="col-md-2">
                            <label for="activity_type" class="form-label">Activity Type</label>
                            <select class="form-select" id="activity_type" name="activity_type">
                                <option value="">All Types</option>
                                <option value="system" <?= $filter_activity_type === 'system' ? 'selected' : '' ?>>System Activities</option>
                                <option value="borrow" <?= $filter_activity_type === 'borrow' ? 'selected' : '' ?>>Book Borrowing</option>
                                <option value="return" <?= $filter_activity_type === 'return' ? 'selected' : '' ?>>Book Returns</option>
                                <option value="attendance_in" <?= $filter_activity_type === 'attendance_in' ? 'selected' : '' ?>>Student Check-in</option>
                                <option value="attendance_out" <?= $filter_activity_type === 'attendance_out' ? 'selected' : '' ?>>Student Check-out</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="action" class="form-label">Action</label>
                            <select class="form-select" id="action" name="action">
                                <option value="">All Actions</option>
                                <?php foreach ($available_actions as $action): ?>
                                    <option value="<?= htmlspecialchars($action['action']) ?>" 
                                            <?= $filter_action === $action['action'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($action['action']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?= htmlspecialchars($filter_date_from) ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?= htmlspecialchars($filter_date_to) ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                                <a href="activity_log.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-x-circle"></i> Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Active Filters Indicator -->
            <?php 
                // Show active filters
                $active_filters = [];
                if (!empty($filter_user)) $active_filters[] = "User: " . htmlspecialchars($filter_user);
                if (!empty($filter_action)) $active_filters[] = "Action: " . htmlspecialchars($filter_action);
                if (!empty($filter_activity_type)) $active_filters[] = "Type: " . htmlspecialchars($filter_activity_type);
                if (!empty($filter_date_from)) $active_filters[] = "From: " . htmlspecialchars($filter_date_from);
                if (!empty($filter_date_to)) $active_filters[] = "To: " . htmlspecialchars($filter_date_to);
                
                if (!empty($active_filters)): ?>
                    <div class="alert alert-info alert-dismissible fade show" role="alert">
                        <i class="bi bi-filter"></i> <strong>Active Filters:</strong> <?= implode(', ', $active_filters) ?>
                        <a href="activity_log.php" class="btn btn-sm btn-outline-secondary ms-2">Clear All</a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

            <!-- Activity Logs Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="bi bi-list-ul"></i> Activity Logs</h6>
                    <small class="text-muted">
                        Showing <?= $offset + 1 ?> to <?= min($offset + $records_per_page, $total_records) ?> 
                        of <?= number_format($total_records) ?> records
                    </small>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($activity_logs)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox display-4 text-muted"></i>
                            <h5 class="mt-3">No Activity Logs Found</h5>
                            <?php if (!empty($filter_user) || !empty($filter_action) || !empty($filter_activity_type) || !empty($filter_date_from) || !empty($filter_date_to)): ?>
                                <p class="text-muted">No activities match your current filter criteria. Try adjusting your filters or <a href="activity_log.php">clear all filters</a>.</p>
                            <?php else: ?>
                                <p class="text-muted">No activities recorded yet. Check back later.</p>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0" id="activityTable">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">ID</th>
                                        <th class="user-cell">User/Student</th>
                                        <th class="activity-cell">Action</th>
                                        <th class="detail-cell">Details</th>
                                        <th class="date-cell">Date & Time</th>
                                        <th class="time-cell">Time Ago</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($activity_logs as $log): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted">#<?= $log['id'] ?></small>
                                            </td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                    <div>
                                                        <i class="bi bi-person-gear text-primary"></i>
                                                        <strong><?= htmlspecialchars($log['username']) ?></strong>
                                                        <br><small class="text-muted">Staff ID: <?= $log['user_id'] ?></small>
                                                    </div>
                                                <?php elseif ($log['student_id']): ?>
                                                    <div>
                                                        <i class="bi bi-person text-info"></i>
                                                        <strong><?= htmlspecialchars($log['student_name']) ?></strong>
                                                        <br><small class="text-muted">Student ID: <?= htmlspecialchars($log['student_id']) ?></small>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">
                                                        <i class="bi bi-gear"></i> System
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="<?= getActionBadgeClass($log['action'], $log['activity_type']) ?>">
                                                    <?= formatAction($log['action'], $log['activity_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($log['book_title']): ?>
                                                    <div>
                                                        <i class="bi bi-book"></i>
                                                        <strong><?= htmlspecialchars($log['book_title']) ?></strong>
                                                    </div>
                                                <?php elseif ($log['additional_info']): ?>
                                                    <small class="text-muted"><?= htmlspecialchars($log['additional_info']) ?></small>
                                                <?php else: ?>
                                                    <small class="text-muted">-</small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div>
                                                    <?= date('M j, Y', strtotime($log['timestamp'])) ?>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= date('g:i A', strtotime($log['timestamp'])) ?>
                                                    </small>
                                                </div>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?php
                                                    $time_diff = time() - strtotime($log['timestamp']);
                                                    if ($time_diff < 60) {
                                                        echo 'Just now';
                                                    } elseif ($time_diff < 3600) {
                                                        echo floor($time_diff / 60) . ' min ago';
                                                    } elseif ($time_diff < 86400) {
                                                        echo floor($time_diff / 3600) . ' hr ago';
                                                    } else {
                                                        echo floor($time_diff / 86400) . ' days ago';
                                                    }
                                                    ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav class="mt-4" aria-label="Activity logs pagination">
                    <ul class="pagination justify-content-center">
                        <?php if ($current_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=1<?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                                    <i class="bi bi-chevron-double-left"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page - 1 ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start_page = max(1, $current_page - 2);
                        $end_page = min($total_pages, $current_page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?= $i === $current_page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($current_page < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $current_page + 1 ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $total_pages ?><?= !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['page' => ''])) : '' ?>">
                                    <i class="bi bi-chevron-double-right"></i>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// Export to CSV function
function exportToCSV() {
    const table = document.getElementById('activityTable');
    const rows = Array.from(table.querySelectorAll('tr'));
    
    const csvContent = rows.map(row => {
        const cells = Array.from(row.querySelectorAll('th, td'));
        return cells.map(cell => {
            // Clean up the cell content
            let content = cell.textContent.trim();
            // Remove extra whitespace and newlines
            content = content.replace(/\s+/g, ' ');
            // Escape quotes
            content = content.replace(/"/g, '""');
            return `"${content}"`;
        }).join(',');
    }).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'activity_logs_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Improved print function
function printActivityLogs() {
    // Add print-specific title and date
    const printTitle = document.createElement('div');
    printTitle.id = 'print-header';
    printTitle.innerHTML = `
        <div style="text-align: center; margin-bottom: 2rem; display: none;" class="print-only">
            <h1>SVCC Library - Activity Logs Report</h1>
            <p>Generated on: ${new Date().toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            })}</p>
            <hr>
        </div>
    `;
    
    // Insert at the beginning of the container
    const container = document.querySelector('.container-fluid');
    container.insertBefore(printTitle, container.firstChild);
    
    // Add print-only CSS
    const printStyle = document.createElement('style');
    printStyle.id = 'print-style';
    printStyle.innerHTML = `
        @media print {
            .print-only {
                display: block !important;
            }
        }
    `;
    document.head.appendChild(printStyle);
    
    // Print the page
    window.print();
    
    // Clean up after printing
    setTimeout(() => {
        if (document.getElementById('print-header')) {
            document.getElementById('print-header').remove();
        }
        if (document.getElementById('print-style')) {
            document.getElementById('print-style').remove();
        }
    }, 1000);
}

// Auto-refresh every 30 seconds if no filters are applied
<?php if (empty($filter_user) && empty($filter_action) && empty($filter_activity_type) && empty($filter_date_from) && empty($filter_date_to)): ?>
setInterval(function() {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 30000);
<?php endif; ?>

// Add search functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('user');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            // Add some visual feedback for search
            if (this.value.length > 0) {
                this.style.borderColor = '#007bff';
            } else {
                this.style.borderColor = '';
            }
        });
    }
});
</script>

<style>
@media print {
    /* Hide interactive elements */
    .btn, .pagination, .form-control, .form-select, .alert-dismissible .btn-close {
        display: none !important;
    }
    
    /* Hide filter form */
    .card:nth-child(3) {
        display: none !important;
    }
    
    /* Show main content */
    .card {
        border: 1px solid #dee2e6 !important;
        box-shadow: none !important;
        margin-bottom: 1rem !important;
        page-break-inside: avoid;
    }
    
    .card-header {
        background-color: #f8f9fa !important;
        border-bottom: 1px solid #dee2e6 !important;
        padding: 0.5rem 1rem !important;
        font-weight: 600;
    }
    
    .card-body {
        padding: 1rem !important;
    }
    
    /* Table styles for print */
    .table {
        font-size: 11px !important;
        margin-bottom: 0 !important;
    }
    
    .table th {
        background-color: #f8f9fa !important;
        border: 1px solid #dee2e6 !important;
        padding: 0.5rem !important;
        font-weight: 600;
    }
    
    .table td {
        border: 1px solid #dee2e6 !important;
        padding: 0.5rem !important;
        vertical-align: top !important;
    }
    
    /* Page break controls */
    .page-break {
        page-break-before: always;
    }
    
    /* Print header */
    @page {
        margin: 1in;
        @top-center {
            content: "SVCC Library - Activity Logs Report";
        }
        @bottom-center {
            content: "Generated on " counter(page) " of " counter(pages);
        }
    }
    
    /* Hide debug info and specific elements */
    .modal,
    .dropdown-menu,
    .collapse:not(.show) {
        display: none !important;
    }
    
    /* Statistics cards for print */
    .row .col-md-3 {
        width: 25% !important;
        float: left !important;
    }
    
    /* Print only elements */
    .print-only {
        display: block !important;
    }
}

.badge {
    font-size: 0.75em;
    padding: 0.375rem 0.75rem;
}

.table td {
    vertical-align: middle;
}

.table th {
    border-top: none;
    font-weight: 600;
}

.table-hover tbody tr:hover {
    background-color: rgba(0, 123, 255, 0.1);
}

.card-title {
    font-size: 1.5rem;
    font-weight: 600;
}

.bi-activity {
    color: #6f42c1;
}

.text-purple {
    color: #6f42c1 !important;
}

.alert-info {
    border-left: 4px solid #007bff;
}

.activity-cell {
    min-width: 120px;
}

.user-cell {
    min-width: 150px;
}

.detail-cell {
    min-width: 200px;
}

.date-cell {
    min-width: 120px;
}

.time-cell {
    min-width: 80px;
}
</style>

<?php require_once '../includes/footer.php'; ?>

<!-- Debug information (remove in production)
<?php
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    echo "<div class='alert alert-info'>";
    echo "<h5>Debug Information:</h5>";
    echo "<p><strong>Filter Activity Type:</strong> " . htmlspecialchars($filter_activity_type) . "</p>";
    echo "<p><strong>Filter User:</strong> " . htmlspecialchars($filter_user) . "</p>";
    echo "<p><strong>Filter Action:</strong> " . htmlspecialchars($filter_action) . "</p>";
    echo "<p><strong>Filter Date From:</strong> " . htmlspecialchars($filter_date_from) . "</p>";
    echo "<p><strong>Filter Date To:</strong> " . htmlspecialchars($filter_date_to) . "</p>";
    echo "<p><strong>Include System:</strong> " . ($include_system ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Include Borrow:</strong> " . ($include_borrow ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Include Return:</strong> " . ($include_return ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Include Attendance In:</strong> " . ($include_attendance_in ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Include Attendance Out:</strong> " . ($include_attendance_out ? 'Yes' : 'No') . "</p>";
    echo "<p><strong>Total Records:</strong> " . $total_records . "</p>";
    echo "</div>";
}
?> -->
