<?php
/**
 * QR Code Attendance Scanner for Course Coordinators
 * NIELIT Bhubaneswar - Attendance Management System
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/attendance_qr_helper.php';

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin'];
$admin_name = $_SESSION['admin_name'] ?? 'Course Coordinator';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create_session':
            $session_data = [
                'session_name' => $_POST['session_name'] ?? '',
                'course_id' => $_POST['course_id'] ?? 0,
                'course_name' => $_POST['course_name'] ?? '',
                'subject' => $_POST['subject'] ?? '',
                'date' => $_POST['date'] ?? date('Y-m-d'),
                'start_time' => $_POST['start_time'] ?? '',
                'end_time' => $_POST['end_time'] ?? '',
                'coordinator_id' => $admin_id,
                'coordinator_name' => $admin_name
            ];
            
            $result = createAttendanceSession($session_data, $conn);
            echo json_encode($result);
            exit;
            
        case 'activate_session':
            $session_id = $_POST['session_id'] ?? 0;
            $success = activateAttendanceSession($session_id, $admin_id, $conn);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'deactivate_session':
            $session_id = $_POST['session_id'] ?? 0;
            $success = deactivateAttendanceSession($session_id, $admin_id, $conn);
            echo json_encode(['success' => $success]);
            exit;
            
        case 'process_qr_scan':
            $qr_data = $_POST['qr_data'] ?? '';
            $session_id = $_POST['session_id'] ?? 0;
            
            // Use enhanced IN/OUT processing
            require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
            $result = processInOutAttendanceQRScan($qr_data, $session_id, $admin_id, $conn);
            echo json_encode($result);
            exit;
            
        case 'get_session_stats':
            $session_id = $_POST['session_id'] ?? 0;
            require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
            $stats = getAttendanceStatistics($session_id, $conn);
            echo json_encode($stats);
            exit;
            
        case 'get_session_attendance_list':
            $session_id = $_POST['session_id'] ?? 0;
            require_once __DIR__ . '/../includes/attendance_in_out_helper.php';
            $attendance_list = getSessionAttendanceList($session_id, $conn);
            echo json_encode(['success' => true, 'data' => $attendance_list]);
            exit;
    }
}

// Get active sessions
$active_sessions = getActiveAttendanceSessions($admin_id, $conn);

// Get available courses for session creation
$courses_query = "SELECT id, course_name, course_code FROM courses WHERE status = 'active' ORDER BY course_name";
$courses_result = $conn->query($courses_query);
$courses = $courses_result ? $courses_result->fetch_all(MYSQLI_ASSOC) : [];

// Debug: Check if we have courses
if (empty($courses)) {
    // If no active courses, get all courses
    $courses_query_all = "SELECT id, course_name, course_code FROM courses ORDER BY course_name";
    $courses_result_all = $conn->query($courses_query_all);
    $courses = $courses_result_all ? $courses_result_all->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Attendance Scanner - NIELIT Bhubaneswar</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Admin Theme CSS -->
    <link href="../assets/css/admin-theme.css" rel="stylesheet">
    
    <style>
        .session-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }

        .session-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .session-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }

        .badge-success {
            background-color: #28a745;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        #qr-reader {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            min-height: 300px;
        }

        .scan-result {
            padding: 8px;
            margin-bottom: 8px;
            border-radius: 4px;
            font-size: 0.9em;
        }

        .scan-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .scan-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .scan-duplicate {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        /* Ensure dropdown is properly styled */
        .form-select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m1 6 7 7 7-7'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 16px 12px;
            cursor: pointer;
        }

        .form-select:focus {
            border-color: #86b7fe;
            outline: 0;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-graduation-cap"></i> NIELIT Admin
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <a class="nav-link" href="logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>


<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-qrcode"></i> QR Attendance Scanner</h2>
            <p class="text-muted">Scan student QR codes to mark attendance for your classes</p>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-md-6">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                <i class="fas fa-plus"></i> Create New Session
            </button>
        </div>
        <div class="col-md-6 text-end">
            <div class="btn-group">
                <button class="btn btn-outline-secondary" onclick="refreshSessions()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Active Sessions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-calendar-alt"></i> Your Attendance Sessions</h5>
                </div>
                <div class="card-body">
                    <?php if (count($active_sessions) > 0): ?>
                        <div class="row" id="sessionsContainer">
                            <?php foreach ($active_sessions as $session): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card session-card" data-session-id="<?php echo $session['id']; ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h6 class="mb-0"><?php echo htmlspecialchars($session['session_name']); ?></h6>
                                            <span class="badge badge-<?php echo $session['status'] === 'active' ? 'success' : 'warning'; ?>">
                                                <?php echo ucfirst($session['status']); ?>
                                            </span>
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-1"><strong>Course:</strong> <?php echo htmlspecialchars($session['course_name']); ?></p>
                                            <p class="mb-1"><strong>Subject:</strong> <?php echo htmlspecialchars($session['subject']); ?></p>
                                            <p class="mb-1"><strong>Date:</strong> <?php echo date('d M Y', strtotime($session['date'])); ?></p>
                                            <p class="mb-3"><strong>Time:</strong> <?php echo date('h:i A', strtotime($session['start_time'])) . ' - ' . date('h:i A', strtotime($session['end_time'])); ?></p>
                                            
                                            <div class="session-stats mb-3" id="stats-<?php echo $session['id']; ?>">
                                                <small class="text-muted">Loading stats...</small>
                                            </div>
                                            
                                            <?php if ($session['status'] === 'scheduled'): ?>
                                                <button class="btn btn-success btn-sm w-100" onclick="activateSession(<?php echo $session['id']; ?>)">
                                                    <i class="fas fa-play"></i> Start QR Scanning
                                                </button>
                                            <?php elseif ($session['status'] === 'active'): ?>
                                                <button class="btn btn-primary btn-sm w-100 mb-2" onclick="openScanner(<?php echo $session['id']; ?>)">
                                                    <i class="fas fa-camera"></i> Open QR Scanner
                                                </button>
                                                <button class="btn btn-info btn-sm w-100 mb-2" onclick="viewAttendanceList(<?php echo $session['id']; ?>)">
                                                    <i class="fas fa-list"></i> View Student List
                                                </button>
                                                <button class="btn btn-danger btn-sm w-100" onclick="deactivateSession(<?php echo $session['id']; ?>)">
                                                    <i class="fas fa-stop"></i> End Session
                                                </button>
                                            <?php else: ?>
                                                <button class="btn btn-info btn-sm w-100 mb-2" onclick="viewAttendanceList(<?php echo $session['id']; ?>)">
                                                    <i class="fas fa-list"></i> View Final List
                                                </button>
                                                <button class="btn btn-secondary btn-sm w-100" disabled>
                                                    <i class="fas fa-check"></i> Session Completed
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No Active Sessions</h5>
                            <p class="text-muted">Create a new attendance session to start scanning QR codes.</p>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createSessionModal">
                                <i class="fas fa-plus"></i> Create First Session
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Create Session Modal -->
<div class="modal fade" id="createSessionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create Attendance Session</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="createSessionForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Session Name</label>
                        <input type="text" class="form-control" name="session_name" required 
                               placeholder="e.g., Morning Session - Database Concepts">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Course</label>
                        <select class="form-select" name="course_id" required onchange="updateCourseName(this)">
                            <option value="">Select Course</option>
                            <?php if (empty($courses)): ?>
                                <option value="" disabled>No courses available</option>
                            <?php else: ?>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['id']; ?>" 
                                            data-name="<?php echo htmlspecialchars($course['course_name']); ?>">
                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <input type="hidden" name="course_name" id="course_name">
                        <?php if (empty($courses)): ?>
                            <small class="text-muted">No courses found. Please add courses first.</small>
                        <?php else: ?>
                            <small class="text-muted"><?php echo count($courses); ?> courses available</small>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Subject</label>
                        <input type="text" class="form-control" name="subject" required 
                               placeholder="e.g., SQL Fundamentals">
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" name="date" 
                                       value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Time</label>
                                <input type="time" class="form-control" name="start_time" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">End Time</label>
                                <input type="time" class="form-control" name="end_time" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Session</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- QR Scanner Modal -->
<div class="modal fade" id="qrScannerModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">QR Code Scanner</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-8">
                        <div id="qr-reader" style="width: 100%;"></div>
                    </div>
                    <div class="col-md-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Scan Results</h6>
                                <small id="scannerStatus" class="text-muted">Ready to scan QR codes...</small>
                            </div>
                            <div class="card-body">
                                <div id="scanResults">
                                    <p class="text-muted">Point camera at student QR codes to mark attendance.</p>
                                    <div class="alert alert-info">
                                        <small>
                                            <i class="fas fa-info-circle"></i>
                                            <strong>Instructions:</strong><br>
                                            • Hold QR code steady in camera view<br>
                                            • Wait for scan confirmation<br>
                                            • First scan = IN, Second scan = OUT
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Student Attendance List Modal -->
<div class="modal fade" id="attendanceListModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Session Attendance List</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row mb-3">
                    <div class="col-md-8">
                        <h6 id="sessionDetailsHeader">Session Details</h6>
                    </div>
                    <div class="col-md-4 text-end">
                        <button class="btn btn-success btn-sm" onclick="refreshAttendanceList()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                        <button class="btn btn-primary btn-sm" onclick="exportAttendanceList()">
                            <i class="fas fa-download"></i> Export
                        </button>
                    </div>
                </div>
                
                <!-- Attendance Statistics -->
                <div class="row mb-4" id="attendanceStatsRow">
                    <div class="col-md-3">
                        <div class="stat-card bg-success text-white">
                            <div class="stat-number" id="presentCount">0</div>
                            <div class="stat-label">Present</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-warning text-white">
                            <div class="stat-number" id="partialCount">0</div>
                            <div class="stat-label">Partial</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-danger text-white">
                            <div class="stat-number" id="absentCount">0</div>
                            <div class="stat-label">Absent</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card bg-info text-white">
                            <div class="stat-number" id="avgDuration">0h</div>
                            <div class="stat-label">Avg Duration</div>
                        </div>
                    </div>
                </div>
                
                <!-- Attendance Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="attendanceTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Student Name</th>
                                <th>Student ID</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Scan History</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <tr>
                                <td colspan="7" class="text-center">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.session-card {
    border-left: 4px solid #007bff;
    transition: all 0.3s ease;
}

.session-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.session-card .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.badge-success {
    background-color: #28a745;
}

.badge-warning {
    background-color: #ffc107;
    color: #212529;
}

#qr-reader {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    min-height: 300px;
}

