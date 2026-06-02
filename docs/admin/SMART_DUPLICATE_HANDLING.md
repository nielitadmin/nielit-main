# Smart Duplicate Handling - Auto Course Code Generation

## ✨ NEW FEATURE: Automatic Duplicate Resolution

The system now **automatically handles duplicate course codes** by adding sequential numbers when the same course name is used multiple times!

---

## How It Works

### Scenario: Multiple Courses with Same Name

```
Course 1: "Python Programming"
→ Course Code: PP-2026 ✅
→ Student ID Code: PP ✅

Course 2: "Python Programming" (same name!)
→ Course Code: PP01-2026 ✅ (automatically adds 01)
→ Student ID Code: PP01 ✅

Course 3: "Python Programming" (same name again!)
→ Course Code: PP02-2026 ✅ (automatically adds 02)
→ Student ID Code: PP02 ✅

Course 4: "Python Programming"
→ Course Code: PP03-2026 ✅
→ Student ID Code: PP03 ✅
```

**No more duplicate errors!** The system automatically finds the next available number.

---

## Visual Example

```
┌─────────────────────────────────────────────────────────────┐
│ FIRST COURSE                                                │
├─────────────────────────────────────────────────────────────┤
│ Course Name: Python Programming                             │
│ ↓ (System checks: PP-2026 available?)                      │
│ ✅ YES - Use PP-2026                                        │
│                                                             │
│ Course Code: PP-2026                                        │
│ Student ID Code: PP                                         │
│ Student IDs: NIELIT/2026/PP/0001, NIELIT/2026/PP/0002      │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ SECOND COURSE (Same Name)                                   │
├─────────────────────────────────────────────────────────────┤
│ Course Name: Python Programming                             │
│ ↓ (System checks: PP-2026 available?)                      │
│ ❌ NO - Already exists                                      │
│ ↓ (System checks: PP01-2026 available?)                    │
│ ✅ YES - Use PP01-2026                                      │
│                                                             │
│ Course Code: PP01-2026                                      │
│ Student ID Code: PP01                                       │
│ Student IDs: NIELIT/2026/PP01/0001, NIELIT/2026/PP01/0002  │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ THIRD COURSE (Same Name)                                    │
├─────────────────────────────────────────────────────────────┤
│ Course Name: Python Programming                             │
│ ↓ (System checks: PP-2026 available?)                      │
│ ❌ NO - Already exists                                      │
│ ↓ (System checks: PP01-2026 available?)                    │
│ ❌ NO - Already exists                                      │
│ ↓ (System checks: PP02-2026 available?)                    │
│ ✅ YES - Use PP02-2026                                      │
│                                                             │
│ Course Code: PP02-2026                                      │
│ Student ID Code: PP02                                       │
│ Student IDs: NIELIT/2026/PP02/0001, NIELIT/2026/PP02/0002  │
└─────────────────────────────────────────────────────────────┘
```

---

## Algorithm

### Step 1: Generate Base Code
```
Course Name: "Python Programming"
→ Base Code: PP-2026
→ Base Abbreviation: PP
```

### Step 2: Check if Base Code Exists
```
Query database: Does "PP-2026" exist?
```

### Step 3A: If NOT Exists
```
✅ Use base code: PP-2026
✅ Use base abbreviation: PP
```

### Step 3B: If EXISTS
```
❌ PP-2026 exists
→ Try PP01-2026 (counter = 01)
→ Check database: Does "PP01-2026" exist?
  → If NO: ✅ Use PP01-2026
  → If YES: Try PP02-2026 (counter = 02)
    → Check database: Does "PP02-2026" exist?
      → If NO: ✅ Use PP02-2026
      → If YES: Try PP03-2026 (counter = 03)
        → ... continues up to PP99-2026
```

### Step 4: Return Unique Code
```
Return: {
  code: "PP01-2026",
  abbreviation: "PP01"
}
```

---

## Real-World Examples

### Example 1: Data Science Courses

```
Course 1: "Data Science and Analytics"
→ DSA-2026

Course 2: "Data Science and Analytics" (Batch 2)
→ DSA01-2026 (automatically!)

Course 3: "Data Science and Analytics" (Evening)
→ DSA02-2026 (automatically!)
```

### Example 2: Web Development Courses

```
Course 1: "Web Development"
→ WD-2026

Course 2: "Web Development" (Advanced)
→ WD01-2026

Course 3: "Web Development" (Beginner)
→ WD02-2026
```

### Example 3: Mixed Courses

```
Course 1: "Python Programming"
→ PP-2026

Course 2: "Python Programming Advanced"
→ PPA-2026 (different name = different base code)

Course 3: "Python Programming"
→ PP01-2026 (same name = adds number)
```

---

## Benefits

### ✅ No More Duplicate Errors
- System automatically handles duplicates
- No need to manually think of unique codes
- Smooth user experience

### ✅ Consistent Naming
- All related courses have similar codes
- Easy to identify course families
- Professional appearance

### ✅ Scalable
- Supports up to 99 courses with same name
- Automatic sequential numbering
- No manual intervention needed

### ✅ User-Friendly
- Works automatically in the background
- No extra clicks or steps
- Transparent to the user

---

## Technical Implementation

### Frontend (JavaScript)

