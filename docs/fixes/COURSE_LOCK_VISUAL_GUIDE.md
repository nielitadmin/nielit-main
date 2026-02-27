# Course Lock Feature - Visual Guide 🎨

## Quick Visual Reference

---

## 🔒 LOCKED MODE (With course_id in URL)

### URL Example:
```
http://localhost/public_html/student/register.php?course_id=5
```

### Visual Appearance:

```
┌─────────────────────────────────────────────────────────────┐
│  📚 Course Selection                                         │
│  Your selected course (locked)                              │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Training Center *                                           │
│  ┌────────────────────────────────────────────────────┐    │
│  │ NIELIT Bhubaneswar Center                          │    │
│  │ (Light blue background, not-allowed cursor)        │    │
│  └────────────────────────────────────────────────────┘    │
│  🔒 Locked by registration link                             │
│                                                              │
│  Select Course *                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │ Python Programming (PY-2026)                       │    │
│  │ (Light blue background, not-allowed cursor)        │    │
│  └────────────────────────────────────────────────────┘    │
│  🔒 Locked by registration link                             │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### Course Info Card (Displayed Above):

```
┌─────────────────────────────────────────────────────────────┐
│  🎓 Selected Course                                          │
├─────────────────────────────────────────────────────────────┤
│  Course Name: Python Programming                            │
│  Code: PY-2026                                              │
│  Fees: ₹15,000                                              │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔓 EDITABLE MODE (No course_id in URL)

### URL Example:
```
http://localhost/public_html/student/register.php
```

### Visual Appearance:

```
┌─────────────────────────────────────────────────────────────┐
│  📚 Course Selection                                         │
│  Choose your desired course and training center             │
├─────────────────────────────────────────────────────────────┤
│                                                              │
│  Training Center *                                           │
│  ┌────────────────────────────────────────────────────┐    │
│  │ Select Training Center                        ▼    │    │
│  │ (White background, clickable dropdown)             │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
│  Select Course *                                             │
│  ┌────────────────────────────────────────────────────┐    │
│  │ Select Course                                 ▼    │    │
│  │ (White background, clickable dropdown)             │    │
│  └────────────────────────────────────────────────────┘    │
│                                                              │
└─────────────────────────────────────────────────────────────┘
```

### No Course Info Card Displayed

---

## 🎨 Color Scheme

### Locked Fields:
```
Background:    #f0f9ff (Light Blue)
Border:        #90caf9 (Blue)
Text:          #0d47a1 (Dark Blue)
Font Weight:   600 (Semi-bold)
Cursor:        not-allowed
```

### Editable Fields:
```
Background:    #ffffff (White)
Border:        #e2e8f0 (Gray)
Text:          #1e293b (Dark Gray)
Font Weight:   400 (Normal)
Cursor:        pointer
```

### Lock Icon:
```
Icon:          🔒 (fa-lock)
Color:         #64748b (Muted Gray)
Size:          13px
Position:      Below field
```

---

## 📱 Responsive Design

### Desktop (> 768px):

```
┌──────────────────────────┬──────────────────────────┐
│  Training Center *       │  Select Course *         │
│  ┌────────────────────┐  │  ┌────────────────────┐  │
│  │ NIELIT Bhubaneswar │  │  │ Python Programming │  │
│  └────────────────────┘  │  └────────────────────┘  │
│  🔒 Locked by link       │  🔒 Locked by link       │
└──────────────────────────┴──────────────────────────┘
```

### Mobile (≤ 768px):

```
┌──────────────────────────────────────┐
│  Training Center *                   │
│  ┌────────────────────────────────┐  │
│  │ NIELIT Bhubaneswar Center      │  │
│  └────────────────────────────────┘  │
│  🔒 Locked by registration link      │
└──────────────────────────────────────┘

┌──────────────────────────────────────┐
│  Select Course *                     │
│  ┌────────────────────────────────┐  │
│  │ Python Programming (PY-2026)   │  │
│  └────────────────────────────────┘  │
│  🔒 Locked by registration link      │
└──────────────────────────────────────┘
```

---

## 🖱️ User Interactions

### Locked Field Interactions:

| Action | Result |
|--------|--------|
| Click | No action (cursor: not-allowed) |
| Hover | Cursor changes to not-allowed |
| Focus | No focus ring, no background change |
| Tab | Skips to next editable field |
| Type | No input accepted |

### Editable Field Interactions:

| Action | Result |
|--------|--------|
| Click | Dropdown opens |
| Hover | Border color changes |
| Focus | Blue ring appears |
| Tab | Moves to field |
| Type | Filters options (if searchable) |

---

## 🔄 State Transitions

### Flow Diagram:

```
User Action
    ↓
┌─────────────────────┐
│ Click Course Link   │
│ (with course_id)    │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ Load register.php   │
│ ?course_id=5        │
└──────────┬──────────┘
           ↓
┌─────────────────────┐
│ Fetch Course from   │
│ Database            │
└──────────┬──────────┘
           ↓
    ┌──────┴──────┐
    │ Found?      │
    └──────┬──────┘
           │
    ┌──────┴──────┐
    │             │
   YES           NO
    │             │
    ↓             ↓
┌─────────┐  ┌─────────┐
│ LOCKED  │  │EDITABLE │
│ MODE    │  │ MODE    │
└─────────┘  └─────────┘
```

---

## 📊 Visual Comparison Table

| Feature | Locked Mode | Editable Mode |
|---------|-------------|---------------|
| **Background** | 🔵 Light Blue | ⚪ White |
| **Border** | 🔵 Blue | ⚫ Gray |
| **Text Color** | 🔵 Dark Blue | ⚫ Dark Gray |
| **Font Weight** | **Bold** | Normal |
| **Cursor** | 🚫 Not-allowed | 👆 Pointer |
| **Icon** | 🔒 Lock | None |
| **Helper Text** | "Locked by link" | None |
| **Subtitle** | "Your selected course" | "Choose your course" |
| **Course Card** | ✅ Displayed | ❌ Hidden |
| **Dropdown** | ❌ Disabled | ✅ Enabled |
| **Hidden Input** | ✅ Yes | ❌ No |
| **JavaScript** | ❌ Disabled | ✅ Enabled |

---

## 🎯 Visual Indicators Checklist

### When Course is Locked:
- [ ] Light blue background (#f0f9ff)
- [ ] Blue border (#90caf9)
- [ ] Dark blue text (#0d47a1)
- [ ] Bold font weight (600)
- [ ] Not-allowed cursor
- [ ] Lock icon (🔒) visible
- [ ] "Locked by registration link" text
- [ ] Section subtitle says "locked"
- [ ] Course info card displayed above
- [ ] No dropdown arrow
- [ ] Cannot click or edit

### When Course is Editable:
- [ ] White background (#ffffff)
- [ ] Gray border (#e2e8f0)
- [ ] Dark gray text (#1e293b)
- [ ] Normal font weight (400)
- [ ] Pointer cursor
- [ ] No lock icon
- [ ] No lock text
- [ ] Section subtitle says "Choose"
- [ ] No course info card
- [ ] Dropdown arrow visible
- [ ] Can click and select

---

## 🖼️ Screenshot Descriptions

### Locked Mode Screenshot:
```
┌────────────────────────────────────────────────────────┐
│                                                         │
│  ┌──────────────────────────────────────────────────┐ │
│  │ 🎓 Selected Course                                │ │
│  │ Course Name: Python Programming                   │ │
│  │ Code: PY-2026    Fees: ₹15,000                   │ │
│  └──────────────────────────────────────────────────┘ │
│                                                         │
│  ┌──────────────────────────────────────────────────┐ │
│  │ 📚 Course Selection                               │ │
│  │ Your selected course (locked)                     │ │
│  ├──────────────────────────────────────────────────┤ │
│  │                                                    │ │
│  │ Training Center *                                  │ │
│  │ ┌──────────────────────────────────────────────┐ │ │
│  │ │ NIELIT Bhubaneswar Center                    │ │ │
│  │ │ (Light blue background)                      │ │ │
│  │ └──────────────────────────────────────────────┘ │ │
│  │ 🔒 Locked by registration link                    │ │
│  │                                                    │ │
│  │ Select Course *                                    │ │
│  │ ┌──────────────────────────────────────────────┐ │ │
│  │ │ Python Programming (PY-2026)                 │ │ │
│  │ │ (Light blue background)                      │ │ │
│  │ └──────────────────────────────────────────────┘ │ │
│  │ 🔒 Locked by registration link                    │ │
│  │                                                    │ │
│  └──────────────────────────────────────────────────┘ │
│                                                         │
└────────────────────────────────────────────────────────┘
```

### Editable Mode Screenshot:
```
┌────────────────────────────────────────────────────────┐
│                                                         │
│  ┌──────────────────────────────────────────────────┐ │
│  │ 📚 Course Selection                               │ │
│  │ Choose your desired course and training center    │ │
│  ├──────────────────────────────────────────────────┤ │
│  │                                                    │ │
│  │ Training Center *                                  │ │
│  │ ┌──────────────────────────────────────────────┐ │ │
│  │ │ Select Training Center              ▼       │ │ │
│  │ │ (White background, clickable)                │ │ │
│  │ └──────────────────────────────────────────────┘ │ │
│  │                                                    │ │
│  │ Select Course *                                    │ │
│  │ ┌──────────────────────────────────────────────┐ │ │
│  │ │ Select Course                       ▼       │ │ │
│  │ │ (White background, clickable)                │ │ │
│  │ └──────────────────────────────────────────────┘ │ │
│  │                                                    │ │
│  └──────────────────────────────────────────────────┘ │
│                                                         │
└────────────────────────────────────────────────────────┘
```

---

## 🎨 CSS Classes Reference

### Applied to Locked Fields:
```css
.form-control[readonly] {
    background-color: #f0f9ff !important;
    cursor: not-allowed;
    border-color: #90caf9;
    color: #0d47a1;
    font-weight: 600;
}
```

### Inline Styles:
```html
style="background-color: #f0f9ff; cursor: not-allowed;"
```

### Helper Text:
```html
<small class="text-muted">
    <i class="fas fa-lock"></i> Locked by registration link
</small>
```

---

## 🔍 Browser DevTools View

### Locked Field HTML:
```html
<input 
    type="text" 
    class="form-control" 
    value="NIELIT Bhubaneswar Center" 
    readonly 
    style="background-color: #f0f9ff; cursor: not-allowed;"
>
<input 
    type="hidden" 
    name="training_center" 
    value="NIELIT BHUBANESWAR CENTER"
>
```

### Editable Field HTML:
```html
<select 
    class="form-select" 
    name="training_center" 
    id="training_center" 
    required
>
    <option value="">Select Training Center</option>
    <option value="NIELIT BHUBANESWAR CENTER">
        NIELIT Bhubaneswar Center
    </option>
    <option value="NIELIT EXTENDED CENTER BALASORE">
        NIELIT Balasore Extension Centre
    </option>
</select>
```

---

## 📱 Mobile View Comparison

### Locked (Mobile):
```
┌─────────────────────────┐
│ Training Center *       │
│ ┌─────────────────────┐ │
│ │ NIELIT Bhubaneswar  │ │
│ │ Center              │ │
│ │ (Blue background)   │ │
│ └─────────────────────┘ │
│ 🔒 Locked by link       │
└─────────────────────────┘

┌─────────────────────────┐
│ Select Course *         │
│ ┌─────────────────────┐ │
│ │ Python Programming  │ │
│ │ (PY-2026)           │ │
│ │ (Blue background)   │ │
│ └─────────────────────┘ │
│ 🔒 Locked by link       │
└─────────────────────────┘
```

### Editable (Mobile):
```
┌─────────────────────────┐
│ Training Center *       │
│ ┌─────────────────────┐ │
│ │ Select Training     │ │
│ │ Center         ▼    │ │
│ │ (White background)  │ │
│ └─────────────────────┘ │
└─────────────────────────┘

┌─────────────────────────┐
│ Select Course *         │
│ ┌─────────────────────┐ │
│ │ Select Course  ▼    │ │
│ │ (White background)  │ │
│ └─────────────────────┘ │
└─────────────────────────┘
```

---

## 🎯 Quick Visual Test

### Test Locked Mode:
1. Open: `register.php?course_id=1`
2. Look for: 🔵 Blue background
3. Look for: 🔒 Lock icon
4. Look for: "Locked by registration link" text
5. Try: Clicking field (should show not-allowed cursor)
6. Check: Course info card at top

### Test Editable Mode:
1. Open: `register.php`
2. Look for: ⚪ White background
3. Look for: ▼ Dropdown arrow
4. Look for: "Choose your desired course" text
5. Try: Clicking field (should open dropdown)
6. Check: No course info card

---

## 🎉 Summary

The course lock feature provides clear visual feedback:

✅ **Locked fields** have blue background and lock icon
✅ **Editable fields** have white background and dropdown
✅ **Section subtitle** changes based on mode
✅ **Course info card** shows when locked
✅ **Cursor changes** to not-allowed for locked fields
✅ **Responsive design** works on all devices
✅ **Consistent styling** matches overall theme

Users will immediately understand whether they can change the course selection or not!

---

**Visual Design**: ✅ COMPLETE
**User Experience**: ✅ INTUITIVE
**Accessibility**: ✅ CLEAR
