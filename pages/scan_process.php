<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../includes/db.php';

date_default_timezone_set('Asia/Manila'); // Set PHP timezone to Philippines

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = $_POST['student_id'];
    $stmt = $conn->prepare("SELECT lastname, firstname FROM students WHERE student_id=?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $stmt->bind_result($lastname, $firstname);
    if ($stmt->fetch()) {
        $name = $firstname . ' ' . $lastname;
        $stmt->close();
        // Check for open attendance (no time_out)
        $stmt2 = $conn->prepare("SELECT id, time_in FROM attendance WHERE student_id=? AND time_out IS NULL ORDER BY time_in DESC LIMIT 1");
        $stmt2->bind_param("s", $student_id);
        $stmt2->execute();
        $stmt2->bind_result($att_id, $time_in);
        if ($stmt2->fetch()) {
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
                $stmt3->execute();
                $stmt3->close();
                echo json_encode(['status'=>'out','name'=>$name,'time'=>$auto_time_out, 'auto'=>true]);
                $conn->close();
                exit;
            }

            if (strtotime($now) - strtotime($time_in) < $minInterval) {
                echo json_encode(['status'=>'error','message'=>'Please wait before scanning out.']);
                $conn->close();
                exit;
            }
            $stmt3 = $conn->prepare("UPDATE attendance SET time_out=?, date_out=?, time_out_only=? WHERE id=?");
            $stmt3->bind_param("sssi", $now, $now_date, $now_time, $att_id);
            $stmt3->execute();
            $stmt3->close();
            echo json_encode(['status'=>'out','name'=>$name,'time'=>$now]);
        } else {
            // Time in
            $stmt2->close();
            $now = date('Y-m-d H:i:s');
            $now_date = date('Y-m-d');
            $now_time = date('H:i:s');
            $stmt4 = $conn->prepare("INSERT INTO attendance (student_id, date_in, time_in_only, time_in) VALUES (?,?,?,?)");
            $stmt4->bind_param("ssss", $student_id, $now_date, $now_time, $now);
            $stmt4->execute();
            $stmt4->close();
            echo json_encode(['status'=>'in','name'=>$name,'time'=>$now]);
        }
    } else {
        echo json_encode(['status'=>'error','message'=>'Student not found.']);
    }
    $conn->close();
    exit;
}
echo json_encode(['status'=>'error','message'=>'Invalid request.']);