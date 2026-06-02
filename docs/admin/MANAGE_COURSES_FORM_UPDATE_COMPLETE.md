# Manage Courses Form Update - Complete

## Overview
Updated the Add New Course and Edit Course forms in `admin/manage_courses.php` to match the structure and categories from `admin/edit_course.php`.

## Changes Made

### 1. Field Sequence Reorganization ✅

**New Order:**
1. **Category** (5 main categories)
2. **Sub-Category** (NSQF/NON-NSQF + 5 special programs)
3. **Eligibility** (required field)
4. **Duration** (required field)
5. **Training Fees** (required field)
6. **NSQF Template Selection** (shown only for NSQF courses)
7. **Course Name** (required field)
8. **Course Code** (auto-generated)
9. **Student ID Code** (auto-generated)
10. **Training Centre** fields
11. **Description**
12. **Registration Link Settings**

### 2. Category Dropdown Updates ✅

**Removed Old Options:**
- ❌ "Regular"
- ❌ "Bootcamp"
- ❌ "Workshop" (moved to Sub-Category)

**Current 5 Main Categories:**
1. Degree / Diploma Courses / PG
2. Skill Based (Long Term) Courses > 500 hrs
3. Skill Based (Short Term) Courses >90 hrs to <=500 hrs
4. Short Term Courses / Digital Competency Courses <= 90 hours
5. NIELIT HQ's Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)

### 3. Sub-Category Dropdown Updates ✅

**Added New Options:**
- ✅ Internship Program
- ✅ Awareness Program
- ✅ FDP Program
- ✅ Workshop
- ✅ GOVT/CORPORATE Training

**Complete Sub-Category List:**
1. NSQF Course
2. NON-NSQF Course
3. Internship Program
4. Awareness Program
5. FDP Program
6. Workshop
7. GOVT/CORPORATE Training

### 4. JavaScript Handler Functions ✅

Added new functions to handle category and sub-category changes:

#### Add Course Modal Handlers:
- `handleAddCategoryChange()` - Sets appropriate placeholders based on category
- `handleAddSubCategoryChange()` - Shows/hides NSQF template selection
- `handleAddTemplateSelection()` - Populates course name and eligibility from template

#### Edit Course Modal Handlers:
- `handleEditCategoryChange()` - Sets appropriate placeholders based on category
- `handleEditSubCategoryChange()` - Shows/hides NSQF template selection
- `handleEditTemplateSelection()` - Populates course name and eligibility from template

### 5. Smart Placeholder System ✅

Placeholders change dynamically based on selected category:

| Category | Eligibility Placeholder | Duration Placeholder |
|----------|------------------------|---------------------|
| Degree / Diploma / PG | 10+2 or equivalent | 3 Years |
| Skill Based (Long Term) | 10th Pass or equivalent | 600 Hours |
| Skill Based (Short Term) | 8th Pass or equivalent | 120 Hours |
| Short Term / Digital Competency | Basic Computer Knowledge | 60 Hours |
| NIELIT HQ Digital Literacy | 8th Pass | 80 Hours |

Placeholders for special programs:

| Sub-Category | Eligibility Placeholder |
|--------------|------------------------|
| Internship Program | Currently enrolled in relevant course |
| Awareness Program | Open to all |
| FDP Program | Faculty members from recognized institutions |
| Workshop | Basic knowledge of the subject |
| GOVT/CORPORATE Training | As per organization requirements |

### 6. NSQF Template Integration ✅

- Template selection group shows only when "NSQF Course" is selected
- Course name input hides when template selection is shown
- Eligibility field becomes readonly for NSQF courses
- Template selection auto-populates course name and eligibility
- Auto-generates course code after template selection

### 7. Form Validation ✅

**Required Fields:**
- Category *
- Sub-Category *
- Eligibility *
- Duration *
- Training Fees *
- Course Name *
- Course Code *
- Student ID Code *
- Training Centre *

## Files Updated

### Main File
- `c:\xampp\htdocs\public_html\admin\manage_courses.php`

### Changes Summary
1. **Add Course Modal** - Completely restructured with new field order
2. **Edit Course Modal** - Completely restructured with new field order
3. **JavaScript Functions** - Added 6 new handler functions
4. **Category Dropdowns** - Updated to match edit_course.php exactly
5. **Sub-Category Dropdowns** - Added 5 new special program options

