# ✅ Check Form Submission - Updated

## What I Just Did

Added debugging to the form submission handler in `student/register.php`.

Now when you submit the form, it will log:
1. Whether course_id field exists
2. The value of course_id
3. ALL form data being submitted

---

## 🧪 Test Now

### Step 1: Open Registration Form
```
http://localhost/public_html/student/register.php?course=ol
```

### Step 2: Open Browser Console
Press **F12** → Click **Console** tab

### Step 3: Fill the Form
- Fill all 3 levels
- Upload 3 files (documents, photo, signature)

### Step 4: Submit and Watch Console
Click "Submit Registration" button

### Step 5: Check Console Output

You should see:
```
=== FORM SUBMISSION STARTED ===
course_id field: [HTMLInputElement]
course_id value: 1
Form data being submitted:
course_id: 1
training_center: [value]
name: [value]
... (all other fields)
documents: [File] filename.pdf
passport_photo: [File] photo.jpg
signature: [File] signature.jpg
```

---

## 🎯 What to Look For

### ✅ GOOD - If you see:
```
course_id value: 1
```
→ Hidden field is working
→ Problem is in PHP validation or processing

### ❌ BAD - If you see:
```
course_id value: 
```
or
```
course_id value: FIELD NOT FOUND
```
→ Hidden field is missing or empty
→ Need to fix course lookup in register.php

---

## 📊 After Checking Console

**Tell me what you see:**

1. Does `course_id value:` show a number (like 1)?
2. Are all form fields present in the log?
3. Are the 3 files showing?
4. Does the form actually submit (page redirects)?
5. Where does it redirect to?

---

## 🔧 If course_id is Empty

If you see `course_id value:` is empty, check:

1. **View Page Source** (Right-click → View Page Source)
2. Search for: `name="course_id"`
3. Check the value attribute

Should look like:
```html
<input type="hidden" name="course_id" value="1">
```

If value is empty:
```html
<input type="hidden" name="course_id" value="">
```

Then the problem is in the course lookup at the top of register.php.

---

## 🎯 Quick Console Test

Before filling the form, type this in console:

```javascript
document.querySelector('input[name="course_id"]').value
```

Press Enter.

**Should show:** `"1"` (or another number)
**If shows:** `""` or `null` → Field is missing/empty

---

## 📞 Next Steps

1. **Submit the form** with console open
2. **Copy the console output** (all of it)
3. **Tell me:**
   - What `course_id value:` shows
   - Where the form redirects to
   - Any error messages

Then I'll know exactly what to fix!

---

**Ready? Open the form and submit with F12 console open!** 🔍
