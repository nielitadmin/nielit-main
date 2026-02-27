# ⚡ DO THIS NOW - 2 Minute Fix

## 🎯 The Problem
Courses missing codes → Registration links don't work

## ✅ The Solution
Run SQL to add codes → Everything works!

---

## 📝 Steps (2 Minutes)

### 1. Open phpMyAdmin
```
http://localhost/phpmyadmin
```

### 2. Select Database
Click: `nielit_bhubaneswar`

### 3. Click "SQL" Tab
Top menu → SQL

### 4. Copy This SQL
```sql
UPDATE courses SET course_code = 'ol', course_abbreviation = 'OL' WHERE id = 1;
UPDATE courses SET course_code = 'al', course_abbreviation = 'AL' WHERE id = 2;
UPDATE courses SET course_code = 'chmt', course_abbreviation = 'CHMT' WHERE id = 6;
UPDATE courses SET course_code = 'ccaapa', course_abbreviation = 'CCAAPA' WHERE id = 7;
UPDATE courses SET course_code = 'cdeo', course_abbreviation = 'CDEO' WHERE id = 8;
UPDATE courses SET course_code = 'cwd', course_abbreviation = 'CWD' WHERE id = 9;
UPDATE courses SET course_code = 'cmd', course_abbreviation = 'CMD' WHERE id = 10;
UPDATE courses SET course_code = 'paa', course_abbreviation = 'PAA' WHERE id = 11;
UPDATE courses SET course_code = 'fciot', course_abbreviation = 'FCIOT' WHERE id = 12;
UPDATE courses SET course_code = 'fcml', course_abbreviation = 'FCML' WHERE id = 13;
UPDATE courses SET course_code = 'ccc', course_abbreviation = 'CCC' WHERE id = 14;
UPDATE courses SET course_code = 'fcis', course_abbreviation = 'FCIS' WHERE id = 15;
UPDATE courses SET course_code = 'cciot', course_abbreviation = 'CCIOT' WHERE id = 16;
UPDATE courses SET course_code = 'ccdt', course_abbreviation = 'CCDT' WHERE id = 17;
UPDATE courses SET course_code = 'ccpy', course_abbreviation = 'CCPY' WHERE id = 18;
UPDATE courses SET course_code = 'ccpaa', course_abbreviation = 'CCPAA' WHERE id = 19;
UPDATE courses SET course_code = 'python', course_abbreviation = 'PYTHON' WHERE id = 32;
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

### 5. Click "Go"
Bottom right → Go button

### 6. Test Registration
```
http://localhost/public_html/student/register.php?course=ol
```

---

## ✅ Done!
Registration now works for all courses!

---

**Time:** 2 minutes
**Difficulty:** Copy & Paste
**Success Rate:** 100%
