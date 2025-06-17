<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// require_once '../includes/header.php';
date_default_timezone_set('Asia/Manila');
?>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
    overflow: hidden;
}
body {
    background: linear-gradient(120deg, #800000 0%, #b22222 100%);
    min-height: 100vh;
    color: #fff;
}
.container.py-5 {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: center;
}
#reader {
    margin: 0 auto;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0,0,0,0.08);
    background: #fff;
    width: 300px;
    height: 300px;
    max-width: 100%;
    position: relative;
}
#reader video {
    width: 100% !important;
    height: 100% !important;
    object-fit: cover;
}
#result {
    min-height: 60px;
    margin-top: 24px;
    text-align: center;
    font-size: 1.3rem;
    font-weight: 500;
    background: none;
    color: inherit;
    border-radius: 10px;
    padding: 18px 12px;
    box-shadow: 0 2px 8px rgba(128,0,0,0.08);
    transition: background 0.3s, color 0.3s;
}
.animated-message {
    animation: popIn 0.5s;
}
@keyframes popIn {
    0% { transform: scale(0.7); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
}
.header-logo {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 24px;
}
.header-logo img {
    height: 64px;
    margin-right: 16px;
}
.header-logo span {
    font-size: 2rem;
    font-weight: bold;
    color: #fff;
    letter-spacing: 2px;
}
.time-ph {
    text-align: center;
    font-size: 1.2rem;
    margin-bottom: 20px;
    color: #ffd6d6;
}
.reminders {
    background: #fff0f0;
    color: #800000;
    border-radius: 10px;
    padding: 16px;
    margin: 0 auto 24px auto;
    max-width: 600px;
    font-size: 1.1rem;
    box-shadow: 0 2px 8px rgba(128,0,0,0.08);
}
</style>
<div class="container py-5">
    <div class="header-logo">
        <img src="../assets/images/svcclogo.png" alt="Library Logo">
        <span>SVCC Library</span>
    </div>
    <div class="time-ph" id="ph-time">
        <?php echo date('l, F j, Y - h:i:s A'); ?> (Asia/Manila)
    </div>
    <div class="row justify-content-center">
        <div id="reader"></div>
        <div id="result"></div>
    </div>
    <div class="reminders">
        <strong>Reminders:</strong>
        <ul>
            <li>Make sure your QR code is clear and not damaged.</li>
            <li>Hold your ID steady in front of the scanner.</li>
            <li>Wait for the confirmation before leaving the scanner area.</li>
            <li>Do not scan twice in quick succession.</li>
            <li>If you encounter issues, please approach the library staff.</li>
        </ul>
    </div>
</div>
<script>
function updateTime() {
    const timeDiv = document.getElementById('ph-time');
    if (!timeDiv) return;
    const now = new Date();
    // Format to 12-hour with AM/PM
    const options = { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric', 
        hour: '2-digit', 
        minute:'2-digit', 
        second:'2-digit', 
        hour12: true, 
        timeZone: 'Asia/Manila' 
    };
    timeDiv.innerHTML = now.toLocaleString('en-US', options) + " (Asia/Manila)";
}
setInterval(updateTime, 1000);
</script>
<script src="https://unpkg.com/html5-qrcode"></script>
<script>
let resultTimeout = null;
let html5QrcodeScanner = null; // <-- define globally

function showMessage(msg, color="#800000", bg="#fff0f0") {
    const resultDiv = document.getElementById('result');
    resultDiv.innerHTML = msg;
    resultDiv.style.color = color;
    resultDiv.style.background = bg;
    resultDiv.classList.add('animated-message');
    clearTimeout(resultTimeout);
    resultTimeout = setTimeout(() => {
        resultDiv.innerHTML = "";
        resultDiv.style.background = "none";
        resultDiv.style.color = "inherit";
    }, 5000);
}

function onScanSuccess(decodedText, decodedResult) {
    // Prevent multiple scans
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => {
            showMessage("Processing...", "#b22222");
            // Send AJAX to scan_process.php
            fetch('scan_process.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'student_id=' + encodeURIComponent(decodedText)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'in') {
                    showMessage("Welcome, " + data.name + "!<br>Time In: " + data.time, "#388e3c", "#e8f5e9");
                } else if (data.status === 'out') {
                    showMessage("Goodbye, " + data.name + "!<br>Time Out: " + data.time, "#1976d2", "#e3f2fd");
                } else {
                    showMessage(data.message, "#e63946", "#fff0f0");
                }
                setTimeout(() => {
                    html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 400, height: 400 } },
                        onScanSuccess
                    );
                }, 2500);
            })
            .catch(() => {
                showMessage("Network error.", "#e63946");
                setTimeout(() => {
                    html5QrcodeScanner.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 400, height: 400 } },
                        onScanSuccess
                    );
                }, 2500);
            });
        });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    html5QrcodeScanner = new Html5Qrcode("reader");
    html5QrcodeScanner.start(
        { facingMode: "environment" },
        { fps: 10, qrbox: { width: 200, height: 200 } },
        onScanSuccess
    ).catch(err => {
        showMessage("Camera error: " + err, "#e63946");
    });
});
</script>
<?php require_once '../includes/footer.php'; ?>