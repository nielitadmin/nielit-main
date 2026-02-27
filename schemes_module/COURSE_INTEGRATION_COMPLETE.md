# Course Integration with Schemes Module - COMPLETE GUIDE

This guide shows you exactly how to integrate the schemes module with your course add/edit pages.

## Step 1: Install the Database

Run this URL in your browser:
```
http://localhost/public_html/schemes_module/install_database.php
```

## Step 2: Navigation Links Added ✅

I've already added the "Schemes/Projects" navigation link to:
- `admin/dashboard.php` ✅
- `admin/students.php` ✅

## Step 3: Update Course Edit Page

You need to add scheme selection to `admin/edit_course.php`. Add this code after the "Training Centre" field (around line 450):

```php
<!-- ADD THIS SECTION -->
<div class="form-group">
    <label class="form-label">
        <i class="fas fa-project-diagram"></i> Schemes/Projects
    </label>
    <?php
    // Fetch all active schemes
    $schemes_query = "SELECT * FROM schemes WHERE status = 'Active' ORDER BY scheme_name";
    $schemes_result = $conn->query($schemes_query);
    
    // Get currently selected schemes for this course
    $selected_schemes_query = "SELECT scheme_id FROM course_schemes WHERE course_id = ?";
    $stmt_schemes = $conn->prepare($selected_schemes_query);
    $stmt_schemes->bind_param("i", $course_id);
    $stmt_schemes->execute();
    $selected_result = $stmt_schemes->get_result();
    $selected_schemes = [];
    while ($row = $selected_result->fetch_assoc()) {
        $selected_schemes[] = $row['scheme_id'];
    }
    ?>
    
    <div style="background: #f8f9fa; padding: 16px; border-radius: 6px; border: 1px solid #dee2e6;">
        <?php if ($schemes_result && $schemes_result->num_rows > 0): ?>
            <?php while ($scheme = $schemes_result->fetch_assoc()): ?>
                <label style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px; cursor: pointer;">
                    <input type="checkbox" 
                           name="schemes[]" 
                           value="<?php echo $scheme['id']; ?>"
                           <?php echo in_array($scheme['id'], $selected_schemes) ? 'checked' : ''; ?>
                           style="width: 18px; height: 18px;">
                    <span style="font-weight: 500;"><?php echo htmlspecialchars($scheme['scheme_name']); ?></span>
                    <span style="color: #6c757d; font-size: 12px;">(<?php echo htmlspecialchars($scheme['scheme_code']); ?>)</span>
                </label>
            <?php endwhile; ?>
        <?php else: ?>
            <p style="color: #6c757d; margin: 0;">
                <i class="fas fa-info-circle"></i> No schemes available. 
                <a href="<?php echo APP_URL; ?>/schemes_module/admin/manage_schemes.php" style="color: #007bff;">Create schemes</a>
            </p>
        <?php endif; ?>
    </div>
    <small class="text-muted">Select one or more schemes/projects for this course</small>
</div>
<!-- END OF SCHEME SELECTION -->
```

## Step 4: Update Course Edit Handler

In the same `admin/edit_course.php` file, find the section where it handles `if (isset($_POST['update_course']))` (around line 70).

After the course update query executes successfully, add this code to handle scheme updates:

```php
if ($stmt->execute()) {
    // Handle scheme associations
    if (isset($_POST['schemes'])) {
        // First, delete existing associations
        $delete_schemes_sql = "DELETE FROM course_schemes WHERE course_id = ?";
        $stmt_delete = $conn->prepare($delete_schemes_sql);
        $stmt_delete->bind_param("i", $course_id);
        $stmt_delete->execute();
        
        // Then, insert new associations
        $insert_scheme_sql = "INSERT INTO course_schemes (course_id, scheme_id) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($insert_scheme_sql);
        
        foreach ($_POST['schemes'] as $scheme_id) {
            $stmt_insert->bind_param("ii", $course_id, $scheme_id);
            $stmt_insert->execute();
        }
    } else {
        // No schemes selected, delete all associations
        $delete_schemes_sql = "DELETE FROM course_schemes WHERE course_id = ?";
        $stmt_delete = $conn->prepare($delete_schemes_sql);
        $stmt_delete->bind_param("i", $course_id);
        $stmt_delete->execute();
    }
    
    // Rest of your existing code for QR generation...
```

## Step 5: Update Course Add Page (manage_courses.php)

Similarly, in `admin/manage_courses.php`, find the "Add Course" form and add the same scheme selection HTML before the submit button.

Then in the add course handler (where `if (isset($_POST['add_course']))`), after inserting the course, add:

```php
if ($stmt->execute()) {
    $new_course_id = $conn->insert_id;
    
    // Handle scheme associations for new course
    if (isset($_POST['schemes']) && !empty($_POST['schemes'])) {
        $insert_scheme_sql = "INSERT INTO course_schemes (course_id, scheme_id) VALUES (?, ?)";
        $stmt_insert = $conn->prepare($insert_scheme_sql);
        
        foreach ($_POST['schemes'] as $scheme_id) {
            $stmt_insert->bind_param("ii", $new_course_id, $scheme_id);
            $stmt_insert->execute();
        }
    }
    
    // Rest of your existing code...
```

## Step 6: Display Schemes in Course List

In your course listing page (dashboard.php or manage_courses.php), you can show which schemes are linked to each course:

```php
// In your course query, add this:
$courses_query = "SELECT c.*, 
                  GROUP_CONCAT(s.scheme_code SEPARATOR ', ') as scheme_codes
                  FROM courses c
                  LEFT JOIN course_schemes cs ON c.id = cs.course_id
                  LEFT JOIN schemes s ON cs.scheme_id = s.id
                  GROUP BY c.id
                  ORDER BY c.created_at DESC";
```

Then in your table, add a column:

```php
<td>
    <?php if (!empty($course['scheme_codes'])): ?>
        <span class="badge badge-info">
            <i class="fas fa-project-diagram"></i> <?php echo htmlspecialchars($course['scheme_codes']); ?>
        </span>
    <?php else: ?>
        <span class="badge badge-secondary">No Scheme</span>
    <?php endif; ?>
</td>
```

## Complete! 🎉

Your schemes module is now fully integrated with the course management system!

### Features:
✅ Create and manage schemes
✅ Link multiple schemes to courses
✅ View courses under each scheme
✅ Modern UI with toast notifications
✅ Confirmation dialogs for deletions

### Test It:
1. Go to Schemes/Projects and create a scheme (e.g., SCSP)
2. Edit a course and select one or more schemes
3. Save the course
4. Go back to Schemes/Projects and view the scheme - you'll see the linked courses!
