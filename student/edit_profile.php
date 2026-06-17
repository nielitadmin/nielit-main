<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

if (!isset($_SESSION['student_id'])) {
    header('Location: login.php');
    exit;
}

$student_id = trim((string)$_SESSION['student_id']);
$success_message = '';
$error_message = '';

$stmt = $conn->prepare('SELECT * FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 1');
if (!$stmt) {
    die('Database error.');
}
$stmt->bind_param('s', $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$student) {
    $_SESSION['error'] = 'Student profile not found.';
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mobile = trim($_POST['mobile'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $state = trim($_POST['state'] ?? '');
    $pincode = trim($_POST['pincode'] ?? '');
    $college_name = trim($_POST['college_name'] ?? '');
    $apaar_id = trim($_POST['apaar_id'] ?? '');
    $distinguishing_marks = trim($_POST['distinguishing_marks'] ?? '');

    if ($mobile === '' || !preg_match('/^[0-9]{10}$/', $mobile)) {
        $error_message = 'Please enter a valid 10-digit mobile number.';
    } elseif ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif ($address === '' || $city === '' || $state === '' || $pincode === '') {
        $error_message = 'Address, city, state, and pincode are required.';
    } elseif ($pincode !== '' && !preg_match('/^[0-9]{6}$/', $pincode)) {
        $error_message = 'Please enter a valid 6-digit pincode.';
    } else {
        $upd = $conn->prepare('UPDATE students SET
            mobile = ?, email = ?, address = ?, city = ?, state = ?, pincode = ?,
            college_name = ?, apaar_id = ?, distinguishing_marks = ?
            WHERE student_id = ?');
        if (!$upd) {
            $error_message = 'Could not save profile. Please try again.';
        } else {
            $upd->bind_param(
                'ssssssssss',
                $mobile,
                $email,
                $address,
                $city,
                $state,
                $pincode,
                $college_name,
                $apaar_id,
                $distinguishing_marks,
                $student_id
            );
            if ($upd->execute()) {
                if (isMultiCourseSystemInstalled($conn)) {
                    $account = getAccountByStudentId($conn, $student_id);
                    if ($account) {
                        $accUpd = $conn->prepare('UPDATE student_accounts SET mobile = ?, email = ?, name = name WHERE id = ?');
                        if ($accUpd) {
                            $accountId = (int)$account['id'];
                            $accUpd->bind_param('ssi', $mobile, $email, $accountId);
                            $accUpd->execute();
                            $accUpd->close();
                        }
                    }
                }

                $success_message = 'Profile updated successfully.';
                $student['mobile'] = $mobile;
                $student['email'] = $email;
                $student['address'] = $address;
                $student['city'] = $city;
                $student['state'] = $state;
                $student['pincode'] = $pincode;
                $student['college_name'] = $college_name;
                $student['apaar_id'] = $apaar_id;
                $student['distinguishing_marks'] = $distinguishing_marks;
            } else {
                $error_message = 'Failed to update profile. Please try again.';
            }
            $upd->close();
        }
    }
}

$page_title = 'Edit Profile';
include 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-user-edit"></i> Edit Profile</h2>
                    <p class="text-muted mb-0">Update your contact details. Name, Aadhar, and course can only be changed by admin.</p>
                </div>
                <a href="profile.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Profile
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-id-card"></i> Student Information (read-only)</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label text-muted">Student ID</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['student_id']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Full Name</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['name']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Aadhar Number</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['aadhar']); ?>" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label text-muted">Course</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($student['course']); ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-address-card"></i> Editable Contact Details</h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="edit_profile.php">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="mobile" class="form-label">Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="mobile" name="mobile" maxlength="10"
                                       value="<?php echo htmlspecialchars($student['mobile'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email"
                                       value="<?php echo htmlspecialchars($student['email'] ?? ''); ?>" required>
                            </div>
                            <div class="col-12">
                                <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                                <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($student['address'] ?? ''); ?></textarea>
                            </div>
                            <div class="col-md-4">
                                <label for="city" class="form-label">City / District <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="city" name="city"
                                       value="<?php echo htmlspecialchars($student['city'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="state" name="state"
                                       value="<?php echo htmlspecialchars($student['state'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="pincode" class="form-label">Pincode <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="pincode" name="pincode" maxlength="6"
                                       value="<?php echo htmlspecialchars($student['pincode'] ?? ''); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="college_name" class="form-label">College / Institute Name</label>
                                <input type="text" class="form-control" id="college_name" name="college_name"
                                       value="<?php echo htmlspecialchars($student['college_name'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="apaar_id" class="form-label">APAAR ID</label>
                                <input type="text" class="form-control" id="apaar_id" name="apaar_id"
                                       value="<?php echo htmlspecialchars($student['apaar_id'] ?? ''); ?>">
                            </div>
                            <div class="col-12">
                                <label for="distinguishing_marks" class="form-label">Distinguishing Marks</label>
                                <input type="text" class="form-control" id="distinguishing_marks" name="distinguishing_marks"
                                       value="<?php echo htmlspecialchars($student['distinguishing_marks'] ?? ''); ?>">
                            </div>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <a href="change_password.php" class="btn btn-outline-primary">
                                <i class="fas fa-key"></i> Change Password
                            </a>
                            <a href="profile.php" class="btn btn-outline-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
