<?php
/** @var array $student_enrollment_rows */
/** @var array $student_enrollments */
$displayRows = !empty($student_enrollment_rows) ? $student_enrollment_rows : $student_enrollments;
if (empty($displayRows)) {
    return;
}
foreach ($displayRows as $erow):
    $cname = $erow['course_name'] ?? ($erow['course'] ?? 'N/A');
    $scheme = trim($erow['scheme_name'] ?? '');
?>
<p class="mb-1 profile-course-line">
    <i class="fas fa-graduation-cap"></i>
    <?php echo htmlspecialchars($cname); ?>
    <?php if ($scheme !== ''): ?>
        <span class="badge bg-light text-dark ms-1"><?php echo htmlspecialchars($scheme); ?></span>
    <?php endif; ?>
</p>
<?php endforeach; ?>
