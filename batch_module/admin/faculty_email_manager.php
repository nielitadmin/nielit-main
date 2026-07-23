<?php
/**
 * Faculty Email Management Panel
 * Displays all faculty members with option to resend confirmation emails
 */

session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/url_helper.php';
require_once __DIR__ . '/../../includes/sidebar_theme_helper.php';
require_once __DIR__ . '/../../includes/admin_assets.php';
require_once __DIR__ . '/../includes/batch_functions.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin'])) {
    header("Location: ../../admin/login.php");
    exit();
}

// Get batch ID if provided
$batch_id = isset($_GET['batch_id']) ? intval($_GET['batch_id']) : null;

// Fetch all faculty members with email and confirmation status
$faculty_query = "SELECT f.id, f.name, f.email, f.designation, f.department, f.created_at, f.email_confirmed_at, a.username as created_by_username
                  FROM faculty f
                  LEFT JOIN admin a ON f.created_by = a.id
                  WHERE f.is_active = 1
                  ORDER BY f.name";

$result = $conn->query($faculty_query);
$faculty_list = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $faculty_list[] = $row;
    }
}

// Get batch info if batch_id provided
$batch_info = null;
if ($batch_id) {
    $batch_query = "SELECT id, batch_name, course_id FROM batches WHERE id = ?";
    $stmt = $conn->prepare($batch_query);
    $stmt->bind_param("i", $batch_id);
    $stmt->execute();
    $batch_info = $stmt->get_result()->fetch_assoc();
}

