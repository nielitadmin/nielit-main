<?php
// Start session and include the database connection
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../includes/batch_functions.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login_new.php");
    exit();
}

// Check user role for lock bypass
$is_master_admin = ($_SESSION['admin_role'] === 'master_admin');
$current_admin_id = $_SESSION['admin_id'] ?? null;

// Get batch ID
$batch_id = isset($_GET['batch_id']) ? $_GET['batch_id'] : null;

if (!$batch_id) {
    header("Location: manage_batches.php");
    exit();
}

// Check if batch is locked (Master Admins can bypass)
$is_locked = isBatchLocked($batch_id, $conn);
$lock_restricted = $is_locked && !$is_master_admin; // Only restrict if locked AND not master admin
$lock_info = getBatchLockInfo($batch_id, $conn);

// Fetch batch details
$batch_query = "SELECT b.*, c.course_name, c.course_code, s.scheme_name, s.scheme_code 
                FROM batches b 
                LEFT JOIN courses c ON b.course_id = c.id 
                LEFT JOIN schemes s ON b.scheme_id = s.id
                WHERE b.id = ?";
$stmt = $conn->prepare($batch_query);

// If schemes table doesn't exist, try without it
if (!$stmt) {
    $batch_query = "SELECT b.*, c.course_name, c.course_code, NULL as scheme_name, NULL as scheme_code 
                    FROM batches b 
                    LEFT JOIN courses c ON b.course_id = c.id 
                    WHERE b.id = ?";
    $stmt = $conn->prepare($batch_query);
}

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param("i", $batch_id);
$stmt->execute();
$batch = $stmt->get_result()->fetch_assoc();

