# 🚀 Quick Test Card - Modern Registration

## 🎯 5-Minute Test

### 1. Open Page
```
http://localhost/student/register.php
```

### 2. Check Visuals
✅ Progress indicator at top (3 steps)  
✅ Step 1 is blue, Steps 2&3 are gray  
✅ Page title with gradient  
✅ Smooth animations on load

### 3. Test Progress
1. Fill "Full Name" → watch progress update
2. Fill "Father's Name" → watch progress update
3. Complete Level 1 → Step 1 turns GREEN ✓

### 4. Test Validation
```
Email: "invalid"       → RED X
Email: "user@test.com" → GREEN ✓

Mobile: "123"          → RED X
Mobile: "9876543210"   → GREEN ✓
```

### 5. Test File Upload
1. Upload PDF → see preview with name & size
2. Click X → preview disappears

### 6. Test Submit
1. Fill all fields
2. Upload all documents
3. Click submit → see "Submitting..." with spinner

---

## ✅ Success = All 6 Tests Pass!

---

## 🎨 What You Should See

### Progress Indicator
```
①────────②────────③
Blue     Gray     Gray
(Active) (Pending)(Pending)

After Level 1 complete:
①────────②────────③
Green✓   Blue     Gray
(Done)   (Active) (Pending)
```

### Validation States
```
✓ Valid Field   (Green checkmark)
✗ Invalid Field (Red X)
```

### File Preview
```
┌─────────────────────────┐
│ 📄 certificate.pdf      │
│    245.67 KB            │
│              [X Remove] │
└─────────────────────────┘
```

---

## 🐛 Quick Troubleshooting

**Progress not updating?**
→ Check browser console (F12)

**Validation not working?**
→ Use valid test data first

**File preview not showing?**
→ Check file size < 5MB

**Animations choppy?**
→ Close other tabs, use Chrome

---

## 📱 Mobile Test

1. Press F12
2. Click device toolbar icon
3. Select "iPhone 12 Pro"
4. Test all features

**Expected:** Single column, full-width buttons

---

## 🎯 Test Data

### Valid Test Data
```
Name: John Doe
Email: john@example.com
Mobile: 9876543210
Aadhar: 123456789012
Pincode: 751024
DOB: 01/01/2000
```

### Invalid Test Data
```
Email: invalid
Mobile: 123
Aadhar: 123456
Pincode: 123
```

---

## ✅ Pass Criteria

1. ✅ Progress updates in real-time
2. ✅ Validation shows green ✓ or red ✗
3. ✅ File preview appears
4. ✅ Animations are smooth
5. ✅ Mobile responsive
6. ✅ Form submits successfully

---

## 📊 Quick Metrics

**Load Time:** < 2 seconds  
**Frame Rate:** 60 FPS  
**Mobile Width:** 375px  
**Desktop Width:** 1920px

---

## 🎉 Result

**Status:** [ ] PASS  [ ] FAIL  
**Time:** _____ minutes  
**Issues:** _____

---

**Version:** 2.0  
**Date:** February 11, 2026
