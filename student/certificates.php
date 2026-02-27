<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];

// Fetch student details
$sql = "SELECT s.*, c.course_name FROM students s 
        LEFT JOIN courses c ON s.course = c.course_code 
        WHERE s.student_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$student = $result->fetch_assoc();

// Fetch certificates
$sql_certs = "SELECT * FROM certificates WHERE student_id = ? ORDER BY issue_date DESC";
$stmt_certs = $conn->prepare($sql_certs);
$certificates = [];
if ($stmt_certs) {
    $stmt_certs->bind_param("s", $student_id);
    $stmt_certs->execute();
    $result_certs = $stmt_certs->get_result();
    while ($row = $result_certs->fetch_assoc()) {
        $certificates[] = $row;
    }
}

$page_title = "Certificates";
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <h2><i class="fas fa-certificate"></i> My Certificates</h2>
            <p class="text-muted">Download and view your course certificates</p>
        </div>
    </div>

    <!-- Certificates Grid -->
    <?php if (count($certificates) > 0): ?>
        <div class="row">
            <?php foreach ($certificates as $cert): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card certificate-card">
                    <div class="card-body text-center">
                        <div class="certificate-icon mb-3">
                            <i class="fas fa-award fa-4x text-warning"></i>
                        </div>
                        <h5 class="card-title"><?php echo htmlspecialchars($cert['certificate_name']); ?></h5>
                        <p class="text-muted mb-2">
                            <?php echo htmlspecialchars($cert['course_name'] ?? $student['course_name']); ?>
                        </p>
                        <p class="small text-muted mb-3">
                            <i class="fas fa-calendar"></i> 
                            Issued: <?php echo date('d M Y', strtotime($cert['issue_date'])); ?>
                        </p>
                        <p class="small mb-3">
                            <strong>Certificate No:</strong> <?php echo htmlspecialchars($cert['certificate_number']); ?>
                        </p>
                        <div class="btn-group" role="group">
                            <a href="view_certificate.php?id=<?php echo $cert['id']; ?>" 
                               target="_blank" 
                               class="btn btn-sm btn-primary">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="download_certificate.php?id=<?php echo $cert['id']; ?>" 
                               class="btn btn-sm btn-success">
                                <i class="fas fa-download"></i> Download
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <!-- No Certificates -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-certificate fa-5x text-muted mb-4"></i>
                        <h4 class="text-muted mb-3">No Certificates Yet</h4>
                        <p class="text-muted mb-4">
                            Certificates will be available here once you complete your course successfully.
                        </p>
                        
                        <!-- Course Progress Info -->
                        <div class="alert alert-info d-inline-block">
                            <i class="fas fa-info-circle"></i> 
                            Complete your course to receive your certificate
                        </div>
                        
                        <div class="mt-4">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Certificate Information -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-info-circle"></i> Certificate Information</h5>
                </div>
                <div class="card-body">
                    <h6>How to get your certificate:</h6>
                    <ol>
                        <li>Complete all course modules and assignments</li>
                        <li>Maintain minimum 75% attendance</li>
                        <li>Pass the final examination</li>
                        <li>Clear all pending fees</li>
                        <li>Certificate will be issued within 30 days of course completion</li>
                    </ol>
                    
                    <hr>
                    
                    <h6>Certificate Verification:</h6>
                    <p>All certificates issued by NIELIT Bhubaneswar can be verified online using the certificate number.</p>
                    <a href="verify_certificate.php" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-check-circle"></i> Verify Certificate
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.certificate-card {
    transition: transform 0.3s, box-shadow 0.3s;
    height: 100%;
}

.certificate-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.certificate-icon {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}
</style>

<?php include 'includes/footer.php'; ?>
