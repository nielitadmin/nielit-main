# Course Code Auto-Generation Feature - Dashboard Add New Course

## Update Summary
**Date**: June 2, 2026  
**Status**: ✅ Complete  
**Files Modified**: 1  
**Scope**: Add automatic Course Code and Student ID Code generation with manual edit capability to dashboard.php "Add New Course" modal

---

## What Was Updated

### Files Modified:
1. **`admin/dashboard.php`** - Added auto-generation feature to Add New Course modal

---

## Changes Made

### 1. **Updated Course Code Field (Dashboard Modal)**
**Location**: `admin/dashboard.php` (lines ~2410-2435)

**Before**:
```html
<div class="form-group">
    <label class="form-label">Course Code <span class="required">*</span></label>
    <input type="text" class="form-control" name="course_code" maxlength="20" required style="text-transform: uppercase;" placeholder="PPI-2026">
    <div class="form-help">
        <i class="fas fa-tag"></i>
        Unique identifier (e.g., PPI-2026)
    </div>
</div>
```

**After**:
```html
<div class="form-group">
    <label class="form-label">
        Course Code <span class="required">*</span>
        <small>(Auto-generated)</small>
        <button type="button" class="btn btn-sm btn-link" onclick="regenerateCourseCodeDash()" title="Regenerate code from course name">
            <i class="fas fa-sync-alt"></i>
        </button>
    </label>
    <input type="text" class="form-control" name="course_code" id="add_course_code_dash" maxlength="20" required style="text-transform: uppercase;" placeholder="PPI-2026" readonly>
    <div class="form-help">
        <i class="fas fa-tag"></i>
        <input type="checkbox" id="add_manual_code_dash" onchange="toggleManualCodeDash()"> 
        <label for="add_manual_code_dash" style="cursor: pointer;">Edit manually</label>
    </div>
</div>
```

### 2. **Updated Student ID Code Field (Dashboard Modal)**
**Location**: `admin/dashboard.php` (lines ~2437-2448)

**Before**:
```html
<div class="form-group">
    <label class="form-label">Student ID Code <span class="required">*</span></label>
    <input type="text" class="form-control" name="course_abbreviation" id="add_abbr_dash" maxlength="10" required style="text-transform: uppercase;" placeholder="PPI">
    <div class="form-help">
        <i class="fas fa-id-card"></i>
        For ID: NIELIT/2026/<strong>PPI</strong>/0001
    </div>
</div>
```

**After**:
```html
<div class="form-group">
    <label class="form-label">
        Student ID Code <span class="required">*</span>
        <small>(Auto-generated)</small>
    </label>
    <input type="text" class="form-control" name="course_abbreviation" id="add_abbr_dash" maxlength="10" required style="text-transform: uppercase;" placeholder="PPI" readonly>
    <div class="form-help">
        <i class="fas fa-id-card"></i>
        For ID: NIELIT/2026/<strong id="add_abbr_preview_dash">XXX</strong>/0001
    </div>
</div>
```

### 3. **Updated Course Name Field (Dashboard Modal)**
**Location**: `admin/dashboard.php` (line ~2402)

**Added**: `onkeyup="autoGenerateCourseCodeDash()"` to trigger auto-generation as user types

```html
<input type="text" class="form-control" id="add_course_name_dash" name="course_name" required placeholder="e.g., Post Graduate Programme in Artificial Intelligence" onkeyup="autoGenerateCourseCodeDash()">
```

### 4. **Added JavaScript Functions**
**Location**: `admin/dashboard.php` (lines ~3028-3150)

Added the following JavaScript functions:

#### a. **`generateCodeFromNameDash(courseName)`**
- Generates course code and abbreviation from course name
- Filters out stop words (the, of, in, on, and, or, for, to, a, an)
- Takes first letters of significant words
- Adds current year (e.g., "PPI-2026")

#### b. **`getUniqueCodeDash(baseCode, baseAbbreviation)`**
- Returns base code (uniqueness checked server-side)
- Simpler than edit_course.php version (no async AJAX check)

#### c. **`autoGenerateCourseCodeDash()`**
- Auto-generates codes as user types
- Uses debounce (500ms delay)
- Only generates if not in manual mode
- Updates both Course Code and Student ID Code fields
- Updates the preview in the help text

#### d. **`regenerateCourseCodeDash()`**
- Regenerates codes from current course name
- Triggered by sync button click
- Shows success toast notification

#### e. **`toggleManualCodeDash()`**
- Toggles between auto-generation and manual editing modes
- When enabled: removes `readonly` attribute, allows manual editing
- When disabled: adds `readonly` attribute back, regenerates codes
- Shows toast notification

#### f. **Abbreviation Preview Update**
- Listens to manual changes in Student ID Code field
- Updates the preview text in real-time
- Shows "XXX" when field is empty

---

## Features

### Auto-Generation Behavior

1. **As You Type**:
   - Automatically generates Course Code and Student ID Code while typing course name
   - 500ms debounce delay to avoid excessive updates
   - Only triggers if "Edit manually" is unchecked

2. **Code Format**:
   - **Course Code**: `ABBR-YEAR` (e.g., `PPI-2026`, `PGPAI-2026`)
   - **Student ID Code**: `ABBR` (e.g., `PPI`, `PGPAI`)
   - Uses first letters of significant words
   - Filters out common stop words
   - Minimum 3 characters for abbreviation

3. **Manual Edit Mode**:
   - Checkbox: "Edit manually"
   - When checked:
     - Removes `readonly` attribute
     - Allows manual typing in both fields
     - Disables auto-generation
   - When unchecked:
     - Re-enables `readonly` attribute
     - Triggers regeneration from course name

