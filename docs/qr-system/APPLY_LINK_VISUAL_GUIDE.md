# 📸 Apply Link & QR System - Visual Guide

## What You'll See in the Admin Panel

---

## 🎯 Add New Course Modal

### Registration Link Settings Section:

```
┌──────────────────────────────────────────────────────────┐
│  🔗 Registration Link Settings                           │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  Apply Link:                                             │
│  ┌────────────────────────────────┬──────────────────┐  │
│  │ Will be auto-generated         │ 🪄 Generate Link │  │
│  └────────────────────────────────┴──────────────────┘  │
│  Click "Generate Link" to create registration URL       │
│                                                          │
│  Publish Status:                                         │
│  ┌─────────────────────────────────────────────────┐    │
│  │ [○────] Unpublished                             │    │
│  └─────────────────────────────────────────────────┘    │
│  Toggle to show/hide on website                         │
│                                                          │
│  ℹ️ Preview:                                             │
│  ┌─────────────────────────────────────────────────┐    │
│  │ Enter course name and click "Generate Link"     │    │
│  └─────────────────────────────────────────────────┘    │
│                                                          │
│  ⚠️ Note: QR code will be generated automatically       │
│     when you save the course with a registration link.  │
└──────────────────────────────────────────────────────────┘
```

---

## 🔄 Step-by-Step Visual Flow

### Step 1: Fill Course Details

```
Course Name: [Web Development Bootcamp        ]
Course Code: [WDB25]
Type:        [Bootcamp ▼]
...
```

### Step 2: Click "Generate Link"

```
Apply Link: [Will be auto-generated] [🪄 Generate Link] ← CLICK HERE
                                      ↓
                                   CLICKED!
                                      ↓
Apply Link: [http://localhost/public_html/student/register.php?course=Web+Development+Bootcamp]
```

### Step 3: Toggle Publish Status

**Before (Unpublished):**
```
Publish Status:
[○────] Unpublished
```

**After (Published):**
```
Publish Status:
[────●] Published ✓
```

### Step 4: Preview Shows Link

```
ℹ️ Preview:
┌─────────────────────────────────────────────────────────┐
│ http://localhost/public_html/student/register.php?     │
│ course=Web+Development+Bootcamp                         │
└─────────────────────────────────────────────────────────┘
```

### Step 5: Save Course

```
[Cancel]  [Add Course] ← CLICK TO SAVE
            ↓
         SAVING...
            ↓
    ✅ Course added successfully!
    ✅ Registration link and QR code generated.
```

---

## 📊 Course Table View

### After Saving Course:

```
┌────┬──────────────────────┬──────┬─────────┬────────────────┬──────────┐
│ ID │ Course Name          │ Code │ Type    │ Reg. Link      │ QR Code  │
├────┼──────────────────────┼──────┼─────────┼────────────────┼──────────┤
│ 5  │ Web Dev Bootcamp     │WDB25 │Bootcamp │ [link] 📋 🔗   │ 🟢 🔵    │
│    │                      │      │         │                │ View Down│
└────┴──────────────────────┴──────┴─────────┴────────────────┴──────────┘
```

**Buttons Explained:**
- 📋 = Copy link to clipboard
- 🔗 = Open link in new tab
- 🟢 = View QR code in modal
- 🔵 = Download QR code PNG

---

## 🎨 Publish Status Visual States

### State 1: Unpublished (Default)

```
┌─────────────────────────────────┐
│ Publish Status:                 │
│ [○────] Unpublished             │
│         ↑                       │
│      Toggle OFF                 │
│      (Gray color)               │
└─────────────────────────────────┘

Result: Link NOT visible on public website
```

### State 2: Published

```
┌─────────────────────────────────┐
│ Publish Status:                 │
│ [────●] Published ✓             │
│         ↑                       │
│      Toggle ON                  │
│      (Green color, bold)        │
└─────────────────────────────────┘

Result: Link VISIBLE on public website
```

---

## 🔍 QR Code Modal View

### When You Click "View QR" Button:

```
┌──────────────────────────────────────────┐
│  QR Code - Web Development Bootcamp  [X] │
├──────────────────────────────────────────┤
│                                          │
│            ┌─────────────┐               │
│            │█████████████│               │
│            │█████████████│               │
│            │█████████████│               │
│            │█████████████│               │
│            │█████████████│               │
│            └─────────────┘               │
│                                          │
│     Scan this QR code to register        │
│                                          │
│  [📥 Download QR Code]  [🔄 Regenerate]  │
│                                          │
└──────────────────────────────────────────┘
```

---

## 📱 Mobile Phone View (Student Side)

### When Student Scans QR Code:

```
┌─────────────────────┐
│  📱 iPhone/Android  │
├─────────────────────┤
│                     │
│  1. Open Camera     │
│  2. Point at QR     │
│  3. Tap notification│
│                     │
│  ↓                  │
│                     │
│  🌐 Browser Opens   │
│                     │
│  ┌───────────────┐  │
│  │ NIELIT        │  │
│  │ Registration  │  │
│  │               │  │
│  │ Course:       │  │
│  │ Web Dev       │  │
│  │ Bootcamp ✓    │  │
│  │               │  │
│  │ [Fill Form]   │  │
│  └───────────────┘  │
│                     │
└─────────────────────┘
```

---

## 🎯 Complete Workflow Diagram

```
ADMIN SIDE:
┌─────────────────┐
│ 1. Add Course   │
│    Fill details │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 2. Generate     │
│    Apply Link   │ ← Click button
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 3. Toggle       │
│    Publish ON   │ ← Make visible
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 4. Save Course  │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 5. QR Generated │ ← Automatic!
│    Automatically│
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 6. Download QR  │
│    & Share      │
└─────────────────┘

STUDENT SIDE:
┌─────────────────┐
│ 1. See QR Code  │
│    on poster    │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 2. Scan with    │
│    Phone Camera │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 3. Link Opens   │
│    in Browser   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 4. Fill Form    │
│    & Register   │
└────────┬────────┘
         │
         ↓
┌─────────────────┐
│ 5. Success!     │
│    Registered   │
└─────────────────┘
```

---

## 🎨 Color Coding

### Button Colors:

| Color | Button | Meaning |
|-------|--------|---------|
| 🟢 Green | Generate Link | Create new link |
| 🟢 Green | View QR | See QR code |
| 🔵 Blue | Download | Get PNG file |
| 🟡 Yellow | Generate QR | Create QR (manual) |
| 🟠 Orange | Regenerate | Replace QR |
| ⚪ Gray | Copy | Copy to clipboard |

### Status Colors:

| Color | Status | Meaning |
|-------|--------|---------|
| 🟢 Green | Published | Visible on website |
| ⚪ Gray | Unpublished | Hidden from website |
| 🟢 Green | Active | Course is active |
| 🔴 Red | Inactive | Course is inactive |

---

## 📋 Quick Reference Card

### For Adding New Course:

```
┌────────────────────────────────────────┐
│  QUICK STEPS:                          │
├────────────────────────────────────────┤
│  1. Fill course details                │
│  2. Click "Generate Link" button       │
│  3. Toggle "Publish" ON                │
│  4. Click "Add Course"                 │
│  5. QR code creates automatically!     │
└────────────────────────────────────────┘
```

### For Editing Course:

```
┌────────────────────────────────────────┐
│  QUICK STEPS:                          │
├────────────────────────────────────────┤
│  1. Click Edit button                  │
│  2. Update details if needed           │
│  3. Click "Generate Link" if changed   │
│  4. Toggle publish status              │
│  5. Click "Update Course"              │
│  6. QR regenerates if link changed!    │
└────────────────────────────────────────┘
```

---

## 🎯 What Each Button Does

### In Add/Edit Modal:

```
[🪄 Generate Link]
↓
Creates registration URL automatically
Based on course name or ID
Shows in preview box

[Toggle Switch]
↓
ON  = Published (shows on website)
OFF = Unpublished (hidden)

[Add Course] / [Update Course]
↓
Saves course to database
Generates QR code automatically
Shows success message
```

### In Course Table:

```
[📋 Copy]
↓
Copies registration link
To clipboard
Shows checkmark feedback

[🔗 Open]
↓
Opens registration link
In new browser tab
For testing

[🟢 View]
↓
Opens QR code modal
Shows large QR image
Download & regenerate options

[🔵 Download]
↓
Downloads QR code PNG
Instant download
No modal needed
```

---

## 💡 Pro Tips

### Tip 1: Draft Mode
```
Create course → Don't publish → Test internally → Publish when ready
```

### Tip 2: Bulk Creation
```
Add multiple courses → Generate links → Keep unpublished → Publish all at once
```

### Tip 3: Link Testing
```
Generate link → Copy → Test in browser → Verify form works → Then publish
```

### Tip 4: QR Testing
```
Generate → Download → Scan with phone → Test registration → Then distribute
```

---

## ✅ Success Indicators

### You'll Know It Worked When:

**After Adding Course:**
```
✅ Success message appears
✅ Course appears in table
✅ Registration link is filled
✅ QR code buttons appear (View/Download)
✅ Publish status shows correctly
```

**After Scanning QR:**
```
✅ Phone shows link notification
✅ Browser opens registration page
✅ Course is pre-selected
✅ Form is ready to fill
✅ Student can register
```

---

## 🎉 You're Ready!

Now you know:
- ✅ How to generate apply links
- ✅ How to publish/unpublish courses
- ✅ How QR codes generate automatically
- ✅ What each button does
- ✅ How students will use it

**Start creating courses with automatic QR codes now!**

---

**Visual Guide Version:** 1.0.0
**Last Updated:** February 11, 2026
**For:** NIELIT Bhubaneswar Admin Panel
