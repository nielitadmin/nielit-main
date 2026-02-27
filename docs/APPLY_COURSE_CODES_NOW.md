# 🚀 Apply Course Codes - Quick Guide

## ⚠️ Issue with Auto-Generated Codes

The auto-fix tool generated some codes with special characters `(` which won't work in URLs. I've created better codes for you.

## ✅ Better Solution - Use SQL

### Option 1: Apply All Codes at Once (Recommended)

1. **Open phpMyAdmin:**
   ```
   http://localhost/phpmyadmin
   ```

2. **Select your database** (probably `nielit_bhubaneswar`)

3. **Click "SQL" tab**

4. **Copy and paste this SQL:**
   ```sql
   -- Core Courses
   UPDATE courses SET course_code = 'ol', course_abbreviation = 'OL' WHERE id = 1;
   UPDATE courses SET course_code = 'al', course_abbreviation = 'AL' WHERE id = 2;
   
   -- Technical Courses
   UPDATE courses SET course_code = 'chmt', course_abbreviation = 'CHMT' WHERE id = 6;
   UPDATE courses SET course_code = 'ccaapa', course_abbreviation = 'CCAAPA' WHERE id = 7;
   UPDATE courses SET course_code = 'cdeo', course_abbreviation = 'CDEO' WHERE id = 8;
   UPDATE courses SET course_code = 'cwd', course_abbreviation = 'CWD' WHERE id = 9;
   UPDATE courses SET course_code = 'cmd', course_abbreviation = 'CMD' WHERE id = 10;
   
   -- Assembly & IoT Courses
   UPDATE courses SET course_code = 'paa', course_abbreviation = 'PAA' WHERE id = 11;
   UPDATE courses SET course_code = 'fciot', course_abbreviation = 'FCIOT' WHERE id = 12;
   UPDATE courses SET course_code = 'fcml', course_abbreviation = 'FCML' WHERE id = 13;
   UPDATE courses SET course_code = 'ccc', course_abbreviation = 'CCC' WHERE id = 14;
   UPDATE courses SET course_code = 'fcis', course_abbreviation = 'FCIS' WHERE id = 15;
   
   -- Certificate Courses
   UPDATE courses SET course_code = 'cciot', course_abbreviation = 'CCIOT' WHERE id = 16;
   UPDATE courses SET course_code = 'ccdt', course_abbreviation = 'CCDT' WHERE id = 17;
   UPDATE courses SET course_code = 'ccpy', course_abbreviation = 'CCPY' WHERE id = 18;
   UPDATE courses SET course_code = 'ccpaa', course_abbreviation = 'CCPAA' WHERE id = 19;
   
   -- Programming
   UPDATE courses SET course_code = 'python', course_abbreviation = 'PYTHON' WHERE id = 32;
   
   -- Drone Boot Camps
   UPDATE courses SET course_code = 'dbc13', course_abbreviation = 'DBC13' WHERE id = 33;
   UPDATE courses SET course_code = 'dbc14', course_abbreviation = 'DBC14' WHERE id = 34;
   UPDATE courses SET course_code = 'dbc15', course_abbreviation = 'DBC15' WHERE id = 35;
   UPDATE courses SET course_code = 'dbc16', course_abbreviation = 'DBC16' WHERE id = 36;
   UPDATE courses SET course_code = 'dbc17', course_abbreviation = 'DBC17' WHERE id = 39;
   UPDATE courses SET course_code = 'dbc18', course_abbreviation = 'DBC18' WHERE id = 40;
   UPDATE courses SET course_code = 'dbc19', course_abbreviation = 'DBC19' WHERE id = 41;
   UPDATE courses SET course_code = 'dbc20', course_abbreviation = 'DBC20' WHERE id = 42;
   UPDATE courses SET course_code = 'dbc21', course_abbreviation = 'DBC21' WHERE id = 43;
   UPDATE courses SET course_code = 'dbc22', course_abbreviation = 'DBC22' WHERE id = 45;
   UPDATE courses SET course_code = 'dbc23', course_abbreviation = 'DBC23' WHERE id = 46;
   UPDATE courses SET course_code = 'dbc24', course_abbreviation = 'DBC24' WHERE id = 47;
   ```

5. **Click "Go"**

6. **Done!** All courses now have proper codes.

### Option 2: Use the SQL File

1. Open phpMyAdmin
2. Select your database
3. Click "Import" tab
4. Choose file: `apply_better_codes.sql`
5. Click "Go"

## 📋 Course Code Reference

| ID | Course Name | Code | Abbreviation | URL Example |
|----|-------------|------|--------------|-------------|
| 1 | IT-O Level | `ol` | `OL` | `?course=ol` |
| 2 | A Level | `al` | `AL` | `?course=al` |
| 6 | Computer Hardware | `chmt` | `CHMT` | `?course=chmt` |
| 7 | Computer Application | `ccaapa` | `CCAAPA` | `?course=ccaapa` |
| 8 | Data Entry | `cdeo` | `CDEO` | `?course=cdeo` |
| 9 | Web Developer | `cwd` | `CWD` | `?course=cwd` |
| 10 | Multimedia Developer | `cmd` | `CMD` | `?course=cmd` |
| 14 | CCC | `ccc` | `CCC` | `?course=ccc` |
| 32 | Python Programming | `python` | `PYTHON` | `?course=python` |
| 33-47 | Drone Boot Camps | `dbc13`-`dbc24` | `DBC13`-`DBC24` | `?course=dbc13` |
| 54 | SAS | `sas` | `SAS` | `?course=sas` ✅ Already set |

## 🧪 Test After Applying

### Test O-Level Registration:
```
http://localhost/public_html/student/register.php?course=ol
```

### Test A-Level Registration:
```
http://localhost/public_html/student/register.php?course=al
```

### Test CCC Registration:
```
http://localhost/public_html/student/register.php?course=ccc
```

### Test SAS Registration (already working):
```
http://localhost/public_html/student/register.php?course=sas
```

## ✅ Expected Result

After applying codes:
- ✅ All registration links work
- ✅ Course info card displays correctly
- ✅ Form submission succeeds
- ✅ Student IDs generated correctly (e.g., `NIELIT/2026/OL/0001`)

## 🔍 Verify Codes Were Applied

Run this SQL to check:
```sql
SELECT id, course_name, course_code, course_abbreviation 
FROM courses 
WHERE course_code IS NOT NULL AND course_code != ''
ORDER BY id;
```

You should see all courses with their codes set.

## 📝 Why These Codes Are Better

**Problems with auto-generated codes:**
- Had special characters `(` that break URLs
- Drone camps all had same code `dbcn` (not unique)
- Some codes were too complex

**Better codes:**
- ✅ No special characters
- ✅ All unique
- ✅ Short and memorable
- ✅ URL-safe
- ✅ Professional looking

## 🎯 Next Steps

1. **Apply the SQL** (2 minutes)
2. **Test registration** with any course code
3. **Fill out the form** completely
4. **Upload 3 required files**
5. **Submit and verify** it works!

---

**Time to complete:** 2 minutes
**Difficulty:** Easy
**Files:** `apply_better_codes.sql` (ready to use)