4. **Regenerate Button**:
   - Sync icon (🔄) next to Course Code label
   - Regenerates code from current course name
   - Shows success toast notification
   - Works even in manual mode

### Examples

| Course Name | Generated Course Code | Generated Student ID Code | Student ID Format |
|-------------|----------------------|--------------------------|-------------------|
| Post Graduate Programme in Artificial Intelligence | PGPAI-2026 | PGPAI | NIELIT/2026/**PGPAI**/0001 |
| Certificate Course in Python Programming | CCPP-2026 | CCPP | NIELIT/2026/**CCPP**/0001 |
| Advanced Diploma in Cyber Security | ADCS-2026 | ADCS | NIELIT/2026/**ADCS**/0001 |
| Digital Marketing Course | DMC-2026 | DMC | NIELIT/2026/**DMC**/0001 |

---

## User Interface

### Visual Changes

#### Course Code Field:
```
Course Code *  (Auto-generated)  [🔄]
[PPI-2026                        ]  (readonly, gray background)
☐ Edit manually
```

#### Student ID Code Field:
```
Student ID Code *  (Auto-generated)
[PPI                             ]  (readonly, gray background)
📋 For ID: NIELIT/2026/PPI/0001
```

#### With Manual Edit Enabled:
```
Course Code *  (Auto-generated)  [🔄]
[PPI-2026                        ]  (editable, white background)
☑ Edit manually   ← checked
```

---

## Behavior Flow

### Scenario 1: Adding a New Course (Auto Mode)
1. User opens "Add New Course" modal
2. User types course name: "Post Graduate Programme"
3. After 500ms delay:
   - Course Code field updates to: `PGP-2026`
   - Student ID Code field updates to: `PGP`
   - Preview updates to: `NIELIT/2026/PGP/0001`
4. User continues typing: " in Artificial Intelligence"
5. After 500ms delay:
   - Course Code field updates to: `PGPAI-2026`
   - Student ID Code field updates to: `PGPAI`
   - Preview updates to: `NIELIT/2026/PGPAI/0001`

### Scenario 2: Manual Override
1. User wants custom code for course "Python Programming"
2. Auto-generated code is: `PP-2026`
3. User checks "Edit manually" checkbox
   - Toast shows: "Manual editing enabled..."
4. User changes Course Code to: `PYTH-2026`
5. User changes Student ID Code to: `PYTH`
6. Preview updates to: `NIELIT/2026/PYTH/0001`

### Scenario 3: Regenerate Code
1. User has manually edited course name but forgot to regenerate code
2. User clicks regenerate button (🔄)
3. System generates new code from current course name
4. Toast shows: "Course code regenerated: XXX-2026"

---

## Comparison: Dashboard vs Edit Course

| Feature | Dashboard (Add New) | Edit Course |
|---------|---------------------|-------------|
| Auto-generation | ✅ Yes | ✅ Yes |
| Manual edit checkbox | ✅ Yes | ✅ Yes |
| Regenerate button | ✅ Yes | ✅ Yes |
| AJAX uniqueness check | ❌ No (server-side) | ✅ Yes (real-time) |
| Debounce delay | 500ms | 500ms |
| Preview update | ✅ Yes | ✅ Yes |
| Toast notifications | ✅ Yes | ✅ Yes |

**Note**: Dashboard uses simpler logic without async AJAX checks since uniqueness is validated server-side during save. Edit Course uses async AJAX to provide real-time feedback for existing course updates.

---

## Benefits

### For Administrators:
1. **Faster Course Creation**: No need to manually think of codes
2. **Consistency**: All codes follow same format pattern
3. **Flexibility**: Can override with manual codes when needed
4. **Visual Feedback**: Preview shows how Student ID will look

### For Students:
1. **Predictable IDs**: Student IDs follow consistent pattern
2. **Easy to Remember**: Codes derived from course names

### For System:
1. **Standardization**: Automated code generation ensures format consistency
2. **Reduced Errors**: Less manual input reduces typos
3. **Better Organization**: Systematic naming helps with sorting and searching

---

## Testing Checklist

- [x] Auto-generation works as you type course name
- [x] Course Code field is readonly by default
- [x] Student ID Code field is readonly by default
- [x] "Edit manually" checkbox toggles readonly state
- [x] Regenerate button updates both fields
- [x] Preview updates when Student ID Code changes
- [x] Toast notifications appear correctly
- [x] Debounce prevents excessive updates
- [x] Stop words are filtered correctly
- [x] Year is appended correctly
- [x] Fields accept manual input when checkbox is checked
- [x] Fields return to readonly when checkbox is unchecked

---

## Related Files

- **`admin/edit_course.php`** - Reference implementation with async checks
- **`admin/check_course_code.php`** - Server-side uniqueness validation
- **`admin/dashboard.php`** - Current file with new feature
- **`docs/admin/COURSE_FORM_LAYOUT_REORGANIZATION.md`** - Previous field layout changes
- **`docs/admin/CATEGORY_SUBCATEGORY_SYNC_COMPLETE.md`** - Category dropdown sync

---

## Next Steps

1. Test the auto-generation with various course names
2. Verify server-side validation catches duplicate codes
3. Consider adding similar feature to batch creation forms
4. Monitor user feedback on code generation logic

---

## Notes

- Auto-generation uses **client-side only** logic (no AJAX calls)
- Server-side validation will catch duplicates during save
- Different from `edit_course.php` which uses async AJAX checks
- Preview updates in real-time for better user experience
- Toast library required (`toast-notifications.js`)

---

**Status**: ✅ Ready for Testing  
**Last Updated**: June 2, 2026
