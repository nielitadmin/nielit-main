<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Simple test page to demonstrate toast notifications
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Toast Notifications Test - NIELIT Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/toast-notifications.css?v=<?php echo time(); ?>">
    <style>
        body {
            background: #f8fafc;
            padding: 40px 20px;
        }
        .test-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        .btn-group {
            margin: 10px 0;
        }
        .demo-section {
            margin: 30px 0;
            padding: 20px;
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
        }
    </style>
</head>
<body>

<div class="test-container">
    <h1><i class="fas fa-bell"></i> Toast Notifications Demo</h1>
    <p class="text-muted">Test all the different types of toast notifications used in the faculty management system.</p>
    
    <div class="demo-section">
        <h3>Basic Toast Types</h3>
        <div class="btn-group">
            <button class="btn btn-success" onclick="toast.success('Faculty member added successfully!')">
                <i class="fas fa-check"></i> Success Toast
            </button>
            <button class="btn btn-danger" onclick="toast.error('Error adding faculty member!')">
                <i class="fas fa-times"></i> Error Toast
            </button>
            <button class="btn btn-warning" onclick="toast.warning('Please fill in all required fields!')">
                <i class="fas fa-exclamation-triangle"></i> Warning Toast
            </button>
            <button class="btn btn-info" onclick="toast.info('Faculty information updated!')">
                <i class="fas fa-info-circle"></i> Info Toast
            </button>
        </div>
    </div>

    <div class="demo-section">
        <h3>Special Action Toasts</h3>
        <div class="btn-group">
            <button class="btn btn-outline-danger" onclick="toast.deleted('Dr. John Smith has been deactivated.')">
                <i class="fas fa-trash-alt"></i> Delete Toast
            </button>
            <button class="btn btn-outline-success" onclick="toast.assigned('Faculty assigned to Computer Science course.')">
                <i class="fas fa-user-plus"></i> Assignment Toast
            </button>
            <button class="btn btn-outline-primary" onclick="showLoadingDemo()">
                <i class="fas fa-spinner"></i> Loading Toast Demo
            </button>
        </div>
    </div>

    <div class="demo-section">
        <h3>Confirmation Dialogs</h3>
        <div class="btn-group">
            <button class="btn btn-outline-warning" onclick="showWarningConfirm()">
                <i class="fas fa-exclamation-triangle"></i> Warning Confirm
            </button>
            <button class="btn btn-outline-danger" onclick="showDangerConfirm()">
                <i class="fas fa-trash"></i> Danger Confirm
            </button>
            <button class="btn btn-outline-info" onclick="showInfoConfirm()">
                <i class="fas fa-question-circle"></i> Info Confirm
            </button>
        </div>
    </div>

    <div class="demo-section">
        <h3>Faculty Management Examples</h3>
        <div class="btn-group">
            <button class="btn btn-primary" onclick="simulateAddFaculty()">
                <i class="fas fa-plus"></i> Simulate Add Faculty
            </button>
            <button class="btn btn-warning" onclick="simulateEmailSend()">
                <i class="fas fa-envelope"></i> Simulate Email Send
            </button>
            <button class="btn btn-danger" onclick="simulateDeactivate()">
                <i class="fas fa-ban"></i> Simulate Deactivate
            </button>
        </div>
    </div>

    <div class="alert alert-info mt-4">
        <h5><i class="fas fa-lightbulb"></i> How to Use in Your Code:</h5>
        <pre><code>// Basic usage
toast.success('Operation completed!');
toast.error('Something went wrong!');
toast.warning('Please check your input!');
toast.info('Information updated!');

// Special actions
toast.deleted('Item has been removed.');
toast.assigned('Assignment completed.');

// Loading with manual removal
const loadingToast = toast.loading('Processing...');
// ... do work ...
toast.remove(loadingToast);

// Confirmation dialogs
showConfirm({
    title: 'Confirm Action',
    message: 'Are you sure you want to proceed?',
    type: 'warning', // 'warning', 'danger', 'info'
    confirmText: 'Yes, Continue',
    cancelText: 'Cancel'
}).then(confirmed => {
    if (confirmed) {
        // User clicked confirm
    }
});</code></pre>
    </div>
</div>

<script src="<?php echo APP_URL; ?>/assets/js/toast-notifications.js"></script>
<script>
function showLoadingDemo() {
    const loadingToast = toast.loading('Processing faculty data...');
    
    setTimeout(() => {
        toast.remove(loadingToast);
        toast.success('Faculty data processed successfully!');
    }, 3000);
}

function showWarningConfirm() {
    showConfirm({
        title: 'Deactivate Faculty',
        message: 'Deactivate <strong>Dr. John Smith</strong>? They will no longer appear in active faculty lists.',
        type: 'warning',
        confirmText: 'Deactivate',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (confirmed) {
            toast.warning('Faculty member would be deactivated (demo mode)');
        } else {
            toast.info('Action cancelled');
        }
    });
}

function showDangerConfirm() {
    showConfirm({
        title: 'Delete Faculty',
        message: 'Permanently delete <strong>Dr. Jane Doe</strong>? This cannot be undone.',
        type: 'danger',
        confirmText: 'Delete',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (confirmed) {
            toast.deleted('Faculty member would be deleted (demo mode)');
        } else {
            toast.info('Deletion cancelled');
        }
    });
}

function showInfoConfirm() {
    showConfirm({
        title: 'Send Email',
        message: 'Send confirmation email to <strong>Dr. Smith</strong>?',
        type: 'info',
        confirmText: 'Send Email',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (confirmed) {
            toast.success('Email would be sent (demo mode)');
        } else {
            toast.info('Email cancelled');
        }
    });
}

function simulateAddFaculty() {
    const loadingToast = toast.loading('Adding faculty member...');
    
    setTimeout(() => {
        toast.remove(loadingToast);
        toast.success('Faculty member <strong>Dr. New Professor</strong> added successfully! Confirmation email sent to <strong>professor@university.edu</strong>.');
    }, 2000);
}

function simulateEmailSend() {
    const loadingToast = toast.loading('Sending email to Dr. Smith...');
    
    setTimeout(() => {
        toast.remove(loadingToast);
        toast.success('Email sent to Dr. Smith successfully!');
    }, 1500);
}

function simulateDeactivate() {
    showConfirm({
        title: 'Deactivate Faculty',
        message: 'Deactivate <strong>Dr. Test Faculty</strong>? They will no longer appear in active faculty lists.',
        type: 'warning',
        confirmText: 'Deactivate',
        cancelText: 'Cancel'
    }).then(confirmed => {
        if (confirmed) {
            const loadingToast = toast.loading('Deactivating Dr. Test Faculty...');
            
            setTimeout(() => {
                toast.remove(loadingToast);
                toast.deleted('Dr. Test Faculty has been deactivated.');
            }, 1500);
        }
    });
}
</script>

</body>
</html>