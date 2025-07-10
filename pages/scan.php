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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
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
        
        /* Admin Manual Entry Styles */
        .admin-controls {
            text-align: center;
            margin-top: 20px;
        }
        
        .btn-admin {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.5);
            color: #fff;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-admin:hover {
            background: rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.8);
            color: #fff;
            transform: translateY(-2px);
        }
        
        .modal-content {
            border-radius: 12px;
            border: none;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #800000, #b22222);
            color: white;
            border-radius: 12px 12px 0 0;
        }
        
        .form-control:focus {
            border-color: #800000;
            box-shadow: 0 0 0 0.2rem rgba(128, 0, 0, 0.25);
        }
        
        .btn-maroon {
            background-color: #800000;
            border-color: #800000;
            color: white;
        }
        
        .btn-maroon:hover {
            background-color: #660000;
            border-color: #660000;
            color: white;
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
        <!-- Admin Manual Entry Button -->
        <div class="admin-controls">
            <button type="button" class="btn btn-admin" data-bs-toggle="modal" data-bs-target="#adminModal">
                🔐 Admin Manual Entry
            </button>
        </div>
    </div>

    <!-- Admin Password Modal -->
    <div class="modal fade" id="adminModal" tabindex="-1" aria-labelledby="adminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminModalLabel">
                        <i class="bi bi-shield-lock"></i> Admin Verification
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="password-step">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Enter your admin password to access manual entry.
                        </div>
                        <div class="mb-3">
                            <label for="adminPassword" class="form-label">Admin Password</label>
                            <input type="password" class="form-control" id="adminPassword" placeholder="Enter admin password">
                            <div class="invalid-feedback" id="passwordError"></div>
                        </div>
                        <div class="d-grid">
                            <button type="button" class="btn btn-maroon" onclick="verifyAdminPassword()">
                                <i class="bi bi-unlock"></i> Verify Password
                            </button>
                        </div>
                    </div>
                    
                    <div id="manual-entry-step" style="display: none;">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle"></i> Admin verified! Enter student number manually.
                        </div>
                        <div class="mb-3">
                            <label for="studentNumber" class="form-label">Student Number</label>
                            <input type="text" class="form-control" id="studentNumber" placeholder="Enter student number (e.g., AY2025-12345)">
                            <div class="invalid-feedback" id="studentNumberError"></div>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="button" class="btn btn-maroon" onclick="processManualEntry()">
                                <i class="bi bi-person-check"></i> Process Entry
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="resetModal()">
                                <i class="bi bi-arrow-left"></i> Back to Password
                            </button>
                        </div>
                    </div>
                </div>
            </div>
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
        
        // Admin Modal Functions
        function verifyAdminPassword() {
            const password = document.getElementById('adminPassword').value;
            const passwordInput = document.getElementById('adminPassword');
            const errorDiv = document.getElementById('passwordError');
            
            if (!password) {
                passwordInput.classList.add('is-invalid');
                errorDiv.textContent = 'Password is required.';
                return;
            }
            
            // Clear previous errors
            passwordInput.classList.remove('is-invalid');
            errorDiv.textContent = '';
            
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
                    // Password is correct, show manual entry step
                    document.getElementById('password-step').style.display = 'none';
                    document.getElementById('manual-entry-step').style.display = 'block';
                    document.getElementById('studentNumber').focus();
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
            });
        }
        
        function processManualEntry() {
            const studentNumber = document.getElementById('studentNumber').value.trim();
            const studentInput = document.getElementById('studentNumber');
            const errorDiv = document.getElementById('studentNumberError');
            
            if (!studentNumber) {
                studentInput.classList.add('is-invalid');
                errorDiv.textContent = 'Student number is required.';
                return;
            }
            
            // Clear previous errors
            studentInput.classList.remove('is-invalid');
            errorDiv.textContent = '';
            
            // Disable the button and show processing
            const processBtn = event.target;
            const originalText = processBtn.innerHTML;
            processBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Processing...';
            processBtn.disabled = true;
            
            // Send to scan_process.php (same as QR scan)
            fetch('scan_process.php', {
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'student_id=' + encodeURIComponent(studentNumber)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                // Close modal first
                const modal = bootstrap.Modal.getInstance(document.getElementById('adminModal'));
                modal.hide();
                
                // Show result in main page
                if (data.status === 'in') {
                    let message = "✅ Manual Entry - Welcome, " + data.name + "!<br> Time In: " + data.time;
                    if (data.type) {
                        message += "<br>👤 Type: " + data.type;
                    }
                    showMessage(message, "#388e3c", "#e8f5e9");
                    updateScannerStatus("✅ Manual entry successful - Time In recorded");
                } else if (data.status === 'out') {
                    let message = "✅ Manual Entry - 👋 Goodbye, " + data.name + "!<br> Time Out: " + data.time;
                    if (data.auto) {
                        message += "<br>🕐 " + data.message;
                    }
                    showMessage(message, "#1976d2", "#e3f2fd");
                    updateScannerStatus("✅ Manual entry successful - Time Out recorded");
                } else {
                    showMessage("❌ " + data.message, "#e63946", "#ffebee");
                    updateScannerStatus("❌ Manual entry failed - " + data.message);
                }
                
                // Reset modal after successful processing
                setTimeout(() => {
                    resetModal();
                }, 1000);
            })
            .catch(error => {
                console.error('Manual entry error:', error);
                studentInput.classList.add('is-invalid');
                errorDiv.textContent = 'Network error. Please try again.';
            })
            .finally(() => {
                // Reset button state
                processBtn.innerHTML = originalText;
                processBtn.disabled = false;
            });
        }
        
        function resetModal() {
            // Reset to password step
            document.getElementById('password-step').style.display = 'block';
            document.getElementById('manual-entry-step').style.display = 'none';
            
            // Clear all inputs
            document.getElementById('adminPassword').value = '';
            document.getElementById('studentNumber').value = '';
            
            // Clear all error states
            document.getElementById('adminPassword').classList.remove('is-invalid');
            document.getElementById('studentNumber').classList.remove('is-invalid');
            document.getElementById('passwordError').textContent = '';
            document.getElementById('studentNumberError').textContent = '';
        }
        
        // Handle Enter key in password field
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('adminPassword').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    verifyAdminPassword();
                }
            });
            
            document.getElementById('studentNumber').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    processManualEntry();
                }
            });
            
            // Reset modal when it's closed
            document.getElementById('adminModal').addEventListener('hidden.bs.modal', function() {
                resetModal();
            });
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
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