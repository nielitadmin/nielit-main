# Course Form Layout Reorganization

**Date:** June 2, 2026  
**Status:** ✅ Complete  
**User Requirement:** Category and Sub-Category fields appear FIRST, before other fields

---

## Overview

Reorganized the course form field layout in both **Add Course** (dashboard.php) and **Edit Course** (edit_course.php) to match user's preferred workflow: **Category/Sub-Category first**, then course name and codes, then details.

---

## Final Layout Structure

### Section 1: Basic Course Information

**Order of Fields:**
1. **Category** (dropdown)
2. **Sub-Category** (dropdown)
3. **Course Name** (text input)
4. **Course Code** (auto-generated)
5. **Student ID Code** (auto-generated)

**Grid Layout:**
```
Row 1: [Category (1fr)] [Sub-Category (1fr)]
Row 2: [Course Name (2fr)] [Course Code (1fr)] [Student ID Code (1fr)]
```

### Section 2: Course Details

- Eligibility
- Duration  
- Training Fees
- Course Coordinator
- Training Centre
- Start/End Dates
- etc.

---

## User's Requirement

> "i want Category * ├─ Sub-Category in the first part after that other thinks"

**Translation:**
- Category and Sub-Category must be the FIRST fields users see
- Other fields (Course Name, Code, etc.) come after
- This creates a category-first workflow

---

## Why This Order Makes Sense

### 1. **Category-First Thinking**
- Administrators first decide "What TYPE of course is this?"
- Is it: Skill Based? Degree/Diploma? Digital Literacy?
- NSQF or Non-NSQF?

### 2. **Logical Decision Flow**
```
Step 1: Classify the course
   ├─ Category: "Skill Based (Short Term) 90-500 hrs"
   └─ Sub-Category: "NSQF Course"

Step 2: Define the course
   ├─ Course Name: "Post Graduate Programme in AI"
   ├─ Course Code: "PPI-2026"
   └─ Student ID Code: "PPI"

Step 3: Add course details
   ├─ Eligibility, Duration, Fees
   └─ Coordinator, Dates, etc.
```

### 3. **Mental Model Alignment**
- Users think in categories before specifics
- "What bucket does this go in?" → Then "What exactly is it?"

---

## Visual Representation

### ✅ Current Layout (User's Preference)

```
┌─────────────────────────────────────────────────┐
│  BASIC COURSE INFORMATION                       │
├─────────────────────────────────────────────────┤
│                                                 │
│  [Category ▼]           [Sub-Category ▼]        │  ← FIRST
│                                                 │
│  [Course Name............................]       │
│  [Course Code]  [Student ID Code]               │
│                                                 │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│  COURSE DETAILS                                 │
├─────────────────────────────────────────────────┤
│  [Eligibility]     [Duration]                   │
│  [Training Fees]   [Coordinator]                │
│  etc...                                         │
└─────────────────────────────────────────────────┘
```

---

## Changes Made

### 1. `admin/edit_course.php`

**Before:**
```php
// Course Name, Course Code, Student ID Code (all together)
<div style="grid-template-columns: 2fr 1fr 1fr">
```

**After:**
```php
// Category and Sub-Category FIRST
<div style="grid-template-columns: 1fr 1fr">
    <div>Category</div>
    <div>Sub-Category</div>
</div>

// Then Course Name, Code, Student ID
<div style="grid-template-columns: 2fr 1fr 1fr">
    <div>Course Name</div>
    <div>Course Code</div>
    <div>Student ID Code</div>
</div>
```

### 2. `admin/dashboard.php` (Add Course Modal)

**Before:**
```html
<div class="form-grid form-grid-2">
    <div>Course Name</div>
    <div>Course Code</div>
</div>
<!-- Category/Sub-Category in "Course Details" section -->
```

**After:**
```html
<!-- Category/Sub-Category FIRST in "Basic Information" -->
<div class="form-grid form-grid-2">
    <div>Category</div>
    <div>Sub-Category</div>
</div>

<!-- Then Course Name, Code, Student ID -->
<div class="form-grid form-grid-3">
    <div>Course Name</div>
    <div>Course Code</div>
    <div>Student ID Code</div>
</div>
```

---

## Field Purposes

| Field | Purpose | Why in This Position |
|-------|---------|---------------------|
| **Category** | Course classification by type/duration | Users need to classify BEFORE naming |
| **Sub-Category** | NSQF vs Non-NSQF designation | Part of classification decision |
| **Course Name** | Official course title | Named AFTER classification is clear |
| **Course Code** | Unique identifier (auto-generated) | Derived from course name |
| **Student ID Code** | Student ID abbreviation | Derived from course name/type |

---

## Benefits of This Layout

✅ **Category-First Workflow**
- Matches how administrators think
- "What type is it?" comes before "What is it called?"

✅ **Better Mental Model**
- Classification → Identification → Details
- Follows natural decision-making process

✅ **Consistency**
- Both Add and Edit forms have identical order
- Reduces training time and errors

✅ **Clear Visual Hierarchy**
- Two dropdowns at top (category selection)
- Three text fields below (course identity)
- Details section below that

---

## Testing Checklist

- [x] Category and Sub-Category appear first in edit_course.php
- [x] Category and Sub-Category appear first in dashboard.php Add Course modal
- [x] Course Name, Code, Student ID appear in second row
- [x] Form submission works correctly
- [x] Both forms maintain consistent order
- [x] No PHP or JavaScript errors

---

## Files Modified

### 1. `admin/edit_course.php`
- Moved Category/Sub-Category to first position
- Followed by Course Name/Code/Student ID row

### 2. `admin/dashboard.php`
- Moved Category/Sub-Category to top of "Basic Information" section
- Followed by Course Name/Code/Student ID row

---

## Notes

- No database changes required
- No functional changes to form submission
- All validation and auto-generation logic remains unchanged
- Forms remain fully responsive
- Help text and tooltips are preserved

---

**Status:** ✅ Reorganization Complete per User Request  
**Forms Updated:** 2 (dashboard.php, edit_course.php)  
**Layout Order:** Category/Sub-Category → Name/Code/ID → Details  
**User Satisfaction:** ✅ Matches requested workflow