// Include theme loader
require_once __DIR__ . '/../../includes/theme_loader.php';
$active_theme = loadActiveTheme($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Faculty Email Management - NIELIT Bhubaneswar</title>
    <?php adminEmitHeadAssets($active_theme, ['toast' => true]); ?>
    <style>
        .faculty-card {
            background: white;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 12px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .faculty-card:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            border-color: var(--secondary-color, #1a56db);
        }
        
        .faculty-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }
        
        .faculty-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--primary-color, #0a1628);
            flex: 1;
        }
        
        .faculty-designation {
            color: #64748b;
            font-size: 14px;
            margin: 4px 0;
        }
        
        .faculty-email {
            color: var(--secondary-color, #1a56db);
            font-size: 14px;
            margin: 4px 0;
        }
        
        .faculty-meta {
            display: flex;
            gap: 16px;
            font-size: 13px;
            color: #64748b;
            margin: 8px 0;
        }
        
        .email-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .email-status.confirmed {
            background: #d1fae5;
            color: #065f46;
        }
        
        .email-status.pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
            margin-top: 10px;
        }
        
        .btn-resend {
            padding: 8px 16px;
            border: 1px solid #fbbf24;
            background: #fbbf24;
            color: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-resend:hover {
            background: #f59e0b;
            border-color: #f59e0b;
        }
        
        .btn-resend:disabled {
            background: #d1d5db;
            border-color: #d1d5db;
            cursor: not-allowed;
            opacity: 0.6;
        }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            padding: 8px 16px;
            border: 1px solid #e0e0e0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #64748b;
            transition: all 0.3s ease;
        }
        
        .filter-tab.active {
            background: var(--secondary-color, #1a56db);
            color: white;
            border-color: var(--secondary-color, #1a56db);
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #64748b;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #cbd5e1;
            margin-bottom: 16px;
            display: block;
        }
    </style>
</head>
<body class="admin-body <?php echo htmlspecialchars(adminBodySidebarClass($conn)); ?>">

<div class="admin-wrapper">
    <?php include __DIR__ . '/../../admin/includes/sidebar.php'; ?>

    <!-- Main Content -->
    <main class="admin-content">
        <!-- Top Bar -->
        <div class="admin-topbar">
            <div class="topbar-left">
                <h4><i class="fas fa-envelope"></i> Faculty Email Management</h4>
                <small>Send and resend confirmation emails to faculty members</small>
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
            <?php if ($batch_id && $batch_info): ?>
                <div style="margin-bottom: 20px;">
                    <a href="batch_details.php?id=<?php echo $batch_id; ?>" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Batch Details
                    </a>
                </div>
            <?php endif; ?>

            <!-- Content Card -->
            <div class="content-card">
                <div class="card-header">
                    <h5 class="card-title">
                        <i class="fas fa-users"></i> Faculty Members 
                        <span style="background: #1a56db; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; margin-left: 10px;">
                            <?php echo count($faculty_list); ?>
                        </span>
                    </h5>
                </div>

                <div style="padding: 20px;">
                    <!-- Filter Tabs -->
                    <div class="filter-tabs">
                        <button class="filter-tab active" onclick="filterFaculty('all')">
                            All Faculty
                        </button>
                        <button class="filter-tab" onclick="filterFaculty('confirmed')">
                            <i class="fas fa-check-circle"></i> Email Confirmed
                        </button>
                        <button class="filter-tab" onclick="filterFaculty('pending')">
                            <i class="fas fa-hourglass-half"></i> Pending Confirmation
                        </button>
                    </div>

                    <!-- Faculty List -->
                    <div id="faculty-container">
                        <?php if (empty($faculty_list)): ?>
                            <div class="empty-state">
                                <i class="fas fa-user-slash"></i>
                                <h5>No Faculty Members Found</h5>
                                <p>Start by adding faculty members from the batch management page.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($faculty_list as $faculty): ?>
                                <div class="faculty-card" data-status="<?php echo $faculty['email_confirmed_at'] ? 'confirmed' : 'pending'; ?>">
                                    <div class="faculty-header">
                                        <div style="flex: 1;">
                                            <div class="faculty-name">
                                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($faculty['name']); ?>
                                            </div>
                                            <?php if ($faculty['designation']): ?>
                                                <div class="faculty-designation">
                                                    <?php echo htmlspecialchars($faculty['designation']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($faculty['email']): ?>
                                                <div class="faculty-email">
                                                    <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($faculty['email']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="email-status <?php echo $faculty['email_confirmed_at'] ? 'confirmed' : 'pending'; ?>">
                                            <?php if ($faculty['email_confirmed_at']): ?>
                                                <i class="fas fa-check-circle"></i>
                                                Confirmed
                                            <?php else: ?>
                                                <i class="fas fa-hourglass-half"></i>
                                                Pending
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Metadata -->
                                    <div class="faculty-meta">
                                        <div>
                                            <strong>Added:</strong> <?php echo date('M d, Y', strtotime($faculty['created_at'])); ?>
                                        </div>
                                        <?php if ($faculty['email_confirmed_at']): ?>
                                            <div>
                                                <strong>Confirmed:</strong> <?php echo date('M d, Y \a\t g:i A', strtotime($faculty['email_confirmed_at'])); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($faculty['created_by_username']): ?>
                                            <div>
                                                <strong>Created by:</strong> <?php echo htmlspecialchars($faculty['created_by_username']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Action Buttons -->
                                    <div class="action-buttons">
                                        <?php if ($faculty['email']): ?>
                                            <button class="btn-resend" onclick="resendFacultyEmail(<?php echo $faculty['id']; ?>, '<?php echo addslashes(htmlspecialchars($faculty['name'])); ?>')">
                                                <i class="fas fa-envelope-circle-check"></i> Resend Email
                                            </button>
                                        <?php else: ?>
                                            <button class="btn-resend" disabled title="No email address provided">
                                                <i class="fas fa-envelope-circle-check"></i> Resend Email
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
// Fallback showToast function if toast-notifications.js doesn't load
if (typeof showToast === 'undefined') {
    function showToast(message, type) {
        alert(message);
    }
}

/**
 * Resend confirmation email to faculty member
 */
function resendFacultyEmail(facultyId, facultyName) {
    if (!confirm(`Resend confirmation email to ${facultyName}?`)) {
        return;
    }
    
    const btn = event.target.closest('.btn-resend');
    const originalHTML = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
    
    fetch('resend_faculty_email_ajax.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'resend_email',
            faculty_id: facultyId
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showToast(result.message, 'success');
            
            // Update button state
            btn.innerHTML = '<i class="fas fa-check"></i> Email Sent';
            btn.classList.remove('btn-resend');
            btn.style.background = '#10b981';
            btn.style.borderColor = '#10b981';
            
            // Update status badge
            const card = btn.closest('.faculty-card');
            const statusBadge = card.querySelector('.email-status');
            if (statusBadge) {
                statusBadge.classList.remove('pending');
                statusBadge.classList.add('confirmed');
                statusBadge.innerHTML = '<i class="fas fa-check-circle"></i> Confirmed';
                card.setAttribute('data-status', 'confirmed');
            }
            
            // Reset button after 3 seconds
            setTimeout(() => {
                btn.innerHTML = originalHTML;
                btn.disabled = false;
                btn.style.background = '';
                btn.style.borderColor = '';
                btn.classList.add('btn-resend');
            }, 3000);
        } else {
            showToast('Error: ' + result.message, 'error');
            btn.innerHTML = originalHTML;
            btn.disabled = false;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('Failed to resend email: ' + error.message, 'error');
        btn.innerHTML = originalHTML;
        btn.disabled = false;
    });
}

/**
 * Filter faculty by status
 */
function filterFaculty(status) {
    // Update active tab
    document.querySelectorAll('.filter-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
    
    // Filter cards
    const cards = document.querySelectorAll('.faculty-card');
    cards.forEach(card => {
        const cardStatus = card.getAttribute('data-status');
        if (status === 'all' || cardStatus === status) {
            card.style.display = '';
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show empty state if no visible cards
    const visibleCards = Array.from(cards).filter(card => card.style.display !== 'none');
    if (visibleCards.length === 0) {
        const container = document.getElementById('faculty-container');
        if (!container.querySelector('.empty-state')) {
            const emptyState = document.createElement('div');
            emptyState.className = 'empty-state';
            emptyState.innerHTML = '<i class="fas fa-search"></i><h5>No faculty members found</h5><p>Try selecting a different filter.</p>';
            container.appendChild(emptyState);
        }
    }
}
</script>

</body>
</html>
