<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student name and course
$sql = "SELECT name, course FROM students WHERE student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Calculate attendance statistics
$sql_stats = "SELECT 
    COUNT(*) as total_classes,
    SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present_count,
    SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent_count,
    SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM attendance WHERE student_id = ?";
$stmt_stats = $conn->prepare($sql_stats);

$total_classes = 0;
$present_count = 0;
$absent_count = 0;
$late_count = 0;
$attendance_percentage = 0;

if ($stmt_stats) {
    $stmt_stats->bind_param("s", $student_id);
    $stmt_stats->execute();
    $result_stats = $stmt_stats->get_result();
    if ($row_stats = $result_stats->fetch_assoc()) {
        $total_classes = $row_stats['total_classes'];
        $present_count = $row_stats['present_count'];
        $absent_count = $row_stats['absent_count'];
        $late_count = $row_stats['late_count'];
        if ($total_classes > 0) {
            $attendance_percentage = round(($present_count / $total_classes) * 100, 1);
        }
    }
}

// Fetch attendance records
$sql_attendance = "SELECT * FROM attendance WHERE student_id = ? ORDER BY date DESC LIMIT 50";
$stmt_attendance = $conn->prepare($sql_attendance);
$attendance_records = [];
if ($stmt_attendance) {
    $stmt_attendance->bind_param("s", $student_id);
    $stmt_attendance->execute();
    $result_attendance = $stmt_attendance->get_result();
    while ($row = $result_attendance->fetch_assoc()) {
        $attendance_records[] = $row;
    }
}

