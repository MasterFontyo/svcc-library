<?php
// Activity Logger Helper Functions
// Include this file in pages that need to log activities

/**
 * Log user activity to the database
 * @param mysqli $conn Database connection
 * @param int $user_id User ID (optional, uses session if not provided)
 * @param string $action Action description
 * @return bool Success status
 */
function logActivity($conn, $user_id = null, $action = '') {
    try {
        // Use session user_id if not provided
        if ($user_id === null && isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
        }
        
        // Prepare and execute the insert statement
        $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $stmt->bind_param("is", $user_id, $action);
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        error_log("Activity logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log user login activity
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $username Username
 * @return bool Success status
 */
function logLogin($conn, $user_id, $username) {
    $action = "User login: " . $username;
    return logActivity($conn, $user_id, $action);
}

/**
 * Log user logout activity
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $username Username
 * @return bool Success status
 */
function logLogout($conn, $user_id, $username) {
    $action = "User logout: " . $username;
    return logActivity($conn, $user_id, $action);
}

/**
 * Log book-related activity
 * @param mysqli $conn Database connection
 * @param string $activity_type Type of activity (add, edit, delete, borrow, return)
 * @param string $book_title Book title
 * @param int $user_id User ID (optional)
 * @return bool Success status
 */
function logBookActivity($conn, $activity_type, $book_title, $user_id = null) {
    $action = "Book " . $activity_type . ": " . $book_title;
    return logActivity($conn, $user_id, $action);
}

/**
 * Log student-related activity
 * @param mysqli $conn Database connection
 * @param string $activity_type Type of activity (add, edit, delete)
 * @param string $student_id Student ID
 * @param string $student_name Student name
 * @param int $user_id User ID (optional)
 * @return bool Success status
 */
function logStudentActivity($conn, $activity_type, $student_id, $student_name, $user_id = null) {
    $action = "Student " . $activity_type . ": " . $student_name . " (ID: " . $student_id . ")";
    return logActivity($conn, $user_id, $action);
}

/**
 * Log event-related activity
 * @param mysqli $conn Database connection
 * @param string $activity_type Type of activity (add, edit, delete)
 * @param string $event_title Event title
 * @param int $user_id User ID (optional)
 * @return bool Success status
 */
function logEventActivity($conn, $activity_type, $event_title, $user_id = null) {
    $action = "Event " . $activity_type . ": " . $event_title;
    return logActivity($conn, $user_id, $action);
}

/**
 * Log borrow/return activity
 * @param mysqli $conn Database connection
 * @param string $activity_type 'borrow' or 'return'
 * @param string $student_id Student ID
 * @param string $book_title Book title
 * @param int $user_id User ID (optional)
 * @return bool Success status
 */
function logBorrowReturnActivity($conn, $activity_type, $student_id, $book_title, $user_id = null) {
    $action = "Book " . $activity_type . " - Student: " . $student_id . ", Book: " . $book_title;
    return logActivity($conn, $user_id, $action);
}

/**
 * Log system activity (non-user specific)
 * @param mysqli $conn Database connection
 * @param string $action Action description
 * @return bool Success status
 */
function logSystemActivity($conn, $action) {
    return logActivity($conn, null, "System: " . $action);
}

/**
 * Log admin activity
 * @param mysqli $conn Database connection
 * @param string $activity_type Type of activity
 * @param string $target_username Target username
 * @param int $user_id User ID (optional)
 * @return bool Success status
 */
function logAdminActivity($conn, $activity_type, $target_username, $user_id = null) {
    $action = "Admin " . $activity_type . ": " . $target_username;
    return logActivity($conn, $user_id, $action);
}

/**
 * Log report generation activity
 * @param mysqli $conn Database connection
 * @param string $report_type Type of report
 * @param int $user_id User ID (optional)
 * @return bool Success status
 */
function logReportActivity($conn, $report_type, $user_id = null) {
    $action = "Report generated: " . $report_type;
    return logActivity($conn, $user_id, $action);
}

/**
 * Clean up old activity logs (older than specified days)
 * @param mysqli $conn Database connection
 * @param int $days_to_keep Number of days to keep logs
 * @return bool Success status
 */
function cleanupActivityLogs($conn, $days_to_keep = 90) {
    try {
        $stmt = $conn->prepare("DELETE FROM activity_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->bind_param("i", $days_to_keep);
        
        $result = $stmt->execute();
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        
        if ($result && $affected_rows > 0) {
            logSystemActivity($conn, "Cleaned up " . $affected_rows . " old activity logs");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Activity log cleanup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent activities for a specific user
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param int $limit Number of activities to retrieve
 * @return array Array of activities
 */
function getRecentUserActivities($conn, $user_id, $limit = 10) {
    try {
        $stmt = $conn->prepare("
            SELECT action, timestamp 
            FROM activity_logs 
            WHERE user_id = ? 
            ORDER BY timestamp DESC 
            LIMIT ?
        ");
        $stmt->bind_param("ii", $user_id, $limit);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $activities = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $activities;
    } catch (Exception $e) {
        error_log("Error getting recent activities: " . $e->getMessage());
        return [];
    }
}

/**
 * Get activity statistics
 * @param mysqli $conn Database connection
 * @return array Array of statistics
 */
function getActivityStatistics($conn) {
    try {
        $stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_activities,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(CASE WHEN DATE(timestamp) = CURDATE() THEN 1 END) as today_activities,
                COUNT(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_activities,
                COUNT(CASE WHEN timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as month_activities
            FROM activity_logs
        ");
        $stmt->execute();
        
        $result = $stmt->get_result();
        $stats = $result->fetch_assoc();
        $stmt->close();
        
        return $stats;
    } catch (Exception $e) {
        error_log("Error getting activity statistics: " . $e->getMessage());
        return [];
    }
}
?>
