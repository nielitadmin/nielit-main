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
                    <i class="fas fa-camera"></i>
                    <span>Photo required · Aadhar optional</span>
                </div>
                <div class="workshop-quick-item">
                    <i class="fas fa-file-pdf"></i>
                    <a href="<?php echo APP_URL; ?>/student/download_workshop_form.php?token=<?php echo rawurlencode($registration_token); ?>"
                       style="color:inherit;text-decoration:underline;">
                        Download printable PDF form
                    </a>
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
                           max="<?php echo htmlspecialchars(date('Y-m-d'), ENT_QUOTES, 'UTF-8'); ?>"
                           min="<?php echo htmlspecialchars(date('Y-m-d', strtotime('-100 years')), ENT_QUOTES, 'UTF-8'); ?>"
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
            <?php workshopSectionHeader('fa-id-card', 'Aadhar details', 'Optional — you can skip and submit without Aadhar', 'icon-gold'); ?>
            <div class="row g-4">
                <div class="col-md-6">
                    <label class="form-label">Aadhar number (student or parent)</label>
                    <input type="text" name="aadhar" class="form-control" maxlength="12" pattern="[0-9]{12}"
                           placeholder="12-digit Aadhar number (optional)" value="<?php echo workshopFieldValue($formData, 'aadhar'); ?>">
                    <span class="field-hint">Optional. If provided, must be 12 digits. Parent/guardian Aadhar is accepted for Class 1–10</span>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Upload Aadhar card</label>
                    <div class="file-upload-zone" id="zone_aadhar_card">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <div class="file-upload-label">JPG or PNG only (optional)</div>
                        <span class="upload-size-badge"><i class="fas fa-weight-hanging me-1"></i>JPG/PNG max 5MB</span>
                        <span class="field-hint d-block mb-2">Optional. Upload a clear photo or scan of your <strong>Aadhar card</strong> if available.</span>
                        <input type="file" name="aadhar_card" id="workshop_aadhar_card" class="form-control workshop-file-input"
                               accept="image/jpeg,image/png,image/jpg"
                               data-max-image-mb="5">
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
                        <span class="field-hint d-block mb-2">Upload a clear front-facing passport-size photo (JPG or PNG, max 5MB).</span>
                        <input type="file" name="passport_photo" id="workshop_passport_photo" class="form-control workshop-file-input workshop-passport-input"
                               accept="image/jpeg,image/png,image/jpg" required data-max-image-mb="5">
                    </div>
                </div>
            </div>
        </section>

        <div class="workshop-info-note mobile-upload-tip" role="note">
            <i class="fas fa-mobile-screen-button"></i>
            <p>On mobile, tap upload fields — choose <strong>Camera</strong> for a new photo or <strong>Gallery / Files</strong> for a saved image.</p>
        </div>

        <div class="workshop-info-note">
            <i class="fas fa-circle-info"></i>
            <p>Payment, marksheets, signature, thumb impression, and Aadhar are <strong>not required</strong> for this short form. Passport photo is mandatory.</p>
        </div>

        <div class="workshop-form-actions">
            <button type="submit" class="btn btn-submit" id="workshopSubmitBtn">
                <i class="fas fa-paper-plane"></i> Submit Registration
            </button>
            <a href="<?php echo APP_URL; ?>/student/download_workshop_form.php?token=<?php echo rawurlencode($registration_token); ?>"
               class="btn-back" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf"></i> Download PDF form
            </a>
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
<script src="<?php echo APP_URL; ?>/assets/js/registration-skeleton.js?v=<?php echo is_file(__DIR__ . '/../assets/js/registration-skeleton.js') ? filemtime(__DIR__ . '/../assets/js/registration-skeleton.js') : time(); ?>"></script>
<script>
function calculateWorkshopAge() {
    const dobInput = document.getElementById('workshop_dob');
    const ageInput = document.getElementById('workshop_age');
    if (!dobInput || !ageInput) {
        return;
    }
    const t = new Date();
    const mo = t.getMonth() + 1;
    const da = t.getDate();
    const todayYmd = t.getFullYear() + '-' + (mo < 10 ? '0' : '') + mo + '-' + (da < 10 ? '0' : '') + da;
    dobInput.setAttribute('max', todayYmd);
    dobInput.setCustomValidity('');
    if (!dobInput.value) {
        ageInput.value = '';
        return;
    }
    if (dobInput.value > todayYmd) {
        dobInput.value = '';
        ageInput.value = '';
        dobInput.setCustomValidity('Date of birth cannot be a future date.');
        dobInput.reportValidity();
        return;
    }
    const dobDate = new Date(dobInput.value + 'T00:00:00');
    const today = new Date(todayYmd + 'T00:00:00');
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

function workshopClearFilePreview(button) {
    const preview = button.closest('.file-preview');
    const zone = button.closest('.file-upload-zone');
    if (!zone) return;
    const input = zone.querySelector('input[type="file"]');
    if (input) {
        input.value = '';
        input.classList.remove('is-valid');
    }
    if (preview) preview.remove();
    zone.classList.remove('has-preview');
    workshopClearFileError(input);
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

    const fileName = (file && file.name) ? file.name : '';
    const fileSize = file && file.size ? workshopFormatFileSize(file.size) : '';
    const fieldName = input.getAttribute('name') || '';
    const isImage = (file && file.type) ? file.type.startsWith('image/') : false;
    const isPdf = (file && file.type === 'application/pdf') || (fileName || '').toLowerCase().endsWith('.pdf');
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

document.querySelectorAll('.workshop-file-input').forEach(function (input) {
    input.addEventListener('change', function () {
        workshopClearFileError(this);
        const file = this.files[0];
        if (!file) {
            const zone = this.closest('.file-upload-zone');
            const preview = zone && zone.querySelector('.file-preview');
            if (preview) preview.remove();
            if (zone) zone.classList.remove('has-preview');
            this.classList.remove('is-valid');
            return;
        }
        const validation = workshopValidateFileInput(this);
        if (!validation.valid) {
            this.value = '';
            this.classList.remove('is-valid');
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
</script>
<?php require __DIR__ . '/includes/registration_site_footer.php'; ?>