.scan-result {
    padding: 8px;
    margin-bottom: 8px;
    border-radius: 4px;
    font-size: 0.9em;
}

.scan-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.scan-error {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.scan-duplicate {
    background-color: #fff3cd;
    color: #856404;
    border: 1px solid #ffeaa7;
}

.stat-card {
    padding: 20px;
    border-radius: 8px;
    text-align: center;
    margin-bottom: 15px;
}

.stat-number {
    font-size: 2rem;
    font-weight: bold;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 0.9rem;
    opacity: 0.9;
}

.scan-history-badge {
    display: inline-block;
    padding: 2px 6px;
    margin: 1px;
    border-radius: 3px;
    font-size: 0.75rem;
    font-weight: bold;
}

.scan-in {
    background-color: #d4edda;
    color: #155724;
}

.scan-out {
    background-color: #f8d7da;
    color: #721c24;
}
</style>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Include QR Scanner Library -->
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>

    <script>
    let html5QrCode;
    let currentSessionId = null;
    let lastScanTime = 0;
    let isProcessingScan = false;

    // Update course name when course is selected
    function updateCourseName(select) {
        const selectedOption = select.options[select.selectedIndex];
        const courseName = selectedOption.getAttribute('data-name') || '';
        document.getElementById('course_name').value = courseName;
    }

    // Create new session
    document.getElementById('createSessionForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        formData.append('action', 'create_session');
        
        fetch('attendance_scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Session created successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('createSessionModal')).hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            showToast('Network error occurred', 'error');
        });
    });

    // Activate session
    function activateSession(sessionId) {
        const formData = new FormData();
        formData.append('action', 'activate_session');
        formData.append('session_id', sessionId);
        
        fetch('attendance_scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showToast('Session activated! You can now scan QR codes.', 'success');
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast('Failed to activate session', 'error');
            }
        });
    }

    // Deactivate session
    function deactivateSession(sessionId) {
        if (confirm('Are you sure you want to end this session?')) {
            const formData = new FormData();
            formData.append('action', 'deactivate_session');
            formData.append('session_id', sessionId);
            
            fetch('attendance_scanner.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Session ended successfully', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showToast('Failed to end session', 'error');
                }
            });
        }
    }

    // Open QR scanner
    function openScanner(sessionId) {
        currentSessionId = sessionId;
        const modal = new bootstrap.Modal(document.getElementById('qrScannerModal'));
        modal.show();
        
        // Initialize QR scanner when modal is shown
        document.getElementById('qrScannerModal').addEventListener('shown.bs.modal', function() {
            startQRScanner();
        });
        
        // Stop scanner when modal is hidden
        document.getElementById('qrScannerModal').addEventListener('hidden.bs.modal', function() {
            stopQRScanner();
        });
    }

    // Start QR scanner
    function startQRScanner() {
        html5QrCode = new Html5Qrcode("qr-reader");
        
        const config = {
            fps: 5, // Reduced from 10 to 5 for better performance
            qrbox: { width: 250, height: 250 },
            aspectRatio: 1.0,
            formatsToSupport: [ Html5QrcodeSupportedFormats.QR_CODE ], // Only QR codes
            experimentalFeatures: {
                useBarCodeDetectorIfSupported: true
            }
        };
        
        html5QrCode.start(
            { facingMode: "environment" },
            config,
            onScanSuccess,
            onScanFailure
        ).catch(err => {
            console.error("Unable to start scanning", err);
            document.getElementById('scanResults').innerHTML = 
                '<div class="scan-error">Camera access denied or not available. Please ensure you have a camera and grant permission.</div>';
        });
    }

    // Stop QR scanner
    function stopQRScanner() {
        if (html5QrCode) {
            html5QrCode.stop().then(() => {
                html5QrCode.clear();
            }).catch(err => {
                console.error("Unable to stop scanning", err);
            });
        }
    }

    // Handle successful QR scan with enhanced feedback
    function onScanSuccess(decodedText, decodedResult) {
        // Prevent rapid multiple scans
        const currentTime = Date.now();
        if (isProcessingScan || (currentTime - lastScanTime) < 2000) {
            console.log('Scan throttled - too soon after last scan');
            return;
        }
        
        // Validate QR code format first
        try {
            const qrData = JSON.parse(decodedText);
            if (!qrData || qrData.type !== 'student_attendance') {
                console.log('Invalid QR format, ignoring...');
                return; // Ignore non-attendance QR codes
            }
        } catch (e) {
            console.log('Not a valid JSON QR code, ignoring...');
            return; // Ignore non-JSON QR codes
        }
        
        // Set processing flag and update last scan time
        isProcessingScan = true;
        lastScanTime = currentTime;
        
        // Update scanner status
        document.getElementById('scannerStatus').innerHTML = 
            '<span class="text-warning"><i class="fas fa-spinner fa-spin"></i> Processing scan...</span>';
        
        // Process the scanned QR code
        const formData = new FormData();
        formData.append('action', 'process_qr_scan');
        formData.append('qr_data', decodedText);
        formData.append('session_id', currentSessionId);
        
        fetch('attendance_scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            const resultsDiv = document.getElementById('scanResults');
            const timestamp = new Date().toLocaleTimeString();
            
            let resultClass = 'scan-error';
            let icon = 'fa-times';
            let message = data.message || 'Scan failed';
            
            if (data.success) {
                resultClass = 'scan-success';
                icon = 'fa-check';
                message = `${data.scan_type.toUpperCase()}: ${data.student_name}`;
                if (data.duration_minutes) {
                    message += ` (${data.duration_minutes} min)`;
                }
            } else if (data.result === 'too_early') {
                resultClass = 'scan-duplicate';
                icon = 'fa-clock';
                message = `${data.student_name}: ${data.message}`;
            } else if (data.result === 'duplicate') {
                resultClass = 'scan-duplicate';
                icon = 'fa-exclamation-triangle';
            }
            
            const resultHtml = `
                <div class="scan-result ${resultClass}">
                    <i class="fas ${icon}"></i>
                    <strong>${message}</strong><br>
                    <small class="text-muted">${timestamp}</small>
                </div>
            `;
            
            resultsDiv.insertAdjacentHTML('afterbegin', resultHtml);
            
            // Update session stats
            updateSessionStats(currentSessionId);
            
            // Update scanner status
            document.getElementById('scannerStatus').innerHTML = 
                '<span class="text-success"><i class="fas fa-check"></i> Ready to scan QR codes...</span>';
            
            // Reset processing flag after a delay
            setTimeout(() => {
                isProcessingScan = false;
            }, 1000);
        })
        .catch(error => {
            console.error('Scan processing error:', error);
            document.getElementById('scannerStatus').innerHTML = 
                '<span class="text-danger"><i class="fas fa-exclamation-triangle"></i> Error - Ready to retry...</span>';
            isProcessingScan = false; // Reset flag on error
        });
    }

    // View attendance list for session
    function viewAttendanceList(sessionId) {
        currentSessionId = sessionId;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('attendanceListModal'));
        modal.show();
        
        // Load attendance data
        loadAttendanceList(sessionId);
    }

    // Load attendance list data
    function loadAttendanceList(sessionId) {
        const formData = new FormData();
        formData.append('action', 'get_session_attendance_list');
        formData.append('session_id', sessionId);
        
        fetch('attendance_scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayAttendanceList(data.data);
                updateAttendanceStats(sessionId);
            } else {
                showToast('Failed to load attendance list', 'error');
            }
        })
        .catch(error => {
            console.error('Error loading attendance list:', error);
            showToast('Network error occurred', 'error');
        });
    }

    // Display attendance list in table
    function displayAttendanceList(attendanceData) {
        const tbody = document.getElementById('attendanceTableBody');
        
        if (attendanceData.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center text-muted">
                        <i class="fas fa-users fa-2x mb-2"></i><br>
                        No attendance records found
                    </td>
                </tr>
            `;
            return;
        }
        
        let html = '';
        attendanceData.forEach(record => {
            const statusClass = {
                'present': 'success',
                'partial': 'warning', 
                'absent': 'danger'
            }[record.status] || 'secondary';
            
            const duration = record.total_duration_minutes > 0 
                ? `${Math.floor(record.total_duration_minutes / 60)}h ${record.total_duration_minutes % 60}m`
                : '-';
                
            // Parse scan history
            let scanHistoryHtml = '-';
            if (record.scan_history) {
                const scans = record.scan_history.split('|');
                scanHistoryHtml = scans.map(scan => {
                    const [type, time] = scan.split(':');
                    return `<span class="scan-history-badge scan-${type}">${type.toUpperCase()} ${time}</span>`;
                }).join(' ');
            }
            
            html += `
                <tr>
                    <td><strong>${record.student_name}</strong></td>
                    <td><code>${record.student_id}</code></td>
                    <td>${record.time_in || '-'}</td>
                    <td>${record.time_out || '-'}</td>
                    <td>${duration}</td>
                    <td><span class="badge bg-${statusClass}">${record.status.toUpperCase()}</span></td>
                    <td>${scanHistoryHtml}</td>
                </tr>
            `;
        });
        
        tbody.innerHTML = html;
    }

    // Update attendance statistics in modal
    function updateAttendanceStats(sessionId) {
        const formData = new FormData();
        formData.append('action', 'get_session_stats');
        formData.append('session_id', sessionId);
        
        fetch('attendance_scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(stats => {
            document.getElementById('presentCount').textContent = stats.present_count || 0;
            document.getElementById('partialCount').textContent = stats.partial_count || 0;
            document.getElementById('absentCount').textContent = stats.absent_count || 0;
            
            const avgHours = stats.avg_duration_minutes ? 
                Math.round((stats.avg_duration_minutes / 60) * 10) / 10 : 0;
            document.getElementById('avgDuration').textContent = avgHours + 'h';
            
            // Update session stats in main view
            const statsDiv = document.getElementById(`stats-${sessionId}`);
            if (statsDiv) {
                statsDiv.innerHTML = `
                    <small class="text-success">
                        <i class="fas fa-users"></i> ${stats.present_count || 0} Present | 
                        <i class="fas fa-clock"></i> ${stats.partial_count || 0} Partial |
                        <i class="fas fa-times"></i> ${stats.absent_count || 0} Absent
                    </small>
                `;
            }
        });
    }

    // Refresh attendance list
    function refreshAttendanceList() {
        if (currentSessionId) {
            loadAttendanceList(currentSessionId);
            showToast('Attendance list refreshed', 'success');
        }
    }

    // Export attendance list
    function exportAttendanceList() {
        if (currentSessionId) {
            window.open(`export_attendance.php?session_id=${currentSessionId}`, '_blank');
        }
    }

    // Handle scan failure
    function onScanFailure(error) {
        // This is called when no QR code is found, which is normal
        // We don't need to do anything here
    }

    // Update session statistics
    function updateSessionStats(sessionId) {
        const formData = new FormData();
        formData.append('action', 'get_session_stats');
        formData.append('session_id', sessionId);
        
        fetch('attendance_scanner.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(stats => {
            const statsDiv = document.getElementById(`stats-${sessionId}`);
            if (statsDiv) {
                statsDiv.innerHTML = `
                    <small class="text-success">
                        <i class="fas fa-users"></i> ${stats.present_count || 0} Present | 
                        <i class="fas fa-qrcode"></i> ${stats.total_scans || 0} Scans
                    </small>
                `;
            }
        });
    }

    // Load initial stats for all sessions
    document.addEventListener('DOMContentLoaded', function() {
        <?php foreach ($active_sessions as $session): ?>
            updateSessionStats(<?php echo $session['id']; ?>);
        <?php endforeach; ?>
    });

    // Refresh sessions
    function refreshSessions() {
        location.reload();
    }

    // Toast notification function
    function showToast(message, type = 'info') {
        // Create toast element
        const toast = document.createElement('div');
        toast.className = `alert alert-${type === 'error' ? 'danger' : type} alert-dismissible fade show position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(toast);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 5000);
    }
    </script>
</body>
</html>