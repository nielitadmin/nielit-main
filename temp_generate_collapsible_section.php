<?php
/**
 * Helper script to generate collapsible course card HTML
 * This generates the proper structure for each course section
 */

function generateCollapsibleCourseCard($row, $show_nsqf_badge = true) {
    $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
    $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
    $today = date('Y-m-d');
    $is_closed = false;
    
    if ($enrollment_status == 'closed') {
        $is_closed = true;
    } elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) {
        $is_closed = true;
    }
    
    ob_start();
    ?>
    <div class="col-md-12">
        <div class="course-card" onclick="toggleCourseCard(this)">
            <div class="course-card-header">
                <div class="course-header-info">
                    <h4>
                        <?php echo htmlspecialchars($row["course_name"]); ?>
                        <?php if ($show_nsqf_badge && !empty($row["is_nsqf"]) && $row["is_nsqf"]==1): ?>
                            <span class="badge bg-info" style="margin-left:8px; font-size:0.7rem;">NSQF</span>
                        <?php endif; ?>
                    </h4>
                    <div class="course-quick-info">
                        <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
                        <?php if (!empty($row["training_fees"])): ?>
                        <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <!-- Enrollment Status Badge -->
                <div class="enrollment-status-badge">
                    <?php if ($is_closed): ?>
                        <span class="status-badge status-closed">
                            <i class="fas fa-times-circle"></i> Closed
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-ongoing">
                            <i class="fas fa-check-circle"></i> Open
                        </span>
                    <?php endif; ?>
                </div>
                <i class="fas fa-chevron-down course-card-toggle"></i>
            </div>
            <div class="course-card-body <?php echo ($is_closed) ? 'course-disabled' : ''; ?>">
                <div class="course-info-grid">
                    <?php if (!empty($row["eligibility"])): ?>
                    <div class="info-item">
                        <i class="fas fa-user-graduate"></i>
                        <div>
                            <span class="info-label">Eligibility</span>
                            <span class="info-value"><?php echo htmlspecialchars($row["eligibility"]); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <i class="fas fa-clock"></i>
                        <div>
                            <span class="info-label">Duration</span>
                            <span class="info-value"><?php echo htmlspecialchars($row["duration"]); ?></span>
                        </div>
                    </div>
                    <?php if (!empty($row["training_fees"])): ?>
                    <div class="info-item">
                        <i class="fas fa-rupee-sign"></i>
                        <div>
                            <span class="info-label">Training Fees</span>
                            <span class="info-value">₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row["start_date"])): ?>
                    <div class="info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <div>
                            <span class="info-label">Course Start Date</span>
                            <span class="info-value"><?php echo date('d M Y', strtotime($row["start_date"])); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row["end_date"])): ?>
                    <div class="info-item">
                        <i class="fas fa-calendar-check"></i>
                        <div>
                            <span class="info-label">Course End Date</span>
                            <span class="info-value"><?php echo date('d M Y', strtotime($row["end_date"])); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row["course_coordinator"])): ?>
                    <div class="info-item">
                        <i class="fas fa-user-tie"></i>
                        <div>
                            <span class="info-label">Coordinator</span>
                            <span class="info-value"><?php echo htmlspecialchars($row["course_coordinator"]); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row["course_description"])): ?>
                    <div class="info-item" style="grid-column: 1 / -1;">
                        <i class="fas fa-info-circle"></i>
                        <div>
                            <span class="info-label">Description</span>
                            <span class="info-value"><?php echo nl2br(htmlspecialchars($row["course_description"])); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row["centre_name"])): ?>
                    <div class="info-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <div>
                            <span class="info-label">Training Centre</span>
                            <span class="info-value"><?php echo htmlspecialchars($row["centre_name"]); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($row["centre_city"]) && !empty($row["centre_state"])): ?>
                    <div class="info-item">
                        <i class="fas fa-location-dot"></i>
                        <div>
                            <span class="info-label">Location</span>
                            <span class="info-value"><?php echo htmlspecialchars($row["centre_city"]) . ', ' . htmlspecialchars($row["centre_state"]); ?></span>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="course-card-footer">
                <?php if (!empty($row["description_url"])): ?>
                    <a href="<?php echo htmlspecialchars($row["description_url"]); ?>" target="_blank" class="btn-outline-modern btn-modern">
                        <i class="fas fa-info-circle"></i> View Details
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($row["description_pdf"])): ?>
                    <a href="<?php echo APP_URL . '/' . htmlspecialchars($row["description_pdf"]); ?>" target="_blank" class="btn-outline-modern btn-modern">
                        <i class="fas fa-file-pdf"></i> Download PDF
                    </a>
                <?php endif; ?>
                
                <?php if (!empty($row["apply_link"]) && (!isset($row["link_published"]) || $row["link_published"] == 1)): ?>
                    <?php if ($is_closed): ?>
                        <button class="btn-disabled btn-modern" disabled title="Enrollment is closed for this course">
                            <i class="fas fa-times-circle"></i> Enrollment Closed
                        </button>
                    <?php else: ?>
                        <a href="<?php echo htmlspecialchars($row["apply_link"]); ?>" target="_blank" class="btn-primary-modern btn-modern">
                            <i class="fas fa-paper-plane"></i> Apply Now
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
?>
