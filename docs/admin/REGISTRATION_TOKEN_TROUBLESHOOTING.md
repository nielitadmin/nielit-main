# Registration Token System - Troubleshooting Guide

## Issue Fixed: Database Error When Adding Course

### Problem
When trying to add a new course in the "Add New Course" modal, an error occurred because the `registration_token` column was missing or existing courses didn't have tokens.

### Solution Applied
✅ **Migration Run**: `migrations/add_registration_token_column.php`

**Results**:
- ✓ `registration_token` column confirmed in `courses` table
- ✓ Generated tokens for 15 existing courses
- ✓ Added index on `registration_token` for fast lookups

---

## Database Schema

### Column Details:
```sql
ALTER TABLE courses 
ADD COLUMN registration_token VARCHAR(255) DEFAULT NULL;

CREATE INDEX idx_registration_token ON courses(registration_token);
```

**Column Properties**:
- **Type**: VARCHAR(255)
- **Default**: NULL
- **Indexed**: Yes
- **Location**: After `apply_link` column

---

## Token Generation Results

The migration generated tokens for the following courses:

| Course ID | Course Code | Generated Token |
|-----------|-------------|-----------------|
| 2 | al | Vqc1l4wI |
| 6 | chmt | lL7N4jUX |
| 7 | ccaapa | Ky9Ipj4p |
| 8 | cdeo | JGEdZ7sa |
| 9 | cwd | 2zkLYjOh |
| 10 | cmd | b5TuPC0t |
| 11 | paa | Q3Iyf31O |
| 12 | fciot | zUsQUjs9 |
| 13 | fcml | 2lLRSygy |
| 14 | ccc | HWKFW32s |
| 15 | fcis | 9WMyu1RU |
| 43 | dbc21 | Jo87VW7s |
| 47 | DBC24 | A5XRQVpc |
| 54 | SAS | LF5KXgur |
| 55 | DBC-2026 | 9yWqLiX7 |

---

## Testing Checklist

### ✅ After Migration:

1. **Test Adding New Course**:
   - Open "Add New Course" modal
   - Fill in course details
   - Click "Generate" for registration link
   - Verify token appears in link
   - Submit form
   - ✅ Should save without errors

2. **Test Existing Courses**:
   - Edit any existing course
   - Check that registration link shows token
   - Verify link format: `?token=XXXXXXXX`
   - Save changes
   - ✅ Should save without errors

3. **Test Registration Links**:
   - Copy a course registration link
   - Paste in browser
   - ✅ Should load registration form

4. **Verify Token Uniqueness**:
   ```sql
   SELECT registration_token, COUNT(*) as count 
   FROM courses 
   WHERE registration_token IS NOT NULL 
   GROUP BY registration_token 
   HAVING count > 1;
   ```
   - ✅ Should return 0 rows (no duplicates)

---

## Common Errors & Solutions

### Error 1: "Column 'registration_token' doesn't exist"

**Cause**: Migration not run or failed

**Solution**:
```bash
cd c:\xampp\htdocs\public_html\migrations
c:\xampp\php\php.exe add_registration_token_column.php
```

### Error 2: "Cannot insert NULL into registration_token"

**Cause**: Column set to NOT NULL without default

**Solution**:
```sql
ALTER TABLE courses 
MODIFY COLUMN registration_token VARCHAR(255) DEFAULT NULL;
```

### Error 3: "Duplicate entry for registration_token"

**Cause**: Same token generated twice (extremely rare)

**Solution**: The JavaScript will auto-generate a new token. If it persists:
```sql
-- Find duplicate
SELECT registration_token, COUNT(*) 
FROM courses 
GROUP BY registration_token 
HAVING COUNT(*) > 1;

-- Manually update one course with new token
UPDATE courses 
SET registration_token = 'NewToken8' 
WHERE id = <duplicate_course_id>;
```

### Error 4: "Apply link not updating with token"

**Cause**: JavaScript not running or PHP variable not set

**Solution**:
1. Clear browser cache (Ctrl+F5)
2. Check browser console for JavaScript errors
3. Verify APP_URL is defined in config.php:
```php
define('APP_URL', 'http://localhost/public_html');
```

---

## Manual Token Generation

If you need to manually generate a token for a course:

```sql
-- Update a specific course
UPDATE courses 
SET registration_token = 'Ab3Xk9Qz' 
WHERE id = <course_id>;

-- Update apply_link to match
UPDATE courses 
SET apply_link = 'http://localhost/public_html/student/register.php?token=Ab3Xk9Qz' 
WHERE id = <course_id>;
```

**Token Format**:
- Length: 8 characters
- Characters: A-Z, a-z, 0-9
- Example: `Ab3Xk9Qz`, `Xy7PqR2m`

---

## Migration File Location

**File**: `migrations/add_registration_token_column.php`

**What it does**:
1. Checks if `registration_token` column exists
2. Adds column if missing
3. Creates index for fast lookups
4. Generates tokens for existing courses without them
5. Reports results

**To re-run** (safe - checks before adding):
```bash
cd c:\xampp\htdocs\public_html\migrations
c:\xampp\php\php.exe add_registration_token_column.php
```

---

## Verification Queries

### Check column exists:
```sql
SHOW COLUMNS FROM courses LIKE 'registration_token';
```

### Count courses with tokens:
```sql
SELECT 
    COUNT(*) as total_courses,
    SUM(CASE WHEN registration_token IS NOT NULL THEN 1 ELSE 0 END) as with_tokens,
    SUM(CASE WHEN registration_token IS NULL THEN 1 ELSE 0 END) as without_tokens
FROM courses;
```

### List courses without tokens:
```sql
SELECT id, course_code, course_name, apply_link 
FROM courses 
WHERE registration_token IS NULL 
OR registration_token = '';
```

### Check for duplicate tokens:
```sql
SELECT registration_token, GROUP_CONCAT(id) as course_ids, COUNT(*) as count
FROM courses 
WHERE registration_token IS NOT NULL
GROUP BY registration_token
HAVING COUNT(*) > 1;
```

---

## Related Documentation

- **`docs/admin/REGISTRATION_LINK_TOKEN_UPDATE.md`** - Feature overview
- **`docs/admin/COURSE_CODE_AUTO_GENERATION_DASHBOARD.md`** - Auto-generation feature
- **`migrations/add_registration_token_column.php`** - Migration script

---

## Status

✅ **Issue Resolved**  
✅ **Migration Complete**  
✅ **15 Courses Updated**  
✅ **System Ready for Use**

---

**Last Updated**: June 2, 2026  
**Status**: All systems operational