$page_title = "Attendance";
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-calendar-check"></i> Attendance Record</h2>
            <p class="text-muted">Track your class attendance and maintain good academic standing</p>
        </div>
    </div>

    <!-- Attendance Statistics -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card bg-primary-gradient">
                <div class="stat-icon">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $total_classes; ?></h3>
                    <p>Total Classes</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-success-gradient">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $present_count; ?></h3>
                    <p>Present</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-danger" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $absent_count; ?></h3>
                    <p>Absent</p>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-3">
            <div class="stat-card bg-warning-gradient">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-content">
                    <h3><?php echo $late_count; ?></h3>
                    <p>Late</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Student QR Code for Attendance -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-qrcode"></i> Your Attendance QR Code</h5>
                </div>
                <div class="card-body text-center">
                    <div class="row">
                        <div class="col-md-6">
                            <?php
                            // Check if student has QR code, if not generate one (only once)
                            require_once __DIR__ . '/../includes/attendance_qr_helper.php';
                            
                            // First check if student record has QR code path
                            if (empty($student['attendance_qr_code'])) {
                                // No QR code path in database, generate new one
                                $qr_result = generateStudentAttendanceQR($student_id, $student['name'], $conn);
                                if ($qr_result['success']) {
                                    $student['attendance_qr_code'] = $qr_result['path'];
                                }
                            } else {
                                // QR code path exists, check if file actually exists
                                $qr_file_path = __DIR__ . '/../' . $student['attendance_qr_code'];
                                if (!file_exists($qr_file_path)) {
                                    // File missing, regenerate with same path structure
                                    $qr_result = generateStudentAttendanceQR($student_id, $student['name'], $conn);
                                    if ($qr_result['success']) {
                                        $student['attendance_qr_code'] = $qr_result['path'];
                                    }
                                }
                            }
                            
                            if (!empty($student['attendance_qr_code']) && file_exists(__DIR__ . '/../' . $student['attendance_qr_code'])): ?>
                                <div class="qr-code-container">
                                    <img src="../<?php echo htmlspecialchars($student['attendance_qr_code']); ?>" 
                                         alt="Student Attendance QR Code" 
                                         class="img-fluid qr-code-image"
                                         style="max-width: 250px; border: 3px solid #007bff; border-radius: 10px; padding: 10px; background: white;">
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    QR Code not available. Please contact your coordinator.
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <div class="qr-instructions">
                                <h6 class="text-primary mb-3">How to Use Your QR Code</h6>
                                <div class="instruction-steps text-left">
                                    <div class="step mb-3">
                                        <span class="step-number">1</span>
                                        <div class="step-content">
                                            <strong>Show this QR code</strong> to your course coordinator during class
                                        </div>
                                    </div>
                                    <div class="step mb-3">
                                        <span class="step-number">2</span>
                                        <div class="step-content">
                                            <strong>Coordinator will scan</strong> your QR code using their scanner
                                        </div>
                                    </div>
                                    <div class="step mb-3">
                                        <span class="step-number">3</span>
                                        <div class="step-content">
                                            <strong>Attendance marked</strong> automatically in the system
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- PERMANENT SECURITY NOTICES - CANNOT BE REMOVED -->
                                <div class="permanent-qr-notice-1" 
                                     style="background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%) !important;
                                            border: 2px solid #007bff !important;
                                            border-radius: 8px !important;
                                            padding: 15px !important;
                                            margin: 15px 0 !important;
                                            display: block !important;
                                            visibility: visible !important;
                                            opacity: 1 !important;
                                            position: relative !important;
                                            z-index: 999999 !important;
                                            font-size: 14px !important;
                                            line-height: 1.5 !important;
                                            color: #0056b3 !important;
                                            font-weight: 500 !important;">
                                    <div style="display: flex !important; align-items: flex-start !important;">
                                        <i class="fas fa-info-circle" 
                                           style="color: #007bff !important; 
                                                  margin-right: 10px !important; 
                                                  font-size: 18px !important;
                                                  margin-top: 2px !important;
                                                  display: inline-block !important;"></i>
                                        <div style="flex: 1 !important;">
                                            <strong style="color: #0056b3 !important; display: inline !important;">IMPORTANT:</strong> 
                                            This QR code is unique to you and remains the same throughout your course. Keep it secure and do not share it with others.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="permanent-qr-notice-2" 
                                     style="background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%) !important;
                                            border: 2px solid #ffc107 !important;
                                            border-radius: 8px !important;
                                            padding: 15px !important;
                                            margin: 10px 0 15px 0 !important;
                                            display: block !important;
                                            visibility: visible !important;
                                            opacity: 1 !important;
                                            position: relative !important;
                                            z-index: 999999 !important;
                                            font-size: 14px !important;
                                            line-height: 1.5 !important;
                                            color: #856404 !important;
                                            font-weight: 500 !important;">
                                    <div style="display: flex !important; align-items: flex-start !important;">
                                        <i class="fas fa-shield-alt" 
                                           style="color: #ffc107 !important; 
                                                  margin-right: 10px !important; 
                                                  font-size: 18px !important;
                                                  margin-top: 2px !important;
                                                  display: inline-block !important;"></i>
                                        <div style="flex: 1 !important;">
                                            <strong style="color: #856404 !important; display: inline !important;">SECURITY:</strong> 
                                            Your QR code is permanent and linked to your identity. Screenshots or photos of this code should be kept private.
                                        </div>
                                    </div>
                                </div>
                                
                                <button class="btn btn-outline-primary btn-sm" onclick="downloadQRCode()">
                                    <i class="fas fa-download"></i> Download QR Code
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Percentage -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-4">
                    <h3 class="mb-3">Overall Attendance</h3>
                    <div class="attendance-circle mx-auto mb-3">
                        <svg width="200" height="200">
                            <circle cx="100" cy="100" r="80" fill="none" stroke="#e9ecef" stroke-width="20"/>
                            <circle cx="100" cy="100" r="80" fill="none" 
                                    stroke="<?php echo $attendance_percentage >= 75 ? '#28a745' : ($attendance_percentage >= 60 ? '#ffc107' : '#dc3545'); ?>" 
                                    stroke-width="20"
                                    stroke-dasharray="<?php echo ($attendance_percentage / 100) * 502.4; ?> 502.4"
                                    transform="rotate(-90 100 100)"/>
                            <text x="100" y="100" text-anchor="middle" dy="10" 
                                  font-size="36" font-weight="bold" 
                                  fill="<?php echo $attendance_percentage >= 75 ? '#28a745' : ($attendance_percentage >= 60 ? '#ffc107' : '#dc3545'); ?>">
                                <?php echo $attendance_percentage; ?>%
                            </text>
                        </svg>
                    </div>
                    <?php if ($attendance_percentage >= 75): ?>
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i> Excellent! You're maintaining good attendance.
                        </div>
                    <?php elseif ($attendance_percentage >= 60): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Your attendance is below recommended level. Try to improve!
                        </div>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-times-circle"></i> Critical! Your attendance is very low. Please attend classes regularly.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Attendance Records -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-list"></i> Attendance History</h5>
                </div>
                <div class="card-body">
                    <?php if (count($attendance_records) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Subject/Class</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                        <th>Remarks</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($attendance_records as $record): ?>
                                    <tr>
                                        <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                        <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                        <td><?php echo htmlspecialchars($record['subject'] ?? 'General'); ?></td>
                                        <td><?php echo htmlspecialchars($record['time'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php
                                            $status = strtolower($record['status']);
                                            $badge_class = 'badge-secondary';
                                            $icon = 'fa-question';
                                            
                                            if ($status == 'present') {
                                                $badge_class = 'badge-success';
                                                $icon = 'fa-check';
                                            } elseif ($status == 'absent') {
                                                $badge_class = 'badge-danger';
                                                $icon = 'fa-times';
                                            } elseif ($status == 'late') {
                                                $badge_class = 'badge-warning';
                                                $icon = 'fa-clock';
                                            }
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['remarks'] ?? '-'); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No attendance records found</h5>
                            <p class="text-muted">Attendance records will appear here once classes begin.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.attendance-circle {
    width: 200px;
    height: 200px;
}

.qr-code-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 250px;
}

.qr-code-image {
    transition: transform 0.3s ease;
}

.qr-code-image:hover {
    transform: scale(1.05);
}

.instruction-steps {
    max-width: 300px;
    margin: 0 auto;
}

.step {
    display: flex;
    align-items: flex-start;
}

.step-number {
    background: #007bff;
    color: white;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: bold;
    margin-right: 15px;
    flex-shrink: 0;
}

.step-content {
    flex: 1;
    font-size: 14px;
    line-height: 1.4;
}

/* Permanent QR Notes - Cannot be hidden by any means */
.qr-note-persistent,
.qr-security-note,
.permanent-qr-notice-1,
.permanent-qr-notice-2 {
    position: static !important;
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    z-index: 999999 !important;
    animation: none !important;
    transition: none !important;
    font-weight: 500 !important;
    margin-top: 15px !important;
    margin-bottom: 10px !important;
    padding: 12px 15px !important;
    border-radius: 6px !important;
    font-size: 14px !important;
    line-height: 1.5 !important;
    color: inherit !important;
    background-color: inherit !important;
    border: inherit !important;
    width: auto !important;
    height: auto !important;
    max-width: none !important;
    max-height: none !important;
    min-width: 0 !important;
    min-height: 0 !important;
    overflow: visible !important;
    clip: none !important;
    clip-path: none !important;
    transform: none !important;
    filter: none !important;
}

/* Extra protection for permanent notices */
.permanent-qr-notice-1,
.permanent-qr-notice-2 {
    content: "" !important;
    pointer-events: auto !important;
    user-select: text !important;
}

.permanent-qr-notice-1 *,
.permanent-qr-notice-2 * {
    display: inherit !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.qr-note-persistent .fas,
.qr-security-note .fas {
    margin-right: 8px !important;
    font-size: 16px !important;
    display: inline !important;
    visibility: visible !important;
    opacity: 1 !important;
}

.qr-note-persistent strong {
    color: #0056b3 !important;
    font-weight: bold !important;
    display: inline !important;
}

.qr-security-note strong {
    color: #856404 !important;
    font-weight: bold !important;
    display: inline !important;
}

/* Override any potential hiding attempts */
.qr-note-persistent[style*="display: none"],
.qr-security-note[style*="display: none"],
.permanent-qr-notice-1[style*="display: none"],
.permanent-qr-notice-2[style*="display: none"] {
    display: block !important;
}

.qr-note-persistent[style*="visibility: hidden"],
.qr-security-note[style*="visibility: hidden"],
.permanent-qr-notice-1[style*="visibility: hidden"],
.permanent-qr-notice-2[style*="visibility: hidden"] {
    visibility: visible !important;
}

.qr-note-persistent[style*="opacity: 0"],
.qr-security-note[style*="opacity: 0"],
.permanent-qr-notice-1[style*="opacity: 0"],
.permanent-qr-notice-2[style*="opacity: 0"] {
    opacity: 1 !important;
}

@media (max-width: 768px) {
    .instruction-steps {
        text-align: center;
    }
    
    .step {
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .step-number {
        margin-right: 0;
        margin-bottom: 10px;
    }
}
</style>

<script>
// Recreate permanent notices if they get removed
function ensurePermanentNotices() {
    const qrInstructions = document.querySelector('.qr-instructions');
    if (!qrInstructions) return;
    
    // Check if notices exist
    let notice1 = document.querySelector('.permanent-qr-notice-1');
    let notice2 = document.querySelector('.permanent-qr-notice-2');
    
    // Recreate notice 1 if missing
    if (!notice1) {
        notice1 = document.createElement('div');
        notice1.className = 'permanent-qr-notice-1';
        notice1.style.cssText = `
            background: linear-gradient(135deg, #e7f3ff 0%, #cce7ff 100%) !important;
            border: 2px solid #007bff !important;
            border-radius: 8px !important;
            padding: 15px !important;
            margin: 15px 0 !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 999999 !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: #0056b3 !important;
            font-weight: 500 !important;
        `;
        notice1.innerHTML = `
            <div style="display: flex !important; align-items: flex-start !important;">
                <i class="fas fa-info-circle" style="color: #007bff !important; margin-right: 10px !important; font-size: 18px !important; margin-top: 2px !important; display: inline-block !important;"></i>
                <div style="flex: 1 !important;">
                    <strong style="color: #0056b3 !important; display: inline !important;">IMPORTANT:</strong> 
                    This QR code is unique to you and remains the same throughout your course. Keep it secure and do not share it with others.
                </div>
            </div>
        `;
        
        // Insert before download button
        const downloadBtn = qrInstructions.querySelector('.btn');
        if (downloadBtn) {
            qrInstructions.insertBefore(notice1, downloadBtn);
        } else {
            qrInstructions.appendChild(notice1);
        }
    }
    
    // Recreate notice 2 if missing
    if (!notice2) {
        notice2 = document.createElement('div');
        notice2.className = 'permanent-qr-notice-2';
        notice2.style.cssText = `
            background: linear-gradient(135deg, #fff8e1 0%, #ffecb3 100%) !important;
            border: 2px solid #ffc107 !important;
            border-radius: 8px !important;
            padding: 15px !important;
            margin: 10px 0 15px 0 !important;
            display: block !important;
            visibility: visible !important;
            opacity: 1 !important;
            position: relative !important;
            z-index: 999999 !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            color: #856404 !important;
            font-weight: 500 !important;
        `;
        notice2.innerHTML = `
            <div style="display: flex !important; align-items: flex-start !important;">
                <i class="fas fa-shield-alt" style="color: #ffc107 !important; margin-right: 10px !important; font-size: 18px !important; margin-top: 2px !important; display: inline-block !important;"></i>
                <div style="flex: 1 !important;">
                    <strong style="color: #856404 !important; display: inline !important;">SECURITY:</strong> 
                    Your QR code is permanent and linked to your identity. Screenshots or photos of this code should be kept private.
                </div>
            </div>
        `;
        
        // Insert before download button
        const downloadBtn = qrInstructions.querySelector('.btn');
        if (downloadBtn) {
            qrInstructions.insertBefore(notice2, downloadBtn);
        } else {
            qrInstructions.appendChild(notice2);
        }
    }
}

// Run on page load and set up observer
document.addEventListener('DOMContentLoaded', function() {
    ensurePermanentNotices();
    
    // Watch for DOM changes and restore notices if removed
    const observer = new MutationObserver(function(mutations) {
        let shouldCheck = false;
        mutations.forEach(function(mutation) {
            if (mutation.type === 'childList') {
                mutation.removedNodes.forEach(function(node) {
                    if (node.classList && (node.classList.contains('permanent-qr-notice-1') || node.classList.contains('permanent-qr-notice-2'))) {
                        shouldCheck = true;
                    }
                });
            }
        });
        if (shouldCheck) {
            setTimeout(ensurePermanentNotices, 100);
        }
    });
    
    // Start observing
    const qrSection = document.querySelector('.qr-instructions');
    if (qrSection) {
        observer.observe(qrSection, { childList: true, subtree: true });
    }
});

// Simple QR download function
function downloadQRCode() {
    const qrImage = document.querySelector('.qr-code-image');
    if (qrImage) {
        const link = document.createElement('a');
        link.href = qrImage.src;
        link.download = 'my-attendance-qr-code.png';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}
</script>

<?php include 'includes/footer.php'; ?>
