<?php
require_once __DIR__ . '/../includes/maintenance_check.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/institute_branding.php';
require_once __DIR__ . '/../includes/course_public_display.php';
require_once __DIR__ . '/../includes/course_category_options.php';
require_once __DIR__ . '/../includes/workshop_registration_helper.php';
require_once __DIR__ . '/../includes/state_city_registration.php';

ensureWorkshopRegistrationSchema($conn);

$registration_token = normalizeRegistrationToken((string) ($_GET['token'] ?? ''));
$legacy_course_code = trim((string) ($_GET['course'] ?? ''));
if ($registration_token === '' && $legacy_course_code === '') {
    setCoursesPageNotice('To register, open a workshop course and tap Apply Now.');
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$course_details = loadCourseByRegistrationParam($conn, $registration_token, $legacy_course_code);
if (!$course_details) {
    setCoursesPageNotice('This registration link is invalid. Use Apply Now from the courses page.');
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$registration_token = normalizeRegistrationToken((string) ($course_details['registration_token'] ?? $registration_token));

if (!workshopCourseUsesShortForm($course_details)) {
    header('Location: ' . APP_URL . '/student/register.php?token=' . rawurlencode($registration_token));
    exit();
}

if (!workshopRegistrationIsOpen($course_details)) {
    setCoursesPageNotice('Registration for this program is currently closed.');
    header('Location: ' . APP_URL . '/public/courses.php');
    exit();
}

$formData = $_SESSION['registration_form_data'] ?? [];
unset($_SESSION['registration_form_data']);
$registrationErrors = $_SESSION['registration_errors'] ?? [];
$registrationMissingFields = $_SESSION['registration_missing_fields'] ?? [];
unset($_SESSION['registration_errors'], $_SESSION['registration_missing_fields']);

function workshopFieldValue(array $formData, string $key, string $default = ''): string
{
    return htmlspecialchars((string)($formData[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}

$programLabel = workshopProgramLabelFromCourse($course_details);
$shortFormTitle = getWorkshopShortFormTitle();
$registration_page_title = $shortFormTitle . ' — Short Form';
$wrCssVer = is_file(__DIR__ . '/../assets/css/workshop-registration.css')
    ? filemtime(__DIR__ . '/../assets/css/workshop-registration.css')
    : time();
$regMobileCssVer = is_file(__DIR__ . '/../assets/css/registration-mobile.css')
    ? filemtime(__DIR__ . '/../assets/css/registration-mobile.css')
    : time();
$regSkeletonCssVer = is_file(__DIR__ . '/../assets/css/registration-skeleton.css')
    ? filemtime(__DIR__ . '/../assets/css/registration-skeleton.css')
    : time();
$registration_extra_head = '<link href="' . APP_URL . '/assets/css/workshop-registration.css?v=' . $wrCssVer . '" rel="stylesheet">'
    . '<link href="' . APP_URL . '/assets/css/registration-mobile.css?v=' . $regMobileCssVer . '" rel="stylesheet">'
    . '<link href="' . APP_URL . '/assets/css/registration-skeleton.css?v=' . $regSkeletonCssVer . '" rel="stylesheet">';

require __DIR__ . '/includes/registration_site_header.php';

function workshopSectionHeader(string $icon, string $title, string $subtitle, string $iconClass = ''): void
{
    $iconExtra = $iconClass !== '' ? ' ' . htmlspecialchars($iconClass) : '';
    echo '<div class="section-header">';
    echo '<div class="section-icon' . $iconExtra . '"><i class="fas ' . htmlspecialchars($icon) . '"></i></div>';
    echo '<div class="section-heading"><h2>' . htmlspecialchars($title) . '</h2>';
    if ($subtitle !== '') {
        echo '<p>' . htmlspecialchars($subtitle) . '</p>';
    }
    echo '</div></div>';
}
?>

<div class="workshop-reg">
<div class="registration-container">

    <header class="workshop-hero">
        <div class="workshop-hero-content">
            <div class="workshop-hero-badge">
                <i class="fas fa-bolt"></i> Workshop &amp; Awareness · Quick registration
            </div>
            <h1><?php echo htmlspecialchars($shortFormTitle); ?> — Short Form</h1>
            <p class="workshop-hero-lead">One-page registration for school students (Class 1–10) and higher levels — workshops and awareness programs.</p>
            <div class="workshop-course-chip">
                <i class="fas fa-chalkboard-teacher"></i>
                <span><?php echo htmlspecialchars($course_details['course_name']); ?></span>
                <span class="workshop-course-code"><?php echo htmlspecialchars($course_details['course_code']); ?></span>
            </div>
            <div class="workshop-quick-info">
                <div class="workshop-quick-item">
                    <i class="fas fa-clock"></i>
                    <span>Single page · ~5 min</span>
                </div>
                <div class="workshop-quick-item">
                    <i class="fas fa-envelope"></i>
                    <span>Email confirmation</span>
                </div>
                <div class="workshop-quick-item">
                    <i class="fas fa-id-card"></i>
                    <span>Aadhar + photo required</span>
                </div>
            </div>
        </div>
    </header>

    <?php if (!empty($registrationErrors)): ?>
    <div class="alert workshop-error-alert" role="alert">
        <strong><i class="fas fa-exclamation-triangle me-1"></i> Please fix these issues:</strong>
        <ul class="mb-0 mt-2 ps-3">
            <?php foreach ($registrationErrors as $err): ?>
            <li><?php echo htmlspecialchars($err); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo APP_URL; ?>/student/submit_workshop_registration.php" enctype="multipart/form-data" class="workshop-form">
        <input type="hidden" name="course_id" value="<?php echo (int)$course_details['id']; ?>">
        <input type="hidden" name="registration_token" value="<?php echo htmlspecialchars($registration_token); ?>">
        <input type="hidden" name="training_center" value="<?php echo htmlspecialchars($course_details['training_center'] ?? 'NIELIT BHUBANESWAR'); ?>">

        <section class="form-section">
            <?php workshopSectionHeader('fa-user-graduate', 'Student details', 'Basic information about the participant'); ?>
            <div class="row g-4">
                <div class="col-md-8">
                    <label class="form-label">Student full name <span class="required-mark">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="Enter full name" value="<?php echo workshopFieldValue($formData, 'name'); ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Class / Level <span class="required-mark">*</span></label>
                    <select name="class_standard" class="form-select" required>
                        <option value="">Select class or level</option>
                        <?php foreach (getWorkshopClassStandardOptions() as $groupLabel => $options): ?>
                        <optgroup label="<?php echo htmlspecialchars($groupLabel); ?>">
                            <?php foreach ($options as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>" <?php echo ($formData['class_standard'] ?? '') === $value ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-hint">Any age allowed for workshops</span>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date of birth <span class="required-mark">*</span></label>
                    <input type="date" name="dob" id="workshop_dob" class="form-control" required
                           value="<?php echo workshopFieldValue($formData, 'dob'); ?>" onchange="calculateWorkshopAge()">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Age</label>
                    <input type="number" name="age" id="workshop_age" class="form-control" readonly
                           placeholder="Auto" value="<?php echo workshopFieldValue($formData, 'age'); ?>">
                    <span class="field-hint">Auto from DOB</span>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Gender <span class="required-mark">*</span></label>
                    <select name="gender" class="form-select" required>
                        <option value="">Select</option>
                        <?php foreach (['Male', 'Female', 'Other'] as $g): ?>
                        <option value="<?php echo $g; ?>" <?php echo ($formData['gender'] ?? '') === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Category</label>
                    <select name="category" class="form-select">
                        <?php foreach (['General', 'OBC', 'SC', 'ST', 'EWS'] as $cat): ?>
                        <option value="<?php echo $cat; ?>" <?php echo ($formData['category'] ?? 'General') === $cat ? 'selected' : ''; ?>><?php echo $cat; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <section class="form-section">
            <?php workshopSectionHeader('fa-users', 'Parent / guardian & contact', 'At least one parent name and contact details', 'icon-blue'); ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Father's name</label>
                    <input type="text" name="father_name" class="form-control" placeholder="Father's full name" value="<?php echo workshopFieldValue($formData, 'father_name'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Mother's name</label>
                    <input type="text" name="mother_name" class="form-control" placeholder="Mother's full name" value="<?php echo workshopFieldValue($formData, 'mother_name'); ?>">
                </div>
                <div class="col-12"><span class="field-hint"><i class="fas fa-info-circle me-1"></i>At least one parent or guardian name is required.</span></div>
                <div class="col-md-6">
                    <label class="form-label">Mobile (parent) <span class="required-mark">*</span></label>
                    <input type="tel" name="mobile" class="form-control" pattern="[0-9]{10}" maxlength="10" required
                           placeholder="10-digit mobile" value="<?php echo workshopFieldValue($formData, 'mobile'); ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email <span class="required-mark">*</span></label>
                    <input type="email" name="email" class="form-control" required placeholder="name@example.com" value="<?php echo workshopFieldValue($formData, 'email'); ?>">
                    <span class="field-hint">Confirmation sent here — success page opens instantly</span>
                </div>
            </div>
        </section>

        <section class="form-section">
            <?php workshopSectionHeader('fa-id-card', 'Aadhar details', 'Identity verification — mandatory', 'icon-gold'); ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Aadhar number (student or parent) <span class="required-mark">*</span></label>
                    <input type="text" name="aadhar" class="form-control" maxlength="12" pattern="[0-9]{12}" required
                           placeholder="12-digit Aadhar number" value="<?php echo workshopFieldValue($formData, 'aadhar'); ?>">
                    <span class="field-hint">For Class 1–10, parent/guardian Aadhar is accepted</span>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Upload Aadhar card <span class="required-mark">*</span></label>
                    <div class="file-upload-zone" id="zone_aadhar_card">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="file-upload-label">JPG or PNG only (verified automatically)</div>
                        <span class="upload-size-badge"><i class="fas fa-weight-hanging me-1"></i>JPG/PNG max 5MB</span>
                        <span class="field-hint d-block mb-2">Upload a clear photo of your <strong>Aadhar card only</strong> — must show Aadhaar / UIDAI text. Marksheet, certificate, or other documents are not accepted.</span>
                        <input type="file" name="aadhar_card" id="workshop_aadhar_card" class="form-control workshop-file-input"
                               accept="image/jpeg,image/png,image/jpg" required
                               data-max-image-mb="5" data-require-aadhar-card="1">
                        <div class="doc-check-status" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="form-section">
            <?php workshopSectionHeader('fa-map-marker-alt', 'School & address', 'Institution and location details', 'icon-green'); ?>
            <div class="row g-4">
                <div class="col-12">
                    <label class="form-label">School / College name <span class="required-mark">*</span></label>
                    <input type="text" name="school_name" class="form-control" required placeholder="School or institution name" value="<?php echo workshopFieldValue($formData, 'school_name'); ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Address <span class="required-mark">*</span></label>
                    <textarea name="address" class="form-control" rows="2" required placeholder="House no., street, locality"><?php echo workshopFieldValue($formData, 'address'); ?></textarea>
                </div>
                <?php renderStateCityPincodeFields($formData); ?>
            </div>
        </section>

        <section class="form-section">
            <?php workshopSectionHeader('fa-camera', 'Student photo', 'Passport-size photograph', 'icon-blue'); ?>
            <div class="row g-4">
                <div class="col-md-8">
                    <div class="file-upload-zone" id="zone_passport_photo">
                        <i class="fas fa-portrait upload-icon"></i>
                        <div class="file-upload-label">Passport-size photo <span class="required-mark">*</span></div>
                        <span class="upload-size-badge"><i class="fas fa-weight-hanging me-1"></i>JPG or PNG · max 5MB</span>
                        <span class="field-hint d-block mb-2">Front-facing passport photo — one face, centred, plain background. Auto-checked before submit.</span>
                        <input type="file" name="passport_photo" id="workshop_passport_photo" class="form-control workshop-file-input workshop-passport-input"
                               accept="image/jpeg,image/png,image/jpg" required data-max-image-mb="5" data-require-face="1">
                        <div class="face-check-status" aria-live="polite"></div>
                    </div>
                </div>
            </div>
        </section>

        <div class="workshop-info-note mobile-upload-tip" role="note">
            <i class="fas fa-mobile-screen-button"></i>
            <p>On mobile, tap upload fields — choose <strong>Camera</strong> for a new photo or <strong>Gallery / Files</strong> for a saved image. Wait for the green verified message before submitting.</p>
        </div>

        <div id="workshopAiPreloadSkeleton" class="reg-skeleton-panel" aria-live="polite" aria-hidden="true">
            <p class="reg-skeleton-panel-title">
                <i class="fas fa-shield-halved"></i>
                Preparing photo &amp; document verification
            </p>
            <div class="reg-skeleton-panel-rows">
                <div class="reg-skeleton-line reg-skeleton-shimmer"></div>
                <div class="reg-skeleton-line reg-skeleton-line--md reg-skeleton-shimmer"></div>
            </div>
            <div class="reg-skeleton-progress" aria-hidden="true">
                <div class="reg-skeleton-progress-bar"></div>
            </div>
            <p class="reg-skeleton-caption">Loading verification tools… Please wait a moment before uploading.</p>
        </div>

        <div class="workshop-info-note">
            <i class="fas fa-circle-info"></i>
            <p>Payment, marksheets, signature, and thumb impression are <strong>not required</strong> for this short form. Aadhar card upload and photo are mandatory.</p>
        </div>

        <div class="workshop-form-actions">
            <button type="submit" class="btn btn-submit" id="workshopSubmitBtn">
                <i class="fas fa-paper-plane"></i> Submit Registration
            </button>
            <a href="<?php echo APP_URL; ?>/public/courses.php" class="btn-back">
                <i class="fas fa-arrow-left"></i> Back to Courses
            </a>
        </div>
    </form>
</div>
</div>

<div id="workshopSubmitSkeleton" class="reg-submit-skeleton-overlay" aria-live="polite" aria-hidden="true" aria-busy="false">
    <div class="reg-submit-skeleton-card" role="status">
        <div class="reg-submit-skeleton-icon" aria-hidden="true">
            <i class="fas fa-paper-plane"></i>
        </div>
        <p class="reg-skeleton-panel-title">
            <i class="fas fa-circle-notch fa-spin"></i>
            Submitting your registration
        </p>
        <div class="reg-skeleton-panel-rows">
            <div class="reg-skeleton-line reg-skeleton-shimmer"></div>
            <div class="reg-skeleton-line reg-skeleton-line--md reg-skeleton-shimmer"></div>
            <div class="reg-skeleton-line reg-skeleton-line--sm reg-skeleton-shimmer"></div>
        </div>
        <div class="reg-skeleton-progress" aria-hidden="true">
            <div class="reg-skeleton-progress-bar"></div>
        </div>
        <p class="reg-skeleton-caption">Saving your details and uploads… Please wait and do not close this page.</p>
    </div>
</div>

<?php renderStateCityPincodeScript($formData); ?>
<script src="<?php echo APP_URL; ?>/assets/js/registration-ai-loader.js?v=<?php echo is_file(__DIR__ . '/../assets/js/registration-ai-loader.js') ? filemtime(__DIR__ . '/../assets/js/registration-ai-loader.js') : time(); ?>"></script>
<script src="<?php echo APP_URL; ?>/assets/js/registration-skeleton.js?v=<?php echo is_file(__DIR__ . '/../assets/js/registration-skeleton.js') ? filemtime(__DIR__ . '/../assets/js/registration-skeleton.js') : time(); ?>"></script>
<script src="<?php echo APP_URL; ?>/assets/js/workshop-passport-photo-check.js?v=<?php echo is_file(__DIR__ . '/../assets/js/workshop-passport-photo-check.js') ? filemtime(__DIR__ . '/../assets/js/workshop-passport-photo-check.js') : time(); ?>"></script>
<script src="<?php echo APP_URL; ?>/assets/js/workshop-aadhar-card-check.js?v=<?php echo is_file(__DIR__ . '/../assets/js/workshop-aadhar-card-check.js') ? filemtime(__DIR__ . '/../assets/js/workshop-aadhar-card-check.js') : time(); ?>"></script>
<script>
if (typeof RegistrationAiLoader !== 'undefined') {
    RegistrationAiLoader.configure({ needPdf: false });
}

function workshopEnsureAi() {
    if (typeof RegistrationAiLoader !== 'undefined') {
        return RegistrationAiLoader.ensureReady();
    }
    return Promise.resolve();
}
function calculateWorkshopAge() {
    const dobInput = document.getElementById('workshop_dob');
    const ageInput = document.getElementById('workshop_age');
    if (!dobInput || !ageInput || !dobInput.value) {
        if (ageInput) ageInput.value = '';
        return;
    }
    const dobDate = new Date(dobInput.value + 'T00:00:00');
    const today = new Date();
    let age = today.getFullYear() - dobDate.getFullYear();
    const monthDiff = today.getMonth() - dobDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dobDate.getDate())) {
        age--;
    }
    ageInput.value = age >= 0 ? age : '';
}
document.addEventListener('DOMContentLoaded', calculateWorkshopAge);

function workshopFormatFileSize(bytes) {
    if (bytes >= 1024 * 1024) {
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }
    return (bytes / 1024).toFixed(1) + ' KB';
}

function workshopValidateFileInput(input) {
    const file = input.files[0];
    if (!file) {
        return input.hasAttribute('required')
            ? { valid: false, message: 'Please select a file.' }
            : { valid: true };
    }
    const name = file.name.toLowerCase();
    const ext = name.substring(name.lastIndexOf('.'));
    const field = input.getAttribute('name') || '';
    if (field === 'passport_photo') {
        if (!['.jpg', '.jpeg', '.png'].includes(ext)) {
            return { valid: false, message: 'Photo must be JPG or PNG.' };
        }
    }
    if (field === 'aadhar_card') {
        if (!['.jpg', '.jpeg', '.png'].includes(ext)) {
            return { valid: false, message: 'Aadhar card must be JPG or PNG.' };
        }
    }
    const maxImageMb = parseFloat(input.getAttribute('data-max-image-mb') || input.getAttribute('data-max-mb') || '5');
    const maxPdfMb = parseFloat(input.getAttribute('data-max-pdf-mb') || '10');
    const isPdf = ext === '.pdf';
    const limitMb = isPdf ? maxPdfMb : maxImageMb;
    const limit = limitMb * 1024 * 1024;
    if (file.size > limit) {
        return { valid: false, message: 'File too large. Maximum size is ' + limitMb + ' MB for ' + (isPdf ? 'PDF' : 'image') + ' files.' };
    }
    return { valid: true };
}

function workshopClearFileError(input) {
    const zone = input.closest('.file-upload-zone');
    if (!zone) return;
    const err = zone.querySelector('.file-error-message');
    if (err) err.remove();
    input.classList.remove('is-invalid');
}

function workshopShowFileError(input, message) {
    workshopClearFileError(input);
    const zone = input.closest('.file-upload-zone');
    if (!zone) return;
    const err = document.createElement('div');
    err.className = 'file-error-message';
    err.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>' + message;
    zone.appendChild(err);
    input.classList.add('is-invalid');
}

function workshopSetFaceCheckStatus(input, message, type) {
    if (!input) return;
    const zone = input.closest('.file-upload-zone');
    const el = zone && zone.querySelector('.face-check-status');
    if (!el) return;
    el.className = 'face-check-status' + (type ? ' ' + type : '');
    el.innerHTML = message ? '<i class="fas fa-' + (type === 'ok' ? 'check-circle' : type === 'fail' ? 'times-circle' : 'spinner fa-spin') + ' me-1"></i>' + message : '';
}

function workshopSetDocCheckStatus(input, message, type) {
    if (!input) return;
    const zone = input.closest('.file-upload-zone');
    const el = zone && zone.querySelector('.doc-check-status');
    if (!el) return;
    el.className = 'doc-check-status' + (type ? ' ' + type : '');
    el.innerHTML = message ? '<i class="fas fa-' + (type === 'ok' ? 'check-circle' : type === 'fail' ? 'times-circle' : 'spinner fa-spin') + ' me-1"></i>' + message : '';
}

function workshopClearFilePreview(button) {
    const preview = button.closest('.file-preview');
    const zone = button.closest('.file-upload-zone');
    if (!zone) return;
    const input = zone.querySelector('input[type="file"]');
    if (input) {
        input.value = '';
        input.classList.remove('is-valid');
        input.dataset.faceValid = '0';
        input.dataset.cardValid = '0';
    }
    if (preview) preview.remove();
    zone.classList.remove('has-preview');
    workshopClearFileError(input);
    if (input && input.dataset.requireFace === '1') {
        workshopSetFaceCheckStatus(input, '', '');
    }
    if (input && input.dataset.requireAadharCard === '1') {
        workshopSetDocCheckStatus(input, '', '');
    }
}

function workshopRenderFilePreview(input, file) {
    const zone = input.closest('.file-upload-zone');
    if (!zone) return;

    let preview = zone.querySelector('.file-preview');
    if (!preview) {
        preview = document.createElement('div');
        preview.className = 'file-preview';
        zone.appendChild(preview);
    }

    const fileName = file.name;
    const fileSize = workshopFormatFileSize(file.size);
    const fieldName = input.getAttribute('name') || '';
    const isImage = file.type.startsWith('image/');
    const isPdf = file.type === 'application/pdf' || fileName.toLowerCase().endsWith('.pdf');
    const showImagePreview = isImage && (fieldName === 'passport_photo' || fieldName === 'aadhar_card');

    zone.classList.add('has-preview');

    if (showImagePreview) {
        preview.classList.add('is-image-preview');
        const reader = new FileReader();
        reader.onload = function (e) {
            const label = fieldName === 'passport_photo' ? 'Passport photo preview' : 'Aadhar card preview';
            preview.innerHTML =
                '<div class="file-preview-image-wrap">' +
                '<img src="' + e.target.result + '" alt="' + label + '">' +
                '</div>' +
                '<div class="file-preview-info text-center">' +
                '<div class="file-preview-name"><i class="fas fa-check-circle text-success me-1"></i>' + fileName + '</div>' +
                '<div class="file-preview-size">' + fileSize + '</div>' +
                '</div>' +
                '<button type="button" class="file-preview-remove" onclick="workshopClearFilePreview(this)">' +
                '<i class="fas fa-times"></i> Remove</button>';
            preview.classList.add('show');
        };
        reader.readAsDataURL(file);
        return;
    }

    preview.classList.remove('is-image-preview');
    const iconClass = isPdf ? 'fa-file-pdf' : 'fa-file-image';
    preview.innerHTML =
        '<div class="file-preview-icon' + (isPdf ? ' pdf' : '') + '">' +
        '<i class="fas ' + iconClass + '"></i></div>' +
        '<div class="file-preview-info">' +
        '<div class="file-preview-name">' + fileName + '</div>' +
        '<div class="file-preview-size">' + fileSize + '</div>' +
        '</div>' +
        '<button type="button" class="file-preview-remove" onclick="workshopClearFilePreview(this)">' +
        '<i class="fas fa-times"></i> Remove</button>';
    preview.classList.add('show');
}

function workshopHandlePassportPhotoChange(input) {
    workshopClearFileError(input);
    input.dataset.faceValid = '0';
    const file = input.files[0];
    if (!file) {
        const zone = input.closest('.file-upload-zone');
        const preview = zone && zone.querySelector('.file-preview');
        if (preview) preview.remove();
        if (zone) zone.classList.remove('has-preview');
        workshopSetFaceCheckStatus(input, '', '');
        return;
    }
    const validation = workshopValidateFileInput(input);
    if (!validation.valid) {
        input.value = '';
        workshopShowFileError(input, validation.message);
        workshopSetFaceCheckStatus(input, validation.message, 'fail');
        return;
    }
    workshopSetFaceCheckStatus(input, 'Loading verification… please wait', 'checking');
    if (typeof RegistrationSkeleton !== 'undefined') {
        RegistrationSkeleton.showFieldCheck(input, 'Checking face in photo…');
    }
    workshopEnsureAi().then(function () {
        if (typeof WorkshopPassportPhotoCheck === 'undefined') {
            throw new Error('Face check could not start. Refresh the page and try again.');
        }
        workshopSetFaceCheckStatus(input, 'Checking face in photo… please wait', 'checking');
        return WorkshopPassportPhotoCheck.validate(file);
    }).then(function (result) {
        if (typeof RegistrationSkeleton !== 'undefined') {
            RegistrationSkeleton.hideFieldCheck(input);
        }
        if (!result.valid) {
            input.value = '';
            const zone = input.closest('.file-upload-zone');
            const preview = zone && zone.querySelector('.file-preview');
            if (preview) preview.remove();
            if (zone) zone.classList.remove('has-preview');
            workshopShowFileError(input, result.message);
            workshopSetFaceCheckStatus(input, result.message, 'fail');
            return;
        }
        input.dataset.faceValid = '1';
        input.classList.add('is-valid');
        workshopSetFaceCheckStatus(input, 'Face detected — photo accepted', 'ok');
        workshopRenderFilePreview(input, file);
    }).catch(function (err) {
        if (typeof RegistrationSkeleton !== 'undefined') {
            RegistrationSkeleton.hideFieldCheck(input);
        }
        input.value = '';
        const detail = err && err.message ? err.message : '';
        const message = detail.indexOf('internet') !== -1 || detail.indexOf('load') !== -1
            ? detail
            : (detail || 'Could not verify the photo. Upload a clear front-facing passport photo and try again.');
        workshopShowFileError(input, message);
        workshopSetFaceCheckStatus(input, 'Face check failed — try again', 'fail');
    });
}

function workshopHandleAadharCardChange(input) {
    workshopClearFileError(input);
    input.dataset.cardValid = '0';
    const file = input.files[0];
    if (!file) {
        const zone = input.closest('.file-upload-zone');
        const preview = zone && zone.querySelector('.file-preview');
        if (preview) preview.remove();
        if (zone) zone.classList.remove('has-preview');
        workshopSetDocCheckStatus(input, '', '');
        return;
    }
    const validation = workshopValidateFileInput(input);
    if (!validation.valid) {
        input.value = '';
        workshopShowFileError(input, validation.message);
        workshopSetDocCheckStatus(input, validation.message, 'fail');
        return;
    }
    workshopSetDocCheckStatus(input, 'Checking Aadhar card document… this may take a few seconds', 'checking');
    if (typeof RegistrationSkeleton !== 'undefined') {
        RegistrationSkeleton.showFieldCheck(input, 'Verifying Aadhar card…');
    }
    workshopEnsureAi().then(function () {
        if (typeof WorkshopAadharCardCheck === 'undefined') {
            throw new Error('Document check could not start. Refresh the page and try again.');
        }
        return WorkshopAadharCardCheck.validate(file);
    }).then(function (result) {
        if (typeof RegistrationSkeleton !== 'undefined') {
            RegistrationSkeleton.hideFieldCheck(input);
        }
        if (!result.valid) {
            input.value = '';
            const zone = input.closest('.file-upload-zone');
            const preview = zone && zone.querySelector('.file-preview');
            if (preview) preview.remove();
            if (zone) zone.classList.remove('has-preview');
            workshopShowFileError(input, result.message);
            workshopSetDocCheckStatus(input, result.message, 'fail');
            return;
        }
        input.dataset.cardValid = '1';
        input.classList.add('is-valid');
        workshopSetDocCheckStatus(input, result.message, 'ok');
        workshopRenderFilePreview(input, file);
    }).catch(function (err) {
        if (typeof RegistrationSkeleton !== 'undefined') {
            RegistrationSkeleton.hideFieldCheck(input);
        }
        input.value = '';
        const detail = err && err.message ? err.message : '';
        workshopShowFileError(input, detail || 'Could not verify the Aadhar card. Upload a clear scan or photo of the card.');
        workshopSetDocCheckStatus(input, 'Document check failed — try again', 'fail');
    });
}

document.querySelectorAll('.workshop-file-input').forEach(function (input) {
    input.addEventListener('change', function () {
        if (this.dataset.requireFace === '1') {
            workshopHandlePassportPhotoChange(this);
            return;
        }
        if (this.dataset.requireAadharCard === '1') {
            workshopHandleAadharCardChange(this);
            return;
        }
        workshopClearFileError(this);
        const file = this.files[0];
        if (!file) {
            const zone = this.closest('.file-upload-zone');
            const preview = zone && zone.querySelector('.file-preview');
            if (preview) preview.remove();
            if (zone) zone.classList.remove('has-preview');
            return;
        }
        const validation = workshopValidateFileInput(this);
        if (!validation.valid) {
            this.value = '';
            workshopShowFileError(this, validation.message);
            return;
        }
        this.classList.add('is-valid');
        workshopRenderFilePreview(this, file);
    });
});

document.querySelector('.workshop-form').addEventListener('submit', function (e) {
    const form = this;
    const submitBtn = document.getElementById('workshopSubmitBtn');

    if (form.dataset.submitting === '1') {
        e.preventDefault();
        return;
    }

    const photo = document.getElementById('workshop_passport_photo');
    if (photo && photo.dataset.requireFace === '1' && photo.dataset.faceValid !== '1') {
        e.preventDefault();
        workshopShowFileError(photo, 'Upload a valid passport photo with one clear front-facing face before submitting.');
        workshopSetFaceCheckStatus(photo, 'Photo must pass face check before submit', 'fail');
        photo.closest('.form-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }
    const aadhar = document.getElementById('workshop_aadhar_card');
    if (aadhar && aadhar.dataset.requireAadharCard === '1' && aadhar.dataset.cardValid !== '1') {
        e.preventDefault();
        workshopShowFileError(aadhar, 'Upload a clear scan or photo of your Aadhar card before submitting.');
        workshopSetDocCheckStatus(aadhar, 'Aadhar card must be verified before submit', 'fail');
        aadhar.closest('.form-section').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    form.dataset.submitting = '1';
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
    }
    if (typeof RegistrationSkeleton !== 'undefined') {
        RegistrationSkeleton.showSubmitOverlay('workshopSubmitSkeleton');
    }
});

window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
        const form = document.querySelector('.workshop-form');
        const submitBtn = document.getElementById('workshopSubmitBtn');
        if (form) {
            form.dataset.submitting = '0';
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Submit Registration';
        }
        if (typeof RegistrationSkeleton !== 'undefined') {
            RegistrationSkeleton.hideSubmitOverlay('workshopSubmitSkeleton');
        }
    }
});

document.querySelectorAll('.workshop-file-input').forEach(function (input) {
    input.addEventListener('focus', function preloadOnFocus() {
        if (typeof RegistrationSkeleton !== 'undefined') {
            RegistrationSkeleton.bindAiLoaderEvents('workshopAiPreloadSkeleton');
            RegistrationSkeleton.trackPreload('workshopAiPreloadSkeleton');
        } else if (typeof RegistrationAiLoader !== 'undefined') {
            RegistrationAiLoader.preload();
        }
        input.removeEventListener('focus', preloadOnFocus);
    });
});
</script>
<?php require __DIR__ . '/includes/registration_site_footer.php'; ?>
