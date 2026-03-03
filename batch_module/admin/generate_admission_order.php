<?php
// Start session and include the database connection
session_start();
require_once __DIR__ . '/../../config/config.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login_new.php");
    exit();
}

// Get batch ID
$batch_id = isset($_GET['batch_id']) ? $_GET['batch_id'] : null;

if (!$batch_id) {
    header("Location: manage_batches.php");
    exit();
}

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
                <small><?php echo htmlspecialchars($batch['batch_name']) . ' - ' . htmlspecialchars($batch['course_name']); ?></small>
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
            <div style="margin-bottom: 20px;">
                <a href="batch_details.php?id=<?php echo $batch_id; ?>" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Batch Details
                </a>
                <button class="btn btn-success" onclick="saveAndRegenerate(event)" style="margin-left: 10px;">
                    <i class="fas fa-save"></i> Save Changes & Regenerate
                </button>
                <button class="btn btn-primary" onclick="generateAdmissionOrder()" style="margin-left: 10px;">
                    <i class="fas fa-sync"></i> Refresh Preview
                </button>
            </div>

            <!-- Preview Area -->
            <div id="preview-area">
                <div class="content-card">
                    <div class="card-header">
                        <h5 class="card-title">
                            <i class="fas fa-eye"></i> Admission Order Preview
                        </h5>
                        <div>
                            <button class="btn btn-success" onclick="downloadPDF()">
                                <i class="fas fa-download"></i> Download PDF
                            </button>
                            <button class="btn btn-primary" onclick="printOrder()">
                                <i class="fas fa-print"></i> Print
                            </button>
                        </div>
                    </div>
                    
                    <div id="admission-order-content" style="padding: 20px; background: white;">
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
    generateAdmissionOrder();
});

function generateAdmissionOrder() {
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
    // Get only the printable content (excludes editable section)
    const element = document.getElementById('printable-content');
    
    if (!element) {
        showToast('Error: Content not found', 'error');
        return;
    }
    
    const opt = {
        margin: [8, 5, 8, 5], // Top, Right, Bottom, Left margins in mm (minimal margins)
        filename: 'admission_order_<?php echo $batch['batch_code']; ?>.pdf',
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
    const data = {
        batch_id: batchId,
        admission_order_ref: document.getElementById('edit_ref').value,
        admission_order_date: document.getElementById('edit_date').value,
        location: document.getElementById('edit_location').value,
        examination_month: document.getElementById('edit_exam_month').value,
        class_time: document.getElementById('edit_time').value,
        batch_coordinator: document.getElementById('edit_faculty').value,
        scheme_incharge: document.getElementById('edit_incharge').value,
        copy_to_list: document.getElementById('edit_copy_to').value
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
