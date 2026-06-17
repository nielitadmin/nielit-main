<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit;
}

$student_id = $_SESSION['student_id'];
require_once __DIR__ . '/includes/load_enrollments.php';

$student = $student_profile;
if (!$student) {
    header("Location: login.php");
    exit;
}

// Fetch education details
$sql_education = "SELECT * FROM education_details WHERE student_id = ? ORDER BY year_of_passing DESC";
$stmt_education = $conn->prepare($sql_education);
$stmt_education->bind_param("s", $student_id);
$stmt_education->execute();
$education_result = $stmt_education->get_result();

$page_title = "My Profile";
include 'includes/header.php';
?>

<div class="container py-4">
    <!-- Profile Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card profile-header-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-2 text-center">
                            <img src="../<?php echo htmlspecialchars($student['passport_photo']); ?>" 
                                 alt="Profile Photo" 
                                 class="profile-photo-large">
                        </div>
                        <div class="col-md-7">
                            <h3><?php echo htmlspecialchars($student['name']); ?></h3>
                            <p class="text-muted mb-2">
                                <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($student['student_id']); ?>
                            </p>
                            <div class="mb-1">
                                <?php include __DIR__ . '/includes/enrollment_courses_list.php'; ?>
                            </div>
                            <p class="mb-1">
                                <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($student['email']); ?>
                            </p>
                            <p class="mb-0">
                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($student['mobile']); ?>
                            </p>
                        </div>
                        <div class="col-md-3 text-right">
                            <a href="download_form.php" class="btn btn-primary mb-2">
                                <i class="fas fa-download"></i> Download Form
                            </a>
                            <br>
                            <a href="edit_profile.php" class="btn btn-outline-primary">
                                <i class="fas fa-edit"></i> Edit Profile
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Personal Information -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-user"></i> Personal Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless profile-table">
                        <tr>
                            <th>Full Name</th>
                            <td><?php echo htmlspecialchars($student['name']); ?></td>
                        </tr>
                        <tr>
                            <th>Father's Name</th>
                            <td><?php echo htmlspecialchars($student['father_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Mother's Name</th>
                            <td><?php echo htmlspecialchars($student['mother_name']); ?></td>
                        </tr>
                        <tr>
                            <th>Date of Birth</th>
                            <td><?php echo date('d M Y', strtotime($student['dob'])); ?></td>
                        </tr>
                        <tr>
                            <th>Age</th>
                            <td><?php echo htmlspecialchars($student['age']); ?> years</td>
                        </tr>
                        <tr>
                            <th>Gender</th>
                            <td><?php echo htmlspecialchars($student['gender']); ?></td>
                        </tr>
                        <tr>
                            <th>Marital Status</th>
                            <td><?php echo htmlspecialchars($student['marital_status']); ?></td>
                        </tr>
                        <tr>
                            <th>Religion</th>
                            <td><?php echo htmlspecialchars($student['religion']); ?></td>
                        </tr>
                        <tr>
                            <th>Category</th>
                            <td><?php echo htmlspecialchars($student['category']); ?></td>
                        </tr>
                        <tr>
                            <th>Nationality</th>
                            <td><?php echo htmlspecialchars($student['nationality']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-address-card"></i> Contact Information</h5>
                </div>
                <div class="card-body">
                    <table class="table table-borderless profile-table">
                        <tr>
                            <th>Mobile Number</th>
                            <td><?php echo htmlspecialchars($student['mobile']); ?></td>
                        </tr>
                        <tr>
                            <th>Email Address</th>
                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                        </tr>
                        <tr>
                            <th>Aadhar Number</th>
                            <td><?php echo htmlspecialchars($student['aadhar']); ?></td>
                        </tr>
                        <tr>
                            <th>Address</th>
                            <td><?php echo htmlspecialchars($student['address']); ?></td>
                        </tr>
                        <tr>
                            <th>City/District</th>
                            <td><?php echo htmlspecialchars($student['city']); ?></td>
                        </tr>
                        <tr>
                            <th>State</th>
                            <td><?php echo htmlspecialchars($student['state']); ?></td>
                        </tr>
                        <tr>
                            <th>Pincode</th>
                            <td><?php echo htmlspecialchars($student['pincode']); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Course Information -->
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-book"></i> My Courses</h5>
                    <?php if (count($student_enrollment_rows) > 1 || count($student_enrollments) > 1): ?>
                    <span class="badge bg-primary"><?php echo max(count($student_enrollment_rows), count($student_enrollments)); ?> enrollments</span>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php include __DIR__ . '/includes/enrollment_courses_table.php'; ?>
                </div>
                <?php if (!empty($student['college_name'])): ?>
                <div class="card-footer text-muted small">
                    <i class="fas fa-university"></i> College: <?php echo htmlspecialchars($student['college_name']); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Educational Qualifications -->
    <div class="row">
        <div class="col-12 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-graduation-cap"></i> Educational Qualifications</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Sl. No.</th>
                                    <th>Exam Passed</th>
                                    <th>Exam Name</th>
                                    <th>Year</th>
                                    <th>Institute/Board</th>
                                    <th>Stream</th>
                                    <th>Percentage/CGPA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sl_no = 1;
                                while ($edu = $education_result->fetch_assoc()):
                                ?>
                                <tr>
                                    <td><?php echo $sl_no++; ?></td>
                                    <td><?php echo htmlspecialchars($edu['exam_passed']); ?></td>
                                    <td><?php echo htmlspecialchars($edu['exam_name']); ?></td>
                                    <td><?php echo htmlspecialchars($edu['year_of_passing']); ?></td>
                                    <td><?php echo htmlspecialchars($edu['institute_name']); ?></td>
                                    <td><?php echo htmlspecialchars($edu['stream']); ?></td>
                                    <td><?php echo htmlspecialchars($edu['percentage']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Documents -->
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-image"></i> Passport Photo</h6>
                </div>
                <div class="card-body text-center">
                    <img src="../<?php echo htmlspecialchars($student['passport_photo']); ?>" 
                         alt="Passport Photo" 
                         class="img-fluid" 
                         style="max-width: 200px;">
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-signature"></i> Signature</h6>
                </div>
                <div class="card-body text-center">
                    <img src="../<?php echo htmlspecialchars($student['signature']); ?>" 
                         alt="Signature" 
                         class="img-fluid" 
                         style="max-width: 200px;">
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-file-pdf"></i> Educational Documents</h6>
                </div>
                <div class="card-body text-center">
                    <?php
                    if (!empty($student['documents'])) {
                        $documents_path = $student['documents'];
                        $documents_ext = strtolower(pathinfo($documents_path, PATHINFO_EXTENSION));
                        if ($documents_ext === 'pdf'):
                    ?>
                        <div class="mb-3">
                            <i class="fas fa-file-pdf text-danger" style="font-size: 48px;"></i>
                        </div>
                        <a href="../<?php echo htmlspecialchars($documents_path); ?>" 
                           target="_blank" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Documents
                        </a>
                        <br><br>
                        <a href="../<?php echo htmlspecialchars($documents_path); ?>" 
                           download 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                    <?php else: ?>
                        <p class="text-muted">Invalid file format</p>
                    <?php endif; ?>
                    <?php } else { ?>
                        <p class="text-muted">No documents uploaded</p>
                    <?php } ?>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-receipt"></i> Payment Receipt</h6>
                </div>
                <div class="card-body text-center">
                    <?php
                    if (!empty($student['payment_receipt'])) {
                        $receipt_path = $student['payment_receipt'];
                        $receipt_ext = strtolower(pathinfo($receipt_path, PATHINFO_EXTENSION));
                        if (in_array($receipt_ext, ['jpg', 'jpeg', 'png'])):
                    ?>
                        <img src="../<?php echo htmlspecialchars($receipt_path); ?>" 
                             alt="Payment Receipt" 
                             class="img-fluid mb-2" 
                             style="max-width: 200px;">
                        <br>
                        <a href="../<?php echo htmlspecialchars($receipt_path); ?>" 
                           target="_blank" 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-eye"></i> View Full Size
                        </a>
                    <?php elseif ($receipt_ext === 'pdf'): ?>
                        <div class="mb-3">
                            <i class="fas fa-file-pdf text-danger" style="font-size: 48px;"></i>
                        </div>
                        <a href="../<?php echo htmlspecialchars($receipt_path); ?>" 
                           target="_blank" 
                           class="btn btn-primary btn-sm">
                            <i class="fas fa-eye"></i> View Receipt
                        </a>
                        <br><br>
                        <a href="../<?php echo htmlspecialchars($receipt_path); ?>" 
                           download 
                           class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-download"></i> Download
                        </a>
                    <?php else: ?>
                        <p class="text-muted">Invalid file format</p>
                    <?php endif; ?>
                    <?php } else { ?>
                        <p class="text-muted">No receipt uploaded</p>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.profile-header-card {
    background: linear-gradient(135deg, #0a1628 0%, #112240 100%);
    color: white;
}

.profile-header-card h3 {
    color: white;
    font-weight: 600;
}

.profile-header-card .text-muted {
    color: rgba(255,255,255,0.8) !important;
}

.profile-header-card p {
    color: rgba(255,255,255,0.9);
}

.profile-photo-large {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 5px solid white;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

.profile-table th {
    width: 40%;
    color: #666;
    font-weight: 600;
    padding: 12px 8px;
}

.profile-table td {
    color: #333;
    padding: 12px 8px;
}

.profile-header-card .badge.bg-light {
    font-weight: 500;
}
</style>

<?php include 'includes/footer.php'; ?>
