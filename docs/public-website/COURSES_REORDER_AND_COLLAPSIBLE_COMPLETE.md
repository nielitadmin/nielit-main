# Courses Page: Section Reordering + Collapsible Cards Implementation

## Overview
Comprehensive update to `public/courses.php` to:
1. **Reorder sections** to match user's priority
2. **Apply collapsible card design** to ALL 6 course sections

## Current Status
- ✅ Internship section has collapsible cards
- ❌ Other 5 sections still use old 3-column card layout
- ❌ Section order doesn't match user requirements

## Required Changes

### 1. Section Order (NEW)
User requested this specific order:

1. **Skill Based (Long Term) Courses (> 500 hrs)** - Priority #1
2. **Skill Based (Short Term) Courses (90-500 hrs)** - Priority #2
3. **Short Term / Digital Competency Courses (<= 90 hrs)** - Priority #3
4. **Internship Programs & Boot Camps** - Priority #4
5. **Degree / Diploma / Postgraduate Courses** - Priority #5
6. **NIELIT HQ Digital Literacy Courses** - Priority #6

### 2. Collapsible Card Structure
ALL sections need this structure:

#### Card Wrapper:
```html
<div class="col-md-12">  <!-- Full width, not col-md-6 col-lg-4 -->
```

#### Card with onclick:
```html
<div class="course-card" onclick="toggleCourseCard(this)">
```

#### Compact Header:
```html
<div class="course-card-header">
    <div class="course-header-info">
        <h4><?php echo htmlspecialchars($row["course_name"]); ?></h4>
        <div class="course-quick-info">
            <span><i class="fas fa-clock"></i> <?php echo htmlspecialchars($row["duration"]); ?></span>
            <?php if (!empty($row["training_fees"])): ?>
            <span><i class="fas fa-rupee-sign"></i> ₹<?php echo is_numeric($row["training_fees"]) ? number_format($row["training_fees"]) : htmlspecialchars($row["training_fees"]); ?></span>
            <?php endif; ?>
        </div>
    </div>
    <!-- Status Badge -->
    <div class="enrollment-status-badge">
        <?php 
        $enrollment_status = $row['enrollment_status'] ?? 'ongoing';
        $enrollment_closing_date = $row['enrollment_closing_date'] ?? null;
        $today = date('Y-m-d');
        $is_closed = false;
        if ($enrollment_status == 'closed') {
            $is_closed = true;
        } elseif (!empty($enrollment_closing_date) && $today > $enrollment_closing_date) {
            $is_closed = true;
        }
        if ($is_closed): 
        ?>
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
```

### 3. Sections to Update

#### Section 1: Skill Based (Long Term) - MOVE TO TOP
- Currently at line ~768
- Change `<div class="col-md-6 col-lg-4">` to `<div class="col-md-12">`
- Add `onclick="toggleCourseCard(this)"` to `.course-card`
- Restructure header with compact info
- Add toggle icon
- Simplify status badge to "Open" or "Closed"

#### Section 2: Skill Based (Short Term) - MOVE TO #2
- Currently at line ~927
- Apply same collapsible structure

#### Section 3: Short Term / Digital Competency - MOVE TO #3
- Currently at line ~1086
- Apply same collapsible structure

#### Section 4: Internship Programs - MOVE TO #4
- Currently at line ~440
- ✅ Already has collapsible cards
- Just needs to be moved to position #4

#### Section 5: Degree / Diploma / PG - MOVE TO #5
- Currently at line ~602
- Apply collapsible structure

#### Section 6: NIELIT HQ Digital Literacy - STAYS AT #6
- Currently at line ~1252
- Apply collapsible structure

### 4. Quick Navigation Update
✅ Already updated to reflect new order:
1. Skill Long Term
2. Skill Short Term
3. Short Term/Digital
4. Internship
5. Degree/Diploma
6. Digital Literacy

## Implementation Strategy

Due to file size (1496 lines), we'll:
1. Extract each section to a separate variable/file
2. Reorder sections
3. Apply collapsible card structure to sections that need it
4. Reassemble the file

## Benefits

### Space Efficiency:
- Reduced vertical space by ~70% when collapsed
- Better overview of all courses
- Less scrolling required

### User Experience:
- Quick scanning of course names
- Expand only what you need
- Consistent interaction across all sections
- Mobile-friendly

### Consistency:
- All 6 sections have same interaction pattern
- Uniform visual design
- Predictable behavior

## Files Modified
- `public/courses.php` - Main implementation
- `public/courses_backup_before_reorder.php` - Backup created

## Testing Checklist
- [ ] All 6 sections display in correct order
- [ ] All cards collapse/expand on click
- [ ] Links and buttons work inside expanded cards
- [ ] Status badges show correctly
- [ ] Quick navigation jumps to correct sections
- [ ] Centre filter works
- [ ] Mobile responsive
- [ ] No JavaScript errors

---

**Implementation Date**: June 1, 2026  
**Status**: 🔄 In Progress