## How It Works

### Add New Course Flow

1. **User selects Category**
   - Placeholders update automatically
   - Duration and eligibility hints change

2. **User selects Sub-Category**
   - If "NSQF Course": Template selection appears
   - If special program: Appropriate placeholders show
   - If "NON-NSQF Course": Standard course name input

3. **User enters/selects Course Name**
   - For NSQF: Select from template dropdown
   - For others: Type course name manually
   - Course code auto-generates as user types

4. **System auto-generates codes**
   - Course Code: e.g., "PP-2026"
   - Student ID Code: e.g., "PP"
   - Handles duplicates automatically (PP01-2026, PP02-2026, etc.)

5. **User completes remaining fields**
   - Training Centre
   - Description
   - Registration Link (optional)

### Edit Course Flow

Same as Add Course, but:
- Fields pre-populated with existing data
- Course code can be edited (not readonly)
- Template selection works same way
- All validations apply

## Testing Checklist

### ✅ Test Add Course Form

1. **Test Category Selection**
   - [ ] Select each of 5 main categories
   - [ ] Verify placeholders change appropriately
   - [ ] Check duration placeholder updates

2. **Test Sub-Category Selection**
   - [ ] Select "NSQF Course" → Template selection should appear
   - [ ] Select "NON-NSQF Course" → Course name input should appear
   - [ ] Select "Internship Program" → Check placeholder
   - [ ] Select "Awareness Program" → Check placeholder
   - [ ] Select "FDP Program" → Check placeholder
   - [ ] Select "Workshop" → Check placeholder
   - [ ] Select "GOVT/CORPORATE Training" → Check placeholder

3. **Test NSQF Template**
   - [ ] Select "NSQF Course" sub-category
   - [ ] Template dropdown should appear
   - [ ] Select a template
   - [ ] Course name should auto-populate
   - [ ] Eligibility should auto-populate
   - [ ] Course code should auto-generate

4. **Test Auto-Generation**
   - [ ] Type course name "Python Programming"
   - [ ] Course code should generate: PP-2026
   - [ ] Student ID code should generate: PP
   - [ ] Create another "Python Programming"
   - [ ] Should generate: PP01-2026 and PP01

5. **Test Form Submission**
   - [ ] Fill all required fields
   - [ ] Submit form
   - [ ] Verify course created successfully
   - [ ] Check all fields saved correctly

### ✅ Test Edit Course Form

1. **Test Field Population**
   - [ ] Click edit on existing course
   - [ ] All fields should populate correctly
   - [ ] Category should be selected
   - [ ] Sub-category should be selected

2. **Test Category Change**
   - [ ] Change category
   - [ ] Verify placeholders update
   - [ ] Change sub-category
   - [ ] Verify behavior matches add form

3. **Test NSQF Template in Edit**
   - [ ] Change sub-category to "NSQF Course"
   - [ ] Template selection should appear
   - [ ] Select template
   - [ ] Fields should update

4. **Test Form Update**
   - [ ] Modify fields
   - [ ] Submit form
   - [ ] Verify changes saved correctly

## Benefits

✅ **Consistent Structure**: Add and Edit forms match edit_course.php exactly  
✅ **Better Organization**: Logical field sequence (Category → Sub-Category → Details → Codes)  
✅ **Smart Placeholders**: Context-aware hints based on selections  
✅ **NSQF Integration**: Seamless template selection for NSQF courses  
✅ **All Special Programs**: Support for 5 special program types  
✅ **Auto-Generation**: Course codes still auto-generate with smart duplicate handling  
✅ **User-Friendly**: Clear labels, helpful hints, and logical flow  

## Related Files

- `admin/manage_courses.php` - Updated add/edit forms
- `admin/edit_course.php` - Reference for structure
- `admin/check_course_code.php` - Backend API for duplicate checking
- `docs/admin/COURSE_MANAGEMENT_UPDATES_SUMMARY.md` - Overall summary

## Status

✅ **COMPLETE** - Add and Edit forms updated to match edit_course.php structure

---
**Updated**: May 26, 2026  
**Task**: Manage Courses Form Update  
**Status**: Complete