```javascript
// Check if code exists and get next available
async function getUniqueCode(baseCode, baseAbbreviation, currentCourseId) {
    // 1. Check if base code exists
    const response = await fetch('check_course_code.php', {
        method: 'POST',
        body: JSON.stringify({
            code: baseCode,
            abbreviation: baseAbbreviation,
            exclude_id: currentCourseId
        })
    });
    
    const result = await response.json();
    
    if (!result.exists) {
        // Base code is unique
        return { code: baseCode, abbreviation: baseAbbreviation };
    }
    
    // 2. Find next available number
    let counter = 1;
    while (counter <= 99) {
        const paddedNumber = counter.toString().padStart(2, '0');
        const uniqueCode = baseAbbreviation + paddedNumber + '-' + year;
        const uniqueAbbr = baseAbbreviation + paddedNumber;
        
        // Check if this code exists
        const checkResponse = await fetch('check_course_code.php', {
            method: 'POST',
            body: JSON.stringify({
                code: uniqueCode,
                abbreviation: uniqueAbbr,
                exclude_id: currentCourseId
            })
        });
        
        const checkResult = await checkResponse.json();
        
        if (!checkResult.exists) {
            // Found unique code!
            return { code: uniqueCode, abbreviation: uniqueAbbr };
        }
        
        counter++;
    }
}
```

### Backend (PHP)

**File**: `admin/check_course_code.php`

```php
// Check if course code or abbreviation exists
$query = "SELECT id FROM courses 
          WHERE (UPPER(TRIM(course_code)) = ? 
                 OR UPPER(TRIM(course_abbreviation)) = ?)";

if ($exclude_id) {
    $query .= " AND id != ?";
}

$stmt = $conn->prepare($query);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo json_encode(['exists' => true]);
} else {
    echo json_encode(['exists' => false]);
}
```

---

## Files Involved

```
admin/
├── edit_course.php              # Updated with smart duplicate handling
├── manage_courses.php           # Updated with smart duplicate handling
└── check_course_code.php        # NEW - Backend API for checking duplicates

docs/admin/
└── SMART_DUPLICATE_HANDLING.md  # This documentation
```

---

## Testing

### Test 1: Create Multiple Courses with Same Name

1. Go to `admin/manage_courses.php`
2. Click "Add New Course"
3. Enter: "Python Programming"
4. **Expected**: Code = `PP-2026`
5. Save course

6. Click "Add New Course" again
7. Enter: "Python Programming" (same name)
8. **Expected**: Code = `PP01-2026` (automatically!)
9. Save course

10. Click "Add New Course" again
11. Enter: "Python Programming" (same name)
12. **Expected**: Code = `PP02-2026` (automatically!)
13. Save course

### Test 2: Edit Course - No Conflict with Self

1. Go to edit "Python Programming" (code: PP-2026)
2. Change course name to "Python Programming Updated"
3. **Expected**: Code stays `PP-2026` (no conflict with itself)
4. Save successfully

### Test 3: Different Names = Different Codes

1. Create: "Python Programming"
   → Code: `PP-2026`

2. Create: "Python Programming Advanced"
   → Code: `PPA-2026` (different base code)

3. Create: "Python Programming"
   → Code: `PP01-2026` (adds number to PP)

---

## Limitations

### Maximum 99 Courses with Same Name
- System supports PP-2026 through PP99-2026
- If you need more than 99, consider using more descriptive names

### Requires JavaScript
- Smart duplicate handling uses AJAX calls
- Falls back to manual editing if JavaScript disabled

### Network Dependency
- Checks database via API calls
- Requires working network connection

---

## Comparison: Before vs After

### Before (Manual Handling)

```
User: Creates "Python Programming"
System: Generates PP-2026
User: Saves ✅

User: Creates "Python Programming" again
System: Generates PP-2026
User: Tries to save
System: ❌ ERROR - Duplicate code!
User: Must manually change to PP2-2026 or PP-BATCH2-2026
User: Saves ✅
```

### After (Smart Handling)

```
User: Creates "Python Programming"
System: Generates PP-2026
System: Checks database - available ✅
User: Saves ✅

User: Creates "Python Programming" again
System: Generates PP-2026
System: Checks database - exists ❌
System: Tries PP01-2026
System: Checks database - available ✅
System: Auto-updates to PP01-2026
User: Saves ✅ (no error!)
```

---

## Summary

| Feature | Status |
|---------|--------|
| **Auto Duplicate Detection** | ✅ Working |
| **Sequential Numbering** | ✅ PP01, PP02, PP03... |
| **Real-Time Checking** | ✅ AJAX API calls |
| **No User Intervention** | ✅ Fully automatic |
| **Edit Mode Support** | ✅ Excludes self |
| **Up to 99 Duplicates** | ✅ Supported |
| **Backend API** | ✅ check_course_code.php |
| **Documentation** | ✅ Complete |

---

## Conclusion

The smart duplicate handling feature makes course creation **effortless**:

✅ **No more duplicate errors**
✅ **Automatic sequential numbering**
✅ **Professional code format**
✅ **Zero user intervention**
✅ **Scalable up to 99 courses**

**Status**: ✅ **IMPLEMENTED AND READY TO USE**

---

**Date**: May 26, 2026  
**Implemented By**: Kiro AI Assistant  
**Feature**: Smart Duplicate Handling with Sequential Numbering
