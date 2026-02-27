# PWD Field - Quick Start Guide

## 🚀 Quick Deployment (3 Steps)

### Step 1: Run Database Migration (2 minutes)
Open in browser: `http://localhost/your-project/add_pwd_status_column.php`

You should see: ✅ SUCCESS! Column 'pwd_status' has been added

### Step 2: Test Registration (1 minute)
1. Go to any course registration link
2. Fill the form
3. Look for "Persons with Disabilities" field in Level 2
4. Select Yes or No
5. Submit registration

### Step 3: Verify in Admin (1 minute)
1. Login to admin panel
2. View any student
3. Check "PWD Status" row in Personal Information
4. Should show badge with wheelchair icon if Yes

## ✅ That's It!

The PWD field is now active across:
- ✅ Registration forms
- ✅ Admin views
- ✅ Edit forms
- ✅ PDF downloads
- ✅ Admission orders

## 📍 Where to Find PWD Field

### For Students (Registration)
**Location:** Level 2 → Additional Details section  
**Position:** After "Category", before "Position/Occupation"  
**Field Type:** Dropdown (Yes/No)  
**Default:** No  
**Required:** No (optional)

### For Admins (View)
**Location:** View Student Documents → Personal Information table  
**Display:** Badge with wheelchair icon  
**Colors:** Blue (Yes), Gray (No)

### For Admins (Edit)
**Location:** Edit Student → Personal Information section  
**Field Type:** Dropdown (Yes/No)  
**Pre-selected:** Current value

### In PDF
**Location:** Personal Information section  
**Label:** PWD STATUS / दिव्यांग स्थिति  
**Value:** Yes / हाँ OR No / नहीं

### In Admission Orders
**Location:** After category summary table  
**Display:** Gradient box with PWD counts  
**Shows:** Male, Female, Total PWD  
**Note:** Only appears if PWD students exist

## 🎨 Visual Guide

```
REGISTRATION FORM (Level 2)
┌─────────────────────────────────────┐
│ Religion        [Select ▼]          │
│ Category        [Select ▼]          │
│ PWD Status      [No ▼]     ← NEW!  │
│ Position        [Select ▼]          │
└─────────────────────────────────────┘

ADMIN VIEW
┌─────────────────────────────────────┐
│ Gender    │ Male                    │
│ Category  │ General                 │
├───────────┼─────────────────────────┤
│ PWD Status│ 🦽 Yes  ← NEW!         │
│ Position  │ Student                 │
└─────────────────────────────────────┘

ADMISSION ORDER
┌─────────────────────────────────────┐
│ 🦽 PWD Summary:          ← NEW!    │
│ Male: 2 | Female: 1 | Total: 3     │
│ Note: Also counted in categories    │
└─────────────────────────────────────┘
```

## 🔧 Troubleshooting

### Migration Script Shows "Column already exists"
✅ **This is OK!** It means the column was already added. No action needed.

### PWD field not showing in registration form
❌ **Check:** Did you update `student/register.php`?  
✅ **Fix:** Re-upload the file or clear browser cache

### PWD status shows as blank in admin view
❌ **Check:** Did you run the migration script?  
✅ **Fix:** Run `add_pwd_status_column.php` in browser

### PDF shows boxes instead of Hindi text
❌ **Check:** Is FreeSans font being used?  
✅ **Fix:** Already fixed in code - uses FreeSans for Hindi

### Admission order doesn't show PWD summary
✅ **This is normal!** PWD summary only shows if there are PWD students in the batch

## 📊 Database Info

**Table:** `students`  
**Column:** `pwd_status`  
**Type:** VARCHAR(3)  
**Default:** 'No'  
**Values:** 'Yes' or 'No'  
**Position:** After `category` column

```sql
-- Check if column exists
SHOW COLUMNS FROM students LIKE 'pwd_status';

-- View PWD statistics
SELECT pwd_status, COUNT(*) as count 
FROM students 
GROUP BY pwd_status;

-- Find all PWD students
SELECT student_id, name, pwd_status 
FROM students 
WHERE pwd_status = 'Yes';
```

## 🎯 Key Points

1. **Optional Field** - Students can choose not to disclose
2. **Independent** - PWD status is separate from Category field
3. **Backward Compatible** - Old records work fine (NULL = No)
4. **Privacy Protected** - Only admins can view PWD status
5. **Bilingual** - PDF includes Hindi translations

## 📝 Quick Test Checklist

- [ ] Run migration script
- [ ] Register student with PWD: Yes
- [ ] View student in admin panel
- [ ] Edit student PWD status
- [ ] Download PDF form
- [ ] Generate admission order
- [ ] Delete migration script

## 🆘 Need Help?

**Full Documentation:** See `PWD_FIELD_IMPLEMENTATION_COMPLETE.md`  
**Spec Files:** `.kiro/specs/pwd-field-addition/`  
**Migration Script:** `add_pwd_status_column.php`

## ⚡ Pro Tips

1. **Delete migration script** after successful deployment
2. **Test with both Yes and No** values
3. **Check old student records** to verify backward compatibility
4. **Generate admission order** to see PWD summary
5. **Download PDF** to verify bilingual labels

---

**Status:** ✅ Ready for Production  
**Time to Deploy:** ~5 minutes  
**Difficulty:** Easy  
**Risk:** Low (backward compatible)
