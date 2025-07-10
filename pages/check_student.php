<?php
// filepath: d:\xampp\htdocs\svcc-library\pages\check_student.php
require_once '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = trim($_POST['student_id']);
    
    // Get student info
    $stmt = $conn->prepare("SELECT lastname, firstname, middlename, type FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $student = $result->fetch_assoc();
        
        // Count borrowed books
        $count_stmt = $conn->prepare("SELECT COUNT(*) as borrowed_count FROM borrowed_books WHERE student_id = ? AND status = 'borrowed'");
        $count_stmt->bind_param("s", $student_id);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $borrowed_count = $count_result->fetch_assoc()['borrowed_count'];
        
        // Determine borrowing limits based on student type
        $student_type = $student['type'];
        $borrow_limit = 0;
        if ($student_type === 'Admin' || $student_type === 'Faculty') {
            $borrow_limit = 999; // No limit
        } elseif ($student_type === 'College' || $student_type === 'BasicEd') {
            $borrow_limit = 3; // Max 3 books
        }
        
        // Check if at limit or near limit
        $at_limit = ($borrowed_count >= $borrow_limit && $borrow_limit < 999);
        $near_limit = ($borrowed_count >= $borrow_limit - 1 && $borrow_limit < 999 && !$at_limit);
        
        $response = [
            'found' => true,
            'name' => trim($student['lastname'] . ', ' . $student['firstname'] . ' ' . $student['middlename']),
            'type' => $student['type'],
            'borrowed_count' => $borrowed_count,
            'limit' => $borrow_limit,
            'at_limit' => $at_limit,
            'near_limit' => $near_limit
        ];
    } else {
        $response = ['found' => false];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>