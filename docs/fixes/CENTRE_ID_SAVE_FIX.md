# Training Centre ID Save Fix

## Problem
The `edit_course.php` page had a Training Centre dropdown, but when editing a course, the `centre_id` value was not being saved to the database. The field existed in the form but was not included in the UPDATE query.

## Solution Applied

### 1. Updated `admin/edit_course.php`

#### Added centre_id to POST data capture:
```php
$centre_id = !empty($_POST['centre_id']) ? intval($_POST['centre_id']) : null;
```

#### Updated the UPDATE query to include centre_id:
```sql
UPDATE courses SET 
    course_name = ?, 
    course_code = ?,
    course_abbreviation = ?,
    eligibility = ?, 
    duration = ?, 
    training_fees = ?, 
    category = ?, 
    start_date = ?, 
    end_date = ?, 
    description_url = ?, 
    description_pdf = ?, 
    course_flyer = ?,
    apply_link = ?,
    course_coordinator = ?,
    training_center = ?,
    centre_id = ?,          -- ADDED THIS
    link_published = ?
    WHERE id = ?
```

#### Updated bind_param to include centre_id:
```php
$stmt->bind_param("sssssssssssssssiiii",  // Added extra 'i' for centre_id
    $course_name,
    $course_code,
    $course_abbreviation,
    $eligibility,
    $duration,
    $training_fees,
    $category,
    $start_date,
    $end_date,
    $description_url,
    $description_pdf,
    $course_flyer,
    $apply_link,
    $course_coordinator,
    $training_center,
    $centre_id,           -- ADDED THIS
    $link_published,
    $course_id
);
```

#### Replaced hardcoded dropdown with dynamic centre loading:
```php
<select class="form-select" name="centre_id" required>
    <option value="">--Select Training Centre--</option>
    <?php
    // Fetch all active centres from database
    $centres_query = "SELECT id, name, code FROM centres WHERE is_active = 1 ORDER BY name";
    $centres_result = $conn->query($centres_query);
    
    if ($centres_result && $centres_result->num_rows > 0) {
        while ($centre = $centres_result->fetch_assoc()) {
            $selected = ($course['centre_id'] == $centre['id']) ? 'selected' : '';
            echo '<option value="' . $centre['id'] . '" ' . $selected . '>' . htmlspecialchars($centre['name']) . '</option>';
        }
    }
    ?>
</select>
```

### 2. Updated `migrations/assign_courses_to_centre.php`

#### Smart Assignment Logic:
- Courses with "Balasore" in the name → Assigned to NIELIT Balasore Extension (centre_id = 2)
- All other courses → Assigned to NIELIT Bhubaneswar (centre_id = 1)

#### Two-Step Assignment Process:
```php
// Step 1: Assign Balasore courses
UPDATE courses SET centre_id = 2 
WHERE centre_id IS NULL 
AND (course_name LIKE '%Balasore%' OR course_name LIKE '%balasore%')

// Step 2: Assign remaining courses to Bhubaneswar
UPDATE courses SET centre_id = 1 
WHERE centre_id IS NULL
```

## Testing

### Test the Fix:
1. Go to `admin/edit_course.php?id=1` (or any course ID)
2. Select a training centre from the dropdown
3. Click "Update Course"
4. Verify the centre_id is saved in the database
5. Reload the page - the selected centre should still be selected

### Run the Migration:
1. Open browser: `http://your-site.com/migrations/assign_courses_to_centre.php`
2. The script will:
   - Find all courses with NULL centre_id
   - Assign Balasore courses to NIELIT Balasore Extension
   - Assign all other courses to NIELIT Bhubaneswar
   - Show a summary of assignments

## Database Structure

### centres table:
- ID 1: NIELIT Bhubaneswar (code: BBSR)
- ID 2: NIELIT Balasore Extension (code: BALA)

### courses table:
- `centre_id` (INT, nullable, foreign key to centres.id)
- `training_center` (VARCHAR, legacy field - kept for backward compatibility)

## Files Modified
- `admin/edit_course.php` - Added centre_id save functionality
- `migrations/assign_courses_to_centre.php` - Smart assignment logic for Balasore courses

## Next Steps
1. Run the migration script to assign all existing courses
2. Test editing a course and changing its training centre
3. Verify the Filter by Training Centre works on courses.php
4. Consider removing the legacy `training_center` text field in future updates

## Status
✅ COMPLETE - Training Centre dropdown now properly saves centre_id to database
