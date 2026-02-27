# 🎯 Test REAL Registration Form

## Important Discovery

The test form works perfectly! ✅
- course_id = 1
- All validations pass
- Would save to database

## Now Test the REAL Form

### Step 1: Open REAL Registration Form
```
http://localhost/public_html/student/register.php?course=sas
```

**Note:** Using `?course=sas` (not `?course=ol`) since you mentioned "SAS"

### Step 2: Open Browser Console
Press **F12** → **Console** tab

### Step 3: Check course_id BEFORE Filling Form

Type in console:
```javascript
document.querySelector('input[name="course_id"]').value
```

**Expected:** Should show a number (like `54` for SAS course)
**If empty:** Course lookup failed

### Step 4: Fill ALL 3 Levels

**Level 1: Course & Personal**
- Name
- Father's Name
- Mother's Name
- Date of Birth
- Mobile
- Aadhar
- Gender
- Religion
- Marital Status
- Category
- Position
- Nationality

**Level 2: Contact & Address**
- Email
- State
- City
- Pincode
- Address
- College Name

**Level 3: Academic & Documents**
- Educational details (exam passed, etc.)
- Upload Documents (PDF)
- Upload Passport Photo
- Upload Signature
- Payment Receipt (optional)
- UTR Number (optional)

### Step 5: Submit and Watch Console

Click "Submit Registration"

Console should show:
```
=== FORM SUBMISSION STARTED ===
course_id field: [HTMLInputElement]
course_id value: 54
Form data being submitted:
course_id: 54
name: [your name]
... (all fields)
documents: [File] filename.pdf
passport_photo: [File] photo.jpg
signature: [File] signature.jpg
```

### Step 6: Check Where It Redirects

After submission, note:
- Does it redirect to `courses.php`?
- Does it redirect to `registration_success.php`?
- Does it show any error message?

---

## 🔍 What to Check

### If course_id is Empty in Console:
The course lookup is failing. Check:
1. Is course ID 54 in database?
2. Does it have `course_code = 'sas'`?
3. Does it have `course_abbreviation = 'SAS'`?

### If Form Redirects to courses.php:
Check console for what data was sent. One of these is happening:
1. course_id is empty/0
2. Required field is missing
3. Validation is failing

### If Form Doesn't Submit at All:
JavaScript validation is failing. Check console for:
- Mobile number validation
- Aadhar validation
- Email validation
- File upload validation

---

## 🎯 Quick Database Check

Check if SAS course exists:

1. Open phpMyAdmin
2. Run this SQL:
```sql
SELECT id, course_name, course_code, course_abbreviation 
FROM courses 
WHERE course_code = 'sas' OR course_abbreviation = 'SAS';
```

**Expected Result:**
```
id: 54
course_name: sas
course_code: sas
course_abbreviation: SAS
```

---

## 📊 Comparison

### Test Form (Works ✅):
- Minimal fields
- course_id = 1
- No file uploads
- Simple validation

### Real Form (Testing):
- ALL fields required
- course_id = 54 (for SAS)
- 3 file uploads required
- Complex validation

---

## 🔧 If Real Form Fails

**Tell me:**
1. What does `document.querySelector('input[name="course_id"]').value` show?
2. What does console show when you submit?
3. Where does it redirect?
4. Any error messages?

Then I'll fix the exact issue!

---

**Ready? Test the REAL form with ?course=sas** 🚀