if (!$batch) {
    header("Location: manage_batches.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Admission Order - NIELIT Bhubaneswar</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin-theme.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css">
    <link rel="icon" href="<?php echo APP_URL; ?>/assets/images/favicon.ico" type="image/x-icon">
</head>
<body>

<div class="admin-wrapper">
    <!-- Sidebar -->
    <aside class="admin-sidebar">
        <div class="sidebar-logo">
            <img src="<?php echo APP_URL; ?>/assets/images/bhubaneswar_logo.png" alt="NIELIT Logo">
            <h5>NIELIT Admin</h5>
            <small>Bhubaneswar</small>
        </div>
        
        <nav class="sidebar-nav">
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/students.php" class="nav-link">
                    <i class="fas fa-users"></i> Students
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/manage_courses.php" class="nav-link">
                    <i class="fas fa-book"></i> Courses
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/batch_module/admin/manage_batches.php" class="nav-link">
                    <i class="fas fa-layer-group"></i> Batches
                </a>
            </div>
            <div class="nav-item">
                <a href="manage_schemes.php" class="nav-link active">
                    <i class="fas fa-project-diagram"></i> Schemes/Projects
                </a>
            </div>
            
            <div class="nav-divider"></div>
            
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/index.php" class="nav-link">
                    <i class="fas fa-globe"></i> View Website
                </a>
            </div>
            <div class="nav-item">
                <a href="<?php echo APP_URL; ?>/admin/logout.php" class="nav-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-file-alt"></i> Generate Admission Order</h4>
                <small><?php echo htmlspecialchars($batch['batch_name']) . ' - ' . htmlspecialchars($batch['course_name']); ?>
                <?php if ($is_locked): ?>
                    <span class="badge badge-danger" style="margin-left: 8px;">
                        <i class="fas fa-lock"></i> LOCKED
                    </span>
                <?php endif; ?>
                </small>
            </div>
            <div class="topbar-right">
                <div class="user-info">
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($_SESSION['admin']); ?></span>
                        <span class="user-role">Administrator</span>
                    </div>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin'], 0, 1)); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="admin-main">
            <?php if ($lock_restricted): ?>
                <!-- Lock Warning for Course Coordinators -->
                <div class="alert alert-danger">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="color: #dc2626; font-size: 1.5rem;">
                            <i class="fas fa-lock"></i>
                        </div>
                        <div>
                            <h6 style="margin: 0; color: #dc2626; font-weight: 600;">Batch is Locked</h6>
                            <p style="margin: 4px 0 0 0; color: #7c2d12; font-size: 14px;">
                                This batch has been locked and admission order generation is disabled. All modifications are prevented.
                            </p>
                            <?php if ($lock_info && $lock_info['locked_at']): ?>
                                <small style="color: #7c2d12;">
                                    Locked on <?php echo date('M d, Y \a\t g:i A', strtotime($lock_info['locked_at'])); ?>
                                    <?php if ($lock_info['locked_by_username']): ?>
                                        by <?php echo htmlspecialchars($lock_info['locked_by_username']); ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php elseif ($is_locked && $is_master_admin): ?>
                <!-- Lock Override Notice for Master Admins -->
                <div class="alert alert-warning">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="color: #d97706; font-size: 1.5rem;">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div>
                            <h6 style="margin: 0; color: #d97706; font-weight: 600;">Master Admin Override</h6>
                            <p style="margin: 4px 0 0 0; color: #92400e; font-size: 14px;">
                                This batch is locked, but you can generate admission orders as a Master Admin.
                            </p>
                            <?php if ($lock_info && $lock_info['locked_at']): ?>
                                <small style="color: #92400e;">
                                    Locked on <?php echo date('M d, Y \a\t g:i A', strtotime($lock_info['locked_at'])); ?>
                                    <?php if ($lock_info['locked_by_username']): ?>
                                        by <?php echo htmlspecialchars($lock_info['locked_by_username']); ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div style="margin-bottom: 20px;">
                <a href="batch_details.php?id=<?php echo $batch_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Batch Details
                </a>
                <?php if ($lock_restricted): ?>
                    <button class="btn btn-secondary" disabled style="margin-left: 10px;">
                        <i class="fas fa-lock"></i> Save Changes (Locked)
                    </button>
                    <button class="btn btn-secondary" disabled style="margin-left: 10px;">
                        <i class="fas fa-lock"></i> Refresh Preview (Locked)
                    </button>
                <?php else: ?>
                    <button class="btn btn-success" onclick="saveAndRegenerate(event)" style="margin-left: 10px;">
                        <i class="fas fa-save"></i> Save Changes & Regenerate
                    </button>
                    <button class="btn btn-primary" onclick="generateAdmissionOrder()" style="margin-left: 10px;">
                        <i class="fas fa-sync"></i> Refresh Preview
                    </button>
                <?php endif; ?>
            </div>

            <!-- Preview Area -->
            <div id="preview-area">
                <div class="content-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-eye"></i> Admission Order Preview
                        </h5>
                        <div>
                            <?php if ($lock_restricted): ?>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-lock"></i> Download PDF (Locked)
                                </button>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-lock"></i> Generate Letterhead (Locked)
                                </button>
                                <button class="btn btn-secondary" disabled>
                                    <i class="fas fa-lock"></i> Print (Locked)
                                </button>
                            <?php else: ?>
                                <button class="btn btn-success" onclick="downloadPDF()">
                                    <i class="fas fa-download"></i> Download PDF
                                </button>
                                <button class="btn btn-warning" onclick="generateLetterhead()" style="margin-left: 5px;">
                                    <i class="fas fa-file-word"></i> Download in (word)
                                </button>
                                <button class="btn btn-primary" onclick="printOrder()" style="margin-left: 5px;">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="admission-order-content" style="padding: 20px; background: white; <?php echo $lock_restricted ? 'opacity: 0.7; pointer-events: none;' : ''; ?>">
                        <?php if ($lock_restricted): ?>
                            <div style="text-align: center; padding: 40px; color: #64748b; background: #f8f9fa; border-radius: 8px; margin-bottom: 20px;">
                                <i class="fas fa-lock" style="font-size: 48px; margin-bottom: 16px; display: block; opacity: 0.3;"></i>
                                <p style="margin: 0; font-size: 16px;">Admission order generation is disabled for locked batches.</p>
                            </div>
                        <?php endif; ?>
                        <!-- Content will be loaded here -->
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
// Fallback showToast function if toast-notifications.js doesn't load
if (typeof showToast === 'undefined') {
    function showToast(message, type) {
        alert(message);
    }
}
</script>
<script>
// Auto-load admission order on page load
document.addEventListener('DOMContentLoaded', function() {
    <?php if (!$lock_restricted): ?>
        generateAdmissionOrder();
    <?php endif; ?>
});

function generateAdmissionOrder() {
    <?php if ($lock_restricted): ?>
        showToast('Cannot generate admission order: Batch is locked', 'error');
        return;
    <?php endif; ?>
    
    const batchId = <?php echo $batch_id; ?>;
    const schemeId = <?php echo $batch['scheme_id'] ?? 'null'; ?>;
    
    // Fetch admission order data
    fetch(`generate_admission_order_ajax.php?batch_id=${batchId}&scheme_id=${schemeId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('admission-order-content').innerHTML = html;
        })
        .catch(error => {
            document.getElementById('admission-order-content').innerHTML = 
                '<div style="color: red; padding: 20px;">Error loading admission order: ' + error.message + '</div>';
        });
}

function downloadPDF() {
    <?php if ($lock_restricted): ?>
        showToast('Cannot download PDF: Batch is locked', 'error');
        return;
    <?php endif; ?>
    
    // Get only the printable content (excludes editable section)
    const element = document.getElementById('printable-content');
    
    if (!element) {
        showToast('Error: Content not found', 'error');
        return;
    }
    
    const opt = {
        margin: [8, 5, 8, 5], // Top, Right, Bottom, Left margins in mm (minimal margins)
        filename: <?php echo json_encode('admission_order_' . $batch['batch_code'] . '.pdf'); ?>,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { 
            scale: 2,
            useCORS: true,
            letterRendering: true
        },
        jsPDF: { 
            unit: 'mm', 
            format: 'a4', 
            orientation: 'portrait',
            compress: true
        },
        pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
    };
    
    // Show loading toast
    showToast('Generating PDF...', 'info');
    
    html2pdf().set(opt).from(element).save().then(() => {
        showToast('PDF downloaded successfully!', 'success');
    }).catch(error => {
        showToast('Error generating PDF: ' + error.message, 'error');
    });
}

function printOrder() {
    <?php if ($lock_restricted): ?>
        showToast('Cannot print: Batch is locked', 'error');
        return;
    <?php endif; ?>
    
    // Get only the printable content (excludes editable section)
    const printContent = document.getElementById('printable-content');
    
    if (!printContent) {
        showToast('Error: Content not found', 'error');
        return;
    }
    
    const content = printContent.innerHTML;
    const printWindow = window.open('', '_blank', 'width=900,height=800');
    
    if (!printWindow) {
        showToast('Error: Could not open print window. Please check your popup blocker.', 'error');
        return;
    }
    
    printWindow.document.write(`
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Admission Order - <?php echo htmlspecialchars($batch['batch_name']); ?></title>
    <style>
        @page {
            size: A4;
            margin: 8mm 5mm; /* Minimal margins - top/bottom 8mm, left/right 5mm */
        }
        
        @media print {
            body {
                margin: 0;
                padding: 0;
            }
            
            .no-print {
                display: none !important;
            }
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: Arial, sans-serif;
            font-size: 9pt;
            line-height: 1.2;
            color: #000;
            background: white;
            margin: 0;
            padding: 0;
        }
        
        #printable-content {
            max-width: 100%;
            padding: 8mm 5mm 8mm 5mm; /* Equal top/bottom margins: 8mm, minimal left/right: 5mm */
            margin: 0;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            page-break-inside: auto;
        }
        
        tr {
            page-break-inside: avoid;
            page-break-after: auto;
        }
        
        thead {
            display: table-header-group;
        }
        
        tfoot {
            display: table-footer-group;
        }
        
        th, td {
            border: 1px solid #000;
            padding: 3px 4px;
            text-align: left;
            font-size: 7pt;
        }
        
        th {
            background: #f0f0f0 !important;
            font-weight: bold;
            font-size: 8pt;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        
        h3 {
            margin: 3px 0;
            font-size: 11pt;
        }
        
        h4 {
            margin: 3px 0;
            font-size: 10pt;
        }
        
        p {
            margin: 3px 0;
        }
        
        img {
            max-width: 100%;
            height: auto;
        }
        
        .no-print {
            display: none !important;
        }
    </style>
</head>
<body>
    <div id="printable-content">
        ${content}
    </div>
</body>
</html>
    `);
    
    printWindow.document.close();
    
    // Wait for all content including images to load
    printWindow.onload = function() {
        // Additional wait to ensure everything is rendered
        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
            
            // Optional: Close the window after printing
            // Uncomment the line below if you want to auto-close
            // printWindow.onafterprint = function() { printWindow.close(); };
        }, 500);
    };
    
    // Fallback if onload doesn't fire
    setTimeout(() => {
        if (printWindow.document.readyState === 'complete') {
            printWindow.focus();
            printWindow.print();
        }
    }, 1000);
}

function saveAndRegenerate(event) {
    <?php if ($lock_restricted): ?>
        showToast('Cannot save changes: Batch is locked', 'error');
        return;
    <?php endif; ?>
    
    const batchId = <?php echo $batch_id; ?>;
    
    console.log('Save button clicked, batch ID:', batchId);
    
    // Check if all required fields exist
    const requiredFields = ['edit_ref', 'edit_date', 'edit_location', 'edit_exam_month', 'edit_time', 'edit_faculty', 'edit_incharge', 'edit_copy_to'];
    const missingFields = requiredFields.filter(id => !document.getElementById(id));
    
    if (missingFields.length > 0) {
        console.error('Missing fields:', missingFields);
        alert('Error: Some fields are missing. Please refresh the page and try again.');
        return;
    }
    
    // Collect all edited values
    const facultySelect = document.getElementById('edit_faculty');
    const selectedFacultyIds = Array.from(facultySelect.selectedOptions).map(option => {
        return parseInt(option.getAttribute('data-id')) || 0;
    }).filter(id => id > 0);
    
    const data = {
        batch_id: batchId,
        admission_order_ref: document.getElementById('edit_ref').value,
        admission_order_date: document.getElementById('edit_date').value,
        location: document.getElementById('edit_location').value,
        examination_month: document.getElementById('edit_exam_month').value,
        class_time: document.getElementById('edit_time').value,
        batch_coordinator: document.getElementById('edit_faculty').value,
        scheme_incharge: document.getElementById('edit_incharge').value,
        copy_to_list: document.getElementById('edit_copy_to').value,
        faculty_ids: selectedFacultyIds
    };
    
    console.log('Data to save:', data);
    
    // Show loading state
    const btn = event ? event.target : document.querySelector('button[onclick*="saveAndRegenerate"]');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    btn.disabled = true;
    
    // Save to database
    console.log('Sending request to save_admission_order_details.php');
    fetch('save_admission_order_details.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => {
        console.log('Response status:', response.status);
        console.log('Response headers:', response.headers);
        
        if (!response.ok) {
            throw new Error('HTTP error! status: ' + response.status);
        }
        
        return response.text().then(text => {
            console.log('Raw response:', text);
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        });
    })
    .then(result => {
        console.log('Parsed result:', result);
        
        if (result.success) {
            showToast('Changes saved successfully!', 'success');
            // Regenerate the preview with saved data
            setTimeout(() => {
                generateAdmissionOrder();
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 500);
        } else {
            showToast('Error saving changes: ' + result.message, 'error');
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showToast('Error saving changes: ' + error.message, 'error');
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function generateLetterhead() {
    <?php if ($lock_restricted): ?>
        showToast('Cannot generate letterhead: Batch is locked', 'error');
        return;
    <?php endif; ?>
    
    // Show loading toast
    showToast('Generating letterhead Word document...', 'info');
    
    // Create download URL for letterhead generator
    const letterheadUrl = <?php echo json_encode('generate_admission_order_word_letterhead.php?batch_id=' . $batch_id . '&scheme_id=' . ($batch['scheme_id'] ?? 0)); ?>;
    
    // Create temporary link and trigger download
    const link = document.createElement('a');
    link.href = letterheadUrl;
    link.download = <?php echo json_encode('admission_order_letterhead_' . $batch['batch_name'] . '.doc'); ?>;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    // Show success message after a short delay
    setTimeout(() => {
        showToast('Letterhead Word document download started!', 'success');
    }, 500);
}

// Faculty Management Functions
function openAddFacultyModal() {
    console.log('openAddFacultyModal called');
    
    // Create modal HTML if it doesn't exist
    if (!document.getElementById('addFacultyModal')) {
        console.log('Creating add faculty modal');
        const modalHTML = `
        <div class="modal fade" id="addFacultyModal" tabindex="-1" aria-labelledby="addFacultyModalLabel" aria-hidden="true"
             style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog" style="position: relative; margin: 50px auto; max-width: 500px;">
                <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div class="modal-header" style="padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                        <h5 class="modal-title" id="addFacultyModalLabel" style="margin: 0; font-size: 16px; font-weight: 600;">
                            <i class="fas fa-user-plus"></i> Add New Faculty Member
                        </h5>
                        <button type="button" class="btn-close" onclick="closeAddFacultyModal()" aria-label="Close" 
                                style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <form id="addFacultyForm">
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_name" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Name *</label>
                                <input type="text" class="form-control" id="faculty_name" name="name" required 
                                       placeholder="e.g., Dr. John Smith"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_email" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                                <input type="email" class="form-control" id="faculty_email" name="email" 
                                       placeholder="e.g., john.smith@nielit.gov.in"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_phone" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Phone</label>
                                <input type="text" class="form-control" id="faculty_phone" name="phone" 
                                       placeholder="e.g., 9876543210"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_designation" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Designation</label>
                                <input type="text" class="form-control" id="faculty_designation" name="designation" 
                                       placeholder="e.g., Professor, Assistant Professor, Lecturer"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <div class="mb-3" style="margin-bottom: 15px;">
                                <label for="faculty_department" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Department</label>
                                <input type="text" class="form-control" id="faculty_department" name="department" 
                                       placeholder="e.g., Computer Science, Information Technology"
                                       style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeAddFacultyModal()"
                                style="padding: 8px 16px; border: 1px solid #6c757d; background: #6c757d; color: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                        <button type="button" class="btn btn-success" onclick="addNewFaculty()"
                                style="padding: 8px 16px; border: 1px solid #198754; background: #198754; color: white; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-save"></i> Add Faculty
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    // Clear form
    document.getElementById('addFacultyForm').reset();
    
    // Show modal
    const modal = document.getElementById('addFacultyModal');
    modal.style.display = 'block';
    
    // Add click outside to close
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeAddFacultyModal();
        }
    };
}

function closeAddFacultyModal() {
    const modal = document.getElementById('addFacultyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function openDeleteFacultyModal() {
    console.log('openDeleteFacultyModal called');
    
    // Get all faculty that can be deleted (owned by current user)
    const facultySelect = document.getElementById('edit_faculty');
    if (!facultySelect) {
        alert('Faculty dropdown not found. Please refresh the page.');
        return;
    }
    
    const deletableFaculty = Array.from(facultySelect.options).filter(option => 
        option.getAttribute('data-can-delete') === 'true'
    );
    
    if (deletableFaculty.length === 0) {
        alert('You have no faculty members to delete. You can only delete faculty you have added.');
        return;
    }
    
    // Create modal HTML if it doesn't exist
    if (!document.getElementById('deleteFacultyModal')) {
        const modalHTML = `
        <div class="modal fade" id="deleteFacultyModal" tabindex="-1" aria-labelledby="deleteFacultyModalLabel" aria-hidden="true"
             style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1050;">
            <div class="modal-dialog" style="position: relative; margin: 50px auto; max-width: 500px;">
                <div class="modal-content" style="background: white; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">
                    <div class="modal-header" style="padding: 15px 20px; border-bottom: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center;">
                        <h5 class="modal-title" id="deleteFacultyModalLabel" style="margin: 0; font-size: 16px; font-weight: 600; color: #dc3545;">
                            <i class="fas fa-trash"></i> Delete Faculty Member
                        </h5>
                        <button type="button" class="btn-close" onclick="closeDeleteFacultyModal()" aria-label="Close" 
                                style="background: none; border: none; font-size: 20px; cursor: pointer;">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 20px;">
                        <div class="alert alert-warning" style="padding: 10px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin-bottom: 15px;">
                            <strong>Warning:</strong> You can only delete faculty members you have added. This action cannot be undone.
                        </div>
                        <div class="mb-3" style="margin-bottom: 15px;">
                            <label for="faculty_to_delete" class="form-label" style="display: block; margin-bottom: 5px; font-weight: 500;">Select Faculty to Delete:</label>
                            <select class="form-control" id="faculty_to_delete" name="faculty_to_delete" 
                                    style="width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">-- Select Faculty --</option>
                            </select>
                        </div>
                        <div id="delete_faculty_info" style="display: none; padding: 10px; background: #f8f9fa; border-radius: 4px; margin-top: 10px;">
                            <strong>Faculty Details:</strong><br>
                            <span id="delete_faculty_details"></span>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding: 15px 20px; border-top: 1px solid #dee2e6; display: flex; justify-content: flex-end; gap: 10px;">
                        <button type="button" class="btn btn-secondary" onclick="closeDeleteFacultyModal()"
                                style="padding: 8px 16px; border: 1px solid #6c757d; background: #6c757d; color: white; border-radius: 4px; cursor: pointer;">Cancel</button>
                        <button type="button" class="btn btn-danger" onclick="deleteFaculty()" id="confirmDeleteBtn" disabled
                                style="padding: 8px 16px; border: 1px solid #dc3545; background: #dc3545; color: white; border-radius: 4px; cursor: pointer;">
                            <i class="fas fa-trash"></i> Delete Faculty
                        </button>
                    </div>
                </div>
            </div>
        </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
    }
    
    // Populate the delete dropdown
    const deleteSelect = document.getElementById('faculty_to_delete');
    deleteSelect.innerHTML = '<option value="">-- Select Faculty --</option>';
    
    deletableFaculty.forEach(option => {
        const deleteOption = document.createElement('option');
        deleteOption.value = option.getAttribute('data-id');
        deleteOption.textContent = option.textContent;
        deleteOption.setAttribute('data-name', option.value);
        deleteOption.setAttribute('data-designation', option.getAttribute('data-designation'));
        deleteSelect.appendChild(deleteOption);
    });
    
    // Add change event listener
    deleteSelect.onchange = function() {
        const selectedOption = this.options[this.selectedIndex];
        const confirmBtn = document.getElementById('confirmDeleteBtn');
        const infoDiv = document.getElementById('delete_faculty_info');
        const detailsSpan = document.getElementById('delete_faculty_details');
        
        if (this.value) {
            const name = selectedOption.getAttribute('data-name');
            const designation = selectedOption.getAttribute('data-designation');
            detailsSpan.innerHTML = `Name: ${name}<br>Designation: ${designation || 'Not specified'}`;
            infoDiv.style.display = 'block';
            confirmBtn.disabled = false;
        } else {
            infoDiv.style.display = 'none';
            confirmBtn.disabled = true;
        }
    };
    
    // Show modal
    const modal = document.getElementById('deleteFacultyModal');
    modal.style.display = 'block';
    
    // Add click outside to close
    modal.onclick = function(event) {
        if (event.target === modal) {
            closeDeleteFacultyModal();
        }
    };
}

function closeDeleteFacultyModal() {
    const modal = document.getElementById('deleteFacultyModal');
    if (modal) {
        modal.style.display = 'none';
    }
}

function addNewFaculty() {
    const form = document.getElementById('addFacultyForm');
    const formData = new FormData(form);
    
    // Validate required fields
    const name = formData.get('name').trim();
    if (!name) {
        alert('Faculty name is required!');
        return;
    }
    
    // Show loading state
    const btn = event.target;
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Adding...';
    btn.disabled = true;
    
    // Prepare data
    const facultyData = {
        action: 'add_faculty',
        name: name,
        email: formData.get('email').trim(),
        phone: formData.get('phone').trim(),
        designation: formData.get('designation').trim(),
        department: formData.get('department').trim()
    };
    
    // Send AJAX request
    fetch('add_faculty_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(facultyData)
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Add new faculty to dropdown
            const facultySelect = document.getElementById('edit_faculty');
            if (facultySelect) {
                const newOption = document.createElement('option');
                newOption.value = result.faculty.name;
                newOption.setAttribute('data-id', result.faculty.id);
                newOption.setAttribute('data-designation', result.faculty.designation || '');
                newOption.setAttribute('data-can-delete', 'true');
                newOption.selected = true; // Auto-select the new faculty
                
                const displayText = result.faculty.name + 
                    (result.faculty.designation ? ' (' + result.faculty.designation + ')' : '') + ' [My Faculty]';
                newOption.textContent = displayText;
                
                facultySelect.appendChild(newOption);
                
                // Update the display
                updateFacultyField();
            }
            
            // Close modal
            closeAddFacultyModal();
            
            // Show success message
            showToast('Faculty member added successfully!', 'success');
        } else {
            alert('Error adding faculty: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error adding faculty. Please try again.');
    })
    .finally(() => {
        // Restore button
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function deleteFaculty() {
    const deleteSelect = document.getElementById('faculty_to_delete');
    const facultyId = deleteSelect.value;
    const facultyName = deleteSelect.options[deleteSelect.selectedIndex].getAttribute('data-name');
    
    if (!facultyId) {
        alert('Please select a faculty member to delete.');
        return;
    }
    
    if (!confirm(`Are you sure you want to delete "${facultyName}"? This action cannot be undone.`)) {
        return;
    }
    
    // Show loading state
    const btn = document.getElementById('confirmDeleteBtn');
    const originalText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
    btn.disabled = true;
    
    // Send AJAX request
    fetch('delete_faculty_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'delete_faculty',
            faculty_id: facultyId
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            // Remove faculty from dropdown
            const facultySelect = document.getElementById('edit_faculty');
            if (facultySelect) {
                const optionToRemove = Array.from(facultySelect.options).find(option => 
                    option.getAttribute('data-id') === facultyId
                );
                
                if (optionToRemove) {
                    facultySelect.removeChild(optionToRemove);
                }
                
                // Update the display
                updateFacultyField();
            }
            
            // Close modal
            closeDeleteFacultyModal();
            
            // Show success message
            showToast('Faculty member deleted successfully!', 'success');
        } else {
            alert('Error deleting faculty: ' + result.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting faculty. Please try again.');
    })
    .finally(() => {
        // Restore button
        btn.innerHTML = originalText;
        btn.disabled = false;
    });
}

function updateFacultyField() {
    const select = document.getElementById('edit_faculty');
    if (!select) return;
    
    const selectedOptions = Array.from(select.selectedOptions);
    
    if (selectedOptions.length === 0) {
        const displayElement = document.getElementById('display_faculty');
        if (displayElement) {
            displayElement.textContent = 'To be assigned';
        }
        return;
    }
    
    const facultyNames = selectedOptions.map(option => {
        const designation = option.getAttribute('data-designation');
        return option.value + (designation ? ' (' + designation + ')' : '');
    });
    
    const displayElement = document.getElementById('display_faculty');
    if (displayElement) {
        displayElement.textContent = facultyNames.join(', ');
    }
}
</script>

<style>
/* Print styles */
@media print {
    .no-print, #editable-section {
        display: none !important;
    }
    
    body {
        margin: 0;
        padding: 0;
    }
    
    * {
        box-sizing: border-box;
    }
    
    #printable-content {
        max-width: 100%;
        padding: 0;
        margin: 0;
    }
    
    table {
        page-break-inside: auto;
    }
    
    tr {
        page-break-inside: avoid;
        page-break-after: auto;
    }
    
    thead {
        display: table-header-group;
    }
    
    th {
        background: #f0f0f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}

/* Screen styles */
.row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}
.col-md-6 {
    flex: 1;
    min-width: 300px;
}
.alert {
    padding: 15px;
    border-radius: 4px;
    background: #fff3cd;
    border: 1px solid #ffc107;
    color: #856404;
}

/* A4 page styling for screen preview */
#printable-content {
    background: white;
    box-shadow: 0 0 10px rgba(0,0,0,0.1);
}

/* Success message styling */
.no-print {
    /* Will be hidden in print */
}
</style>

</body>
</html>
<?php
$conn->close();
?>
