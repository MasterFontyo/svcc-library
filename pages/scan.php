<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SVCC Library Scanner</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        body {
            background: linear-gradient(120deg, #800000 0%, #b22222 100%);
            min-height: 100vh;
            color: #fff;
        }
        .container-fluid {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 20px;
        }
        #reader {
            margin: 0 auto;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.3);
            background: #fff;
            width: 100%;
            max-width: 400px;
            height: 300px;
        }
        #reader > div {
            border: none !important;
        }
        #reader video {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover;
            border-radius: 16px;
        }
        #reader canvas {
            display: none !important;
        }
        #result {
            min-height: 80px;
            margin-top: 24px;
            text-align: center;
            font-size: 1.4rem;
            font-weight: 600;
            background: none;
            color: inherit;
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        .animated-message {
            animation: popIn 0.6s ease-out;
        }
        @keyframes popIn {
            0% { 
                transform: scale(0.8) translateY(-20px); 
                opacity: 0; 
            }
            100% { 
                transform: scale(1) translateY(0); 
                opacity: 1; 
            }
        }
        .header-logo {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }
        .header-logo img {
            height: 80px;
            margin-right: 20px;
        }
        .header-logo span {
            font-size: 2.5rem;
            font-weight: bold;
            color: #fff;
            letter-spacing: 3px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        .time-ph {
            text-align: center;
            font-size: 1.3rem;
            margin-bottom: 25px;
            color: #ffd6d6;
            font-weight: 500;
        }
        .reminders {
            background: rgba(255,240,240,0.95);
            color: #800000;
            border-radius: 12px;
            padding: 20px;
            margin: 30px auto 0 auto;
            max-width: 500px;
            font-size: 1rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .scanner-status {
            text-align: center;
            margin-top: 15px;
            font-size: 1.1rem;
            color: #ffd6d6;
        }
        /* Fix for QR scanner UI elements */
        #reader__dashboard_section {
            display: none !important;
        }
        #reader__camera_selection {
            display: none !important;
        }
        #reader__scan_region {
            border: 3px solid #fff !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="header-logo">
            <img src="../assets/images/svcclogo.png" alt="Library Logo">
            <span>SVCC Library</span>
        </div>
        
        <div class="time-ph" id="ph-time">
            <?php echo date('l, F j, Y - h:i:s A'); ?> (Asia/Manila)
        </div>
        
        <div class="row justify-content-center">
            <div class="col-12 col-md-8 col-lg-6">
                <div id="reader"></div>
                <div class="scanner-status" id="scanner-status">
                    <span>Point your QR code at the camera</span>
                </div>
                <div id="result"></div>
            </div>
        </div>
        
        <div class="reminders">
            <strong>📋 Scanner Instructions:</strong>
            <ul style="margin-bottom: 0;">
                <li>Hold your QR code 6-12 inches from the camera</li>
                <li>Ensure good lighting on your QR code</li>
                <li>Keep the code steady and flat</li>
                <li>Wait for the green confirmation message</li>
                <li>Allow camera permissions when prompted</li>
            </ul>
        </div>
    </div>

    <script>
        function updateTime() {
            const timeDiv = document.getElementById('ph-time');
            if (!timeDiv) return;
            const now = new Date();
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

    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <script>
        let resultTimeout = null;
        let html5QrcodeScanner = null;
        let isProcessing = false;

        function showMessage(msg, color="#800000", bg="#fff0f0") {
            const resultDiv = document.getElementById('result');
            resultDiv.innerHTML = msg;
            resultDiv.style.color = color;
            resultDiv.style.background = bg;
            resultDiv.style.border = `2px solid ${color}`;
            resultDiv.classList.add('animated-message');
            
            clearTimeout(resultTimeout);
            resultTimeout = setTimeout(() => {
                resultDiv.innerHTML = "";
                resultDiv.style.background = "none";
                resultDiv.style.color = "inherit";
                resultDiv.style.border = "none";
                resultDiv.classList.remove('animated-message');
            }, 4000);
        }

        function updateScannerStatus(status) {
            const statusDiv = document.getElementById('scanner-status');
            if (statusDiv) {
                statusDiv.innerHTML = status;
            }
        }

        function onScanSuccess(decodedText, decodedResult) {
            // Prevent multiple rapid scans
            if (isProcessing) {
                return;
            }
            
            isProcessing = true;
            updateScannerStatus("🔄 Processing scan...");
            
            // Stop the scanner temporarily
            if (html5QrcodeScanner) {
                html5QrcodeScanner.pause(true);
            }
            
            showMessage("🔄 Processing...", "#b22222", "#fff3e0");
            
            // Send AJAX to scan_process.php
            fetch('scan_process.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'student_id=' + encodeURIComponent(decodedText)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'in') {
                    let message = "Welcome, " + data.name + "!<br> Time In: " + data.time;
                    if (data.type) {
                        message += "<br>👤 Type: " + data.type;
                    }
                    showMessage(message, "#388e3c", "#e8f5e9");
                    updateScannerStatus("✅ Scan successful - Time In recorded");
                } else if (data.status === 'out') {
                    let message = "👋 Goodbye, " + data.name + "!<br> Time Out: " + data.time;
                    if (data.auto) {
                        message += "<br>🕐 " + data.message;
                    }
                    showMessage(message, "#1976d2", "#e3f2fd");
                    updateScannerStatus("✅ Scan successful - Time Out recorded");
                } else {
                    showMessage("❌ " + data.message, "#e63946", "#ffebee");
                    updateScannerStatus("❌ Scan failed - " + data.message);
                }
                
                // Resume scanning after delay
                setTimeout(() => {
                    isProcessing = false;
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.resume();
                        updateScannerStatus("Ready to scan next QR code");
                    }
                }, 3000);
            })
            .catch(error => {
                console.error('Scan error:', error);
                showMessage("❌ Network error. Please try again.", "#e63946", "#ffebee");
                updateScannerStatus("❌ Network error - Please try again");
                
                // Resume scanning after error
                setTimeout(() => {
                    isProcessing = false;
                    if (html5QrcodeScanner) {
                        html5QrcodeScanner.resume();
                        updateScannerStatus("Ready to scan QR code");
                    }
                }, 2000);
            });
        }

        function onScanFailure(error) {
            // Ignore scan failures - they happen constantly while scanning
            // console.warn('Scan failure:', error);
        }

        // Initialize scanner when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateScannerStatus("🔄 Starting camera...");
            
            html5QrcodeScanner = new Html5Qrcode("reader");
            
            // Get camera devices
            Html5Qrcode.getCameras().then(devices => {
                if (devices && devices.length) {
                    // Prefer back camera if available
                    let cameraId = devices[0].id;
                    if (devices.length > 1) {
                        // Look for back camera
                        const backCamera = devices.find(device => 
                            device.label.toLowerCase().includes('back') || 
                            device.label.toLowerCase().includes('rear') ||
                            device.label.toLowerCase().includes('environment')
                        );
                        if (backCamera) {
                            cameraId = backCamera.id;
                        }
                    }
                    
                    // Start scanning
                    html5QrcodeScanner.start(
                        cameraId,
                        {
                            fps: 10,
                            qrbox: { width: 250, height: 250 },
                            aspectRatio: 1.0,
                            disableFlip: false
                        },
                        onScanSuccess,
                        onScanFailure
                    ).then(() => {
                        updateScannerStatus("Camera ready - Point QR code here");
                    }).catch(err => {
                        console.error('Camera start error:', err);
                        updateScannerStatus("❌ Camera error: " + err);
                        showMessage("❌ Camera error: " + err, "#e63946", "#ffebee");
                        
                        // Fallback: try with facingMode constraint
                        setTimeout(() => {
                            html5QrcodeScanner.start(
                                { facingMode: "environment" },
                                {
                                    fps: 10,
                                    qrbox: { width: 250, height: 250 }
                                },
                                onScanSuccess,
                                onScanFailure
                            ).catch(fallbackErr => {
                                updateScannerStatus("❌ Camera not available");
                                showMessage("❌ Camera access denied or not available.<br>Please check permissions.", "#e63946", "#ffebee");
                            });
                        }, 1000);
                    });
                }
            }).catch(err => {
                console.error('Get cameras error:', err);
                updateScannerStatus("❌ Cannot access camera");
                showMessage("❌ Cannot access camera devices.<br>Please check permissions.", "#e63946", "#ffebee");
            });
        });

        // Cleanup on page unload
        window.addEventListener('beforeunload', function() {
            if (html5QrcodeScanner) {
                html5QrcodeScanner.stop().catch(err => console.error('Stop error:', err));
            }
        });
    </script>
</body>
</html>