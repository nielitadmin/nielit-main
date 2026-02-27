# 👁️ Visual Test Guide - What You Should See

## 🎯 Page Overview

```
┌─────────────────────────────────────────────────────────────┐
│                    REGISTRATION PORTAL                       │
│              🎓 Student Registration                         │
│     Complete the 3-level registration process               │
│                    ════════                                  │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ①────────────②────────────③                               │
│  Course &     Contact &    Academic &                        │
│  Personal     Address      Documents                         │
│  (BLUE)       (GRAY)       (GRAY)                           │
│                                                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 📚 Selected Course                                   │   │
│  │ Course: Web Development | Code: WD101 | Fees: ₹5000│   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ╔═══════════════════════════════════════════════════════╗ │
│  ║              LEVEL 1                                  ║ │
│  ║   Course Selection & Personal Information             ║ │
│  ╚═══════════════════════════════════════════════════════╝ │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 📖 Course Selection                                  │   │
│  │                                                       │   │
│  │ Training Center: [NIELIT Bhubaneswar Center ▼]      │   │
│  │ Select Course:   [Web Development (WD101)   ▼]      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 👤 Personal Information                              │   │
│  │                                                       │   │
│  │ Full Name:      [John Doe                    ] ✓    │   │
│  │ Father's Name:  [Robert Doe                  ] ✓    │   │
│  │ Mother's Name:  [Mary Doe                    ] ✓    │   │
│  │ Date of Birth:  [01/01/2000] Age: [26]             │   │
│  │ Gender:         [Male ▼]  Marital: [Single ▼]      │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ╔═══════════════════════════════════════════════════════╗ │
│  ║              LEVEL 2                                  ║ │
│  ║   Contact & Address Information                       ║ │
│  ╚═══════════════════════════════════════════════════════╝ │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 📞 Contact Information                               │   │
│  │                                                       │   │
│  │ Mobile:  [9876543210              ] ✓               │   │
│  │ Email:   [john@example.com        ] ✓               │   │
│  │ Aadhar:  [123456789012            ] ✓               │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 📍 Address Details                                   │   │
│  │                                                       │   │
│  │ Address: [123 Main Street                          ] │   │
│  │ State:   [Odisha ▼]  City: [Bhubaneswar ▼]         │   │
│  │ Pincode: [751024    ] ✓                             │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ╔═══════════════════════════════════════════════════════╗ │
│  ║              LEVEL 3                                  ║ │
│  ║   Academic Details & Document Upload                  ║ │
│  ╚═══════════════════════════════════════════════════════╝ │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 🎓 Academic Details                                  │   │
│  │                                                       │   │
│  │ ┌─────────────────────────────────────────────────┐ │   │
│  │ │ Sl | Exam | Name | Year | Institute | % | ✕    │ │   │
│  │ │ 1  | 10th | HS   | 2016 | CBSE      |85%| ✕    │ │   │
│  │ │ 2  | 12th | HSC  | 2018 | CBSE      |90%| ✕    │ │   │
│  │ └─────────────────────────────────────────────────┘ │   │
│  │ [+ Add More]                                         │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│  ┌─────────────────────────────────────────────────────┐   │
│  │ 📁 Document Upload                                   │   │
│  │                                                       │   │
│  │ Documents: [Choose File]                             │   │
│  │ ┌─────────────────────────────────────────────────┐ │   │
│  │ │ 📄 certificate.pdf                               │ │   │
│  │ │    245.67 KB                          [X Remove] │ │   │
│  │ └─────────────────────────────────────────────────┘ │   │
│  │                                                       │   │
│  │ Photo:     [Choose File]                             │   │
│  │ Signature: [Choose File]                             │   │
│  └─────────────────────────────────────────────────────┘   │
│                                                              │
│              [📤 Submit Registration]                        │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎨 Color Guide

### Progress Indicator States

**Pending (Gray):**
```
②
Gray circle
Gray text
```

**Active (Blue):**
```
②
Blue circle
Blue text
Blue glow
```

**Completed (Green):**
```
②✓
Green circle
Green checkmark
Green text
```

---

## ✓ Validation States

### Valid Field
```
┌─────────────────────────┐
│ john@example.com     ✓ │ ← Green checkmark
└─────────────────────────┘
   Green border
```

### Invalid Field
```
┌─────────────────────────┐
│ invalid              ✗ │ ← Red X
└─────────────────────────┘
   Red border
   ⚠️ Please enter a valid email
```

---

## 📁 File Upload States

### Before Upload
```
┌─────────────────────────┐
│ [Choose File]           │
└─────────────────────────┘
```

### After Upload
```
┌─────────────────────────┐
│ [Choose File]           │
└─────────────────────────┘
┌─────────────────────────┐
│ 📄 certificate.pdf      │
│    245.67 KB            │
│              [X Remove] │
└─────────────────────────┘
```

---

## 🎬 Animation Sequence

### Page Load (0-2 seconds)
```
0.0s: Page appears
0.2s: Title fades in ↓
0.4s: Progress indicator slides in →
0.6s: Level 1 fades in ↑
0.8s: Level 2 fades in ↑
1.0s: Level 3 fades in ↑
```

### Hover Animation
```
Normal State:
┌─────────────────┐
│  Form Section   │
└─────────────────┘

Hover State:
  ┌─────────────────┐
  │  Form Section   │ ← Lifts up 2px
  └─────────────────┘
     Stronger shadow
     Subtle glow
```

### Button Animation
```
Normal:
[Submit Registration]

Hover:
  [Submit Registration] ← Lifts up 3px
     Ripple effect
     Stronger shadow

Click:
[⏳ Submitting...]
  Spinner animation
  Button disabled
```

---

## 📱 Mobile View (< 768px)

```
┌─────────────────┐
│  REGISTRATION   │
│   PORTAL        │
│                 │
│ ①──②──③        │ ← Smaller circles
│                 │
│ ┌─────────────┐ │
│ │ LEVEL 1     │ │
│ └─────────────┘ │
│                 │
│ ┌─────────────┐ │
│ │ Full Name   │ │ ← Single column
│ │ [John Doe]  │ │
│ └─────────────┘ │
│                 │
│ ┌─────────────┐ │
│ │ Father Name │ │
│ │ [Robert]    │ │
│ └─────────────┘ │
│                 │
│ ┌─────────────┐ │
│ │   Submit    │ │ ← Full width
│ └─────────────┘ │
└─────────────────┘
```

---

## 🎯 Progress Line Animation

### 0% Complete
```
①────────②────────③
│
└─ Blue circle, no line
```

### 33% Complete (Level 1 done)
```
①════════②────────③
✓        │
Green    Blue circle
```

### 66% Complete (Level 2 done)
```
①════════②════════③
✓        ✓        │
Green    Green    Blue
```

### 100% Complete (All done)
```
①════════②════════③
✓        ✓        ✓
Green    Green    Green
```

---

## 🖱️ Interactive Elements

### Hover States

**Form Section:**
- Normal: Flat, subtle shadow
- Hover: Lifts 2px, stronger shadow, gradient glow

**Button:**
- Normal: Blue gradient
- Hover: Lifts 3px, ripple effect
- Active: Pressed down

**Input Field:**
- Normal: Gray border
- Focus: Blue border, blue glow
- Valid: Green border, checkmark
- Invalid: Red border, X icon

---

## 🎨 Typography Hierarchy

```
Page Title (2.5rem, bold, gradient)
  ↓
Level Title (1.8rem, bold, blue)
  ↓
Section Title (22px, bold, blue)
  ↓
Label (14px, semi-bold, gray)
  ↓
Input Text (14px, regular, dark)
  ↓
Help Text (13px, regular, light gray)
```

---

## 📏 Spacing Guide

```
┌─────────────────────────────┐
│ Section Header              │ ← 24px padding
│                             │
│ ┌─────────────────────────┐ │
│ │ Input Field             │ │ ← 12px gap
│ └─────────────────────────┘ │
│                             │
│ ┌─────────────────────────┐ │
│ │ Input Field             │ │ ← 12px gap
│ └─────────────────────────┘ │
│                             │
└─────────────────────────────┘
       ↓ 28px margin
┌─────────────────────────────┐
│ Next Section                │
└─────────────────────────────┘
```

---

## ✅ Visual Checklist

When testing, verify you see:

- [ ] Gradient text on page title
- [ ] 3-step progress indicator
- [ ] Blue active step, gray pending steps
- [ ] Green completed steps with checkmarks
- [ ] Smooth progress line animation
- [ ] Green checkmarks on valid fields
- [ ] Red X on invalid fields
- [ ] File preview with icon, name, size
- [ ] Hover lift effect on sections
- [ ] Button ripple effect
- [ ] Smooth 60 FPS animations
- [ ] Mobile single-column layout
- [ ] Full-width buttons on mobile
- [ ] Smaller progress circles on mobile

---

## 🎉 Perfect State

When everything is working perfectly:

```
┌─────────────────────────────────────────┐
│         REGISTRATION PORTAL              │
│      🎓 Student Registration             │
│                                          │
│  ①════════②════════③                    │
│  ✓        ✓        ✓                    │
│  Course   Contact  Academic              │
│  (GREEN)  (GREEN)  (GREEN)              │
│                                          │
│  Progress: 100% Complete                 │
│                                          │
│  All fields filled ✓                     │
│  All validations passed ✓                │
│  All documents uploaded ✓                │
│                                          │
│  [📤 Submit Registration]                │
│                                          │
└─────────────────────────────────────────┘
```

---

**Status:** Ready for Visual Testing  
**Version:** 2.0  
**Date:** February 11, 2026

