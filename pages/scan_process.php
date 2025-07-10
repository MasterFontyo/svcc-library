<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../includes/db.php';

date_default_timezone_set('Asia/Manila'); // Set PHP timezone to Philippines

// Set proper JSON header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = trim($_POST['student_id']); // Add trim to remove whitespace
    
    $stmt = $conn->prepare("SELECT lastname, firstname, middlename, type FROM students WHERE student_id=?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->bind_result($lastname, $firstname, $middlename, $type);
    
    if ($stmt->fetch()) {
        $name = $firstname . ' ' . $lastname;
        $stmt->close();
        
        // Check for open attendance (no time_out)
        $stmt2 = $conn->prepare("SELECT id, time_in FROM attendance WHERE student_id=? AND time_out IS NULL ORDER BY time_in DESC LIMIT 1");
        $stmt2->bind_param("s", $student_id);
        $stmt2->execute();
        $stmt2->bind_result($att_id, $time_in);
        
        if ($stmt2->fetch()) {
            // Student has an active session - TIME OUT
            $stmt2->close();
            $now = date('Y-m-d H:i:s');
            $now_date = date('Y-m-d');
            $now_time = date('H:i:s');
            $minInterval = 60; // seconds

            // --- AUTO TIME OUT after 8pm PH time ---
            $eight_pm = strtotime($now_date . ' 20:00:00');
            $current_time = strtotime($now);
            
            if ($current_time >= $eight_pm) {
                // Auto time out at 8:00 PM
                $auto_time_out = $now_date . ' 20:00:00';
                $auto_time_out_only = '20:00:00';
                $stmt3 = $conn->prepare("UPDATE attendance SET time_out=?, date_out=?, time_out_only=? WHERE id=?");
                $stmt3->bind_param("sssi", $auto_time_out, $now_date, $auto_time_out_only, $att_id);
                
                if ($stmt3->execute()) {
                    $stmt3->close();
                    echo json_encode([
                        'status' => 'out',
                        'name' => $name,
                        'time' => date('M d, Y h:i:s A', strtotime($auto_time_out)),
                        'auto' => true,
                        'message' => 'Auto time-out at 8:00 PM'
                    ]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Failed to record auto time-out']);
                }
                $conn->close();
                exit;
            }

            // Check minimum interval between time in and time out
            if (strtotime($now) - strtotime($time_in) < $minInterval) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Please wait ' . ($minInterval - (strtotime($now) - strtotime($time_in))) . ' seconds before scanning out.'
                ]);
                $conn->close();
                exit;
            }
            
            // Regular time out
            $stmt3 = $conn->prepare("UPDATE attendance SET time_out=?, date_out=?, time_out_only=? WHERE id=?");
            $stmt3->bind_param("sssi", $now, $now_date, $now_time, $att_id);
            
            if ($stmt3->execute()) {
                $stmt3->close();
                echo json_encode([
                    'status' => 'out',
                    'name' => $name,
                    'time' => date('M d, Y h:i:s A', strtotime($now)),
                    'type' => $type
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to record time out']);
            }
            
        } else {
            // No active session - TIME IN
            $stmt2->close();
            
            // Check if it's too late to time in (after 8 PM)
            $now = date('Y-m-d H:i:s');
            $now_date = date('Y-m-d');
            $now_time = date('H:i:s');
            $eight_pm = strtotime($now_date . ' 20:00:00');
            $current_time = strtotime($now);
            
            if ($current_time >= $eight_pm) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Library is closed. Time-in not allowed after 8:00 PM.'
                ]);
                $conn->close();
                exit;
            }
            
            // Check if already timed in today (prevent multiple time-ins on same day)
            $stmt_check = $conn->prepare("SELECT id FROM attendance WHERE student_id=? AND date_in=? AND time_out IS NOT NULL");
            $stmt_check->bind_param("ss", $student_id, $now_date);
            $stmt_check->execute();
            $existing_result = $stmt_check->get_result();
            $stmt_check->close();
            
            // Allow time in
            $stmt4 = $conn->prepare("INSERT INTO attendance (student_id, date_in, time_in_only, time_in) VALUES (?,?,?,?)");
            $stmt4->bind_param("ssss", $student_id, $now_date, $now_time, $now);
            
            if ($stmt4->execute()) {
                $stmt4->close();
                echo json_encode([
                    'status' => 'in',
                    'name' => $name,
                    'time' => date('M d, Y h:i:s A', strtotime($now)),
                    'type' => $type
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to record time in']);
            }
        }
    } else {
        $stmt->close();
        echo json_encode(['status' => 'error', 'message' => 'Student ID not found in database']);
    }
    $conn->close();
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request method or missing student ID']);
?>