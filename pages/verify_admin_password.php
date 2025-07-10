<?php
session_start();
require_once '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get the JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['password']) || empty($input['password'])) {
    echo json_encode(['success' => false, 'message' => 'Password is required']);
    exit();
}

$password = $input['password'];
$user_id = $_SESSION['user_id'];

try {
    // Get the current user's password hash from database
    $stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit();
    }
    
    $user = $result->fetch_assoc();
    
    // Verify the password
    if (password_verify($password, $user['password'])) {
        // Log the activity
        $action = "Accessed Activity Logs with password verification";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $user_id, $action);
        $log_stmt->execute();
        
        echo json_encode(['success' => true, 'message' => 'Password verified successfully']);
    } else {
        // Log the failed attempt
        $action = "Failed Activity Logs access - incorrect password";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, timestamp) VALUES (?, ?, NOW())");
        $log_stmt->bind_param("is", $user_id, $action);
        $log_stmt->execute();
        
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
    
} catch (Exception $e) {
    error_log("Password verification error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred during verification']);
}
?>
