<?php
/** @var array $student_enrollment_rows */
/** @var array $student_enrollments */
$displayRows = !empty($student_enrollment_rows) ? $student_enrollment_rows : $student_enrollments;
if (empty($displayRows)) {
    echo '<p class="text-muted mb-0">No course enrollments found.</p>';
    return;
}
?>
<div class="table-responsive">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>Course</th>
                <th>Scheme / Project</th>
                <th>Batch</th>
                <th>Training Centre</th>
                <th>Status</th>
                <th>Registered</th>
                <th>Form</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($displayRows as $erow):
                $recordId = (int)($erow['id'] ?? $erow['student_record_id'] ?? 0);
                $cname = $erow['course_name'] ?? ($erow['course'] ?? 'N/A');
                $scheme = trim($erow['scheme_name'] ?? '');
                $batch = trim($erow['batch_name'] ?? ($erow['batch_code'] ?? ''));
                $centre = trim($erow['training_center'] ?? '');
                $status = ucfirst($erow['status'] ?? 'pending');
                $regDate = $erow['registration_date'] ?? ($erow['registered_at'] ?? '');
                $statusClass = 'bg-secondary';
                if (strtolower($status) === 'active') {
                    $statusClass = 'bg-success';
                } elseif (strtolower($status) === 'pending') {
                    $statusClass = 'bg-warning text-dark';
                }
            ?>
            <tr>
                <td><?php echo htmlspecialchars($cname); ?></td>
                <td><?php echo $scheme !== '' ? htmlspecialchars($scheme) : '—'; ?></td>
                <td><?php echo $batch !== '' ? htmlspecialchars($batch) : 'Not assigned'; ?></td>
                <td><?php echo $centre !== '' ? htmlspecialchars($centre) : '—'; ?></td>
                <td><span class="badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></span></td>
                <td><?php echo $regDate ? date('d M Y', strtotime($regDate)) : '—'; ?></td>
                <td>
                    <?php if ($recordId > 0): ?>
                    <a href="download_form.php?record_id=<?php echo $recordId; ?>" class="btn btn-sm btn-outline-success" title="Download form">
                        <i class="fas fa-file-pdf"></i>
                    </a>
                    <?php else: ?>
                    —
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
