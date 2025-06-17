<?php
require_once '../includes/db.php';

$student_id = isset($_GET['student_id']) ? trim($_GET['student_id']) : null;
$student = null;

if ($student_id) {
    $stmt = $conn->prepare("SELECT * FROM students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $student = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Printable Library Card</title>
    <style>
        @media print {
            body { background: none; }
            .no-print { display: none; }
            .card-sheet { box-shadow: none !important; }
        }
        body {
            background: #f7f7f7;
            font-family: 'Segoe UI', Arial, sans-serif;
        }
        .card-sheet {
            width: 35cm; /* 2 x A6 width */
            height: 8cm; /* A6 height */
            margin: 2rem auto;
            display: flex;
            flex-direction: row;
            box-shadow: 0 4px 24px rgba(44,0,0,0.12);
            border-radius: 28px;
            overflow: hidden;
            background: #fff;
            gap: .2cm;
            padding: 0.6cm;
        }
        .card-side {
            width: 14.8cm;
            height: 10.5cm;
            min-width: 14.8cm;
            min-height: 8cm;
            max-width: 10.8cm;
            max-height: 9cm;
            position: relative;
            padding: 0;
            border-radius: 25px;
            overflow: hidden;
            background: #fff;
            /* box-shadow: 0 2px 12px #80000018; */
            border: 3px solid #80000022;
            display: flex;
            flex-direction: column;
            justify-content: stretch;
            box-sizing: border-box;
        }
        .front {
            background: linear-gradient(135deg, #800000 0%, #fff 100%);
            color: #800000;
            flex-direction: row;
            align-items: stretch;
            height: 100%;
            border-radius: 22px;
        }
        .front-left {
            width: 20%;
            background: #fff;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border-right: 2px solid #80000022;
            padding: 0.7cm 0.2cm 0.7cm 0.7cm;
            border-top-left-radius: 22px;
            border-bottom-left-radius: 22px;
        }
        .front-left img.logo {
            width: 4cm;
            height: 4cm;
            object-fit: contain;
            border-radius: 50%;
            /* border: 3px solid #80000033; */
            /* background: #fff; */
            /* box-shadow: 0 2px 8px #80000022; */
        }
        .front-left img.qr {
            width: 2.7cm;
            height: 2.7cm;
            object-fit: contain;
            border: 2px solid #80000055;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 2px 8px #80000022;
        }
        .front-right {
            width: 80%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 0.9cm 0.9cm 0.9cm 0.7cm;
            border-top-right-radius: 22px;
            border-bottom-right-radius: 22px;
        }
        .school-name {
            font-size: 1.18em;
            font-weight: bold;
            letter-spacing: 1.5px;
            margin-bottom: 0.18cm;
            color: #fff;
        }
        .card-title {
            font-size: 1.08em;
            font-weight: 600;
            margin-bottom: 0.5cm;
            color: #fff;
            background: #800000;
            border-radius: 12px;
            padding: 0.13cm 0.5cm;
            display: inline-block;
            box-shadow: 0 2px 8px #80000022;
            letter-spacing: 1.2px;
        }
        .student-details {
            font-size: 1.04em;
            background: #fff;
            border-radius: 14px;
            padding: 0.5cm 0.7cm;
            box-shadow: 0 2px 8px #80000011;
            border: 1.5px solid #80000022;
            margin-top: 0.2cm;
        }
        .student-details div {
            margin-bottom: 0.13cm;
        }
        .student-details strong {
            color: #800000;
            min-width: 2.2cm;
            display: inline-block;
        }
        .back {
            background: linear-gradient(135deg, #fff 70%, #80000011 100%);
            color: #800000;
            display: flex;
            flex-direction: column;
            /* justify-content: space-between; */
            align-items: stretch;
            padding: .7cm .7cm .5cm .7cm;
            font-size: .7em;
            border-radius: 22px;
            height: 100%;
            box-sizing: border-box;
        }
        .reminders {
            margin-top: 0.2cm;
            margin-bottom: 0cm;
            background: #fff;
            border-radius: 14px;
            padding: 0.5cm 0.7cm;
            box-shadow: 0 2px 8px #80000011;
            border: 1.5px solid #80000022;
        }
        .reminders-title {
            font-weight: bold;
            font-size: 1.13em;
            margin-bottom: 0.3cm;
            color: #800000;
            letter-spacing: 1px;
        }
        .reminders-list {
            margin: 0 0 0 .5em;
            padding: 0;
        }
        .reminders-list li {
            margin-bottom: 0.12cm;
        }
        .sign-area {
            margin-top: auto;
            text-align: center;
            padding-top: 0cm;
        }
        .sign-line {
            border-bottom: 2px solid #800000;
            width: 7cm;
            margin: 0 auto 0.2cm auto;
            height: 0.7cm;
            border-radius: 8px;
        }
        .sign-label {
            font-size: 1em;
            color: #800000;
            letter-spacing: 1px;
        }
        .no-print {
            margin: 1rem auto;
            text-align: center;
        }
        @page {
            size: A6 landscape;
            margin: 0;
        }
    </style>
    <script>
        window.onload = function() {
            window.print();
        }
    </script>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" style="background:#800000;color:#fff;padding:8px 24px;border:none;border-radius:8px;font-size:1em;cursor:pointer;box-shadow:0 2px 8px #80000022;">Print Card</button>
    </div>
    <div class="card-sheet">
        <!-- FRONT -->
        <div class="card-side front">
            <div class="front-left">
                <img src="../assets/images/svcclogo.png" alt="SVCC Logo" class="logo">
                <?php if (!empty($student['qr_path']) && file_exists('../' . $student['qr_path'])): ?>
                    <img src="../<?= htmlspecialchars($student['qr_path']) ?>" alt="QR Code" class="qr">
                <?php else: ?>
                    <div style="width:2.7cm;height:2.7cm;display:flex;align-items:center;justify-content:center;background:#eee;border-radius:18px;color:#800000;font-size:0.9em;text-align:center;box-shadow:0 2px 8px #80000022;">No QR<br>Available</div>
                <?php endif; ?>
            </div>
            <div class="front-right">
                <div class="school-name">ST. VINCENT COLLEGE OF CABUYAO</div>
                <div class="card-title">LIBRARY CARD</div>
                <div class="student-details">
                    <div><strong>ID:</strong> <?= htmlspecialchars($student['student_id']) ?></div>
                    <div><strong>Name:</strong> <?= htmlspecialchars($student['lastname']) ?>, <?= htmlspecialchars($student['firstname']) ?> <?= htmlspecialchars($student['middlename']) ?></div>
                    <div><strong>Course:</strong> <?= htmlspecialchars($student['course']) ?></div>
                    <div><strong>Year:</strong> <?= htmlspecialchars($student['year_level']) ?></div>
                </div>
            </div>
        </div>
        <!-- BACK -->
        <div class="card-side back">
            <div class="reminders">
                <div class="reminders-title">Library Card & Usage Reminders</div>
                <ul class="reminders-list">
                    <li>Always bring your library card when entering the library.</li>
                    <li>This card is non-transferable and must be presented when borrowing books.</li>
                    <li>Report lost cards immediately to the librarian.</li>
                    <li>Observe silence and proper conduct inside the library.</li>
                    <li>Return borrowed books on or before the due date.</li>
                </ul>
            </div>
            <div class="sign-area">
                <div class="sign-line"></div>
                <div class="sign-label">Librarian</div>
            </div>
        </div>
    </div>
    <script>
        // Flip to back on print (optional, for double-sided print preview)
        // You may add a button to toggle .front/.back display for preview if needed.
    </script>
</body>
</html>