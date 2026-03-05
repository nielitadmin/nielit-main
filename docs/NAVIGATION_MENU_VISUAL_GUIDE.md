# Navigation Menu System - Visual Guide

## Where to Find It

### Step 1: Log in to Admin Panel
```
URL: admin/login.php
```

### Step 2: Go to Homepage Content
```
Dashboard → Homepage Content
```

### Step 3: Click "Edit Navigation Menu"
```
┌─────────────────────────────────────────────────────────┐
│ Homepage Content Sections                               │
├─────────────────────────────────────────────────────────┤
│                                                         │
│  [Manage Announcements] [Edit Navigation Menu] [+ Add] │
│                                                         │
│                    ↑ NEW BUTTON HERE                    │
└─────────────────────────────────────────────────────────┘
```

## Admin Interface Layout

```
┌──────────────────────────────────────────────────────────────┐
│  Manage Navigation Menu                                      │
│  Control menu items displayed on the public website          │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  [← Back to Homepage]  [+ Add Menu Item]                     │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐ │
│  │ Navigation Menu Items                                  │ │
│  ├────────────────────────────────────────────────────────┤ │
│  │ ☰  Order  Label          URL              Status      │ │
│  ├────────────────────────────────────────────────────────┤ │
│  │ ⋮   1     Home           index.php        Active      │ │
│  │ ⋮   2     Job Fair       DGR/index.php    Active      │ │
│  │ ⋮   3     PM SHRI KV JNV #                Active      │ │
│  │     └─ 1  Membership Form Membership...   Active      │ │
│  │ ⋮   4     Student Zone   #                Active      │ │
│  │     └─ 1  Courses        public/courses   Active      │ │
│  │     └─ 2  Portal         student/login    Active      │ │
│  │ ⋮   5     Admin          #                Active      │ │
│  │     └─ 1  Admin Login    admin/login      Active      │ │
│  │     └─ 2  Finance Login  /Salary_Slip...  Active      │ │
│  │ ⋮   6     Contact        public/contact   Active      │ │
│  │                                                        │ │
│  │         [Edit] [Toggle] [Delete] buttons →            │ │
│  └────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

## Add Menu Item Form

```
┌─────────────────────────────────────────────┐
│  Add Menu Item                          × │
├─────────────────────────────────────────────┤
│                                             │
│  Label *                                    │
│  ┌─────────────────────────────────────┐   │
│  │ About Us                            │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  URL *                                      │
│  ┌─────────────────────────────────────┐   │
│  │ public/about.php                    │   │
│  └─────────────────────────────────────┘   │
│  Use # for dropdown parent items            │
│                                             │
│  Parent Menu                                │
│  ┌─────────────────────────────────────┐   │
│  │ None (Top Level)            ▼       │   │
│  └─────────────────────────────────────┘   │
│                                             │
│  Display Order    Target                    │
│  ┌──────────┐    ┌──────────────────┐      │
│  │ 7        │    │ Same Window  ▼   │      │
│  └──────────┘    └──────────────────┘      │
│                                             │
│  Icon (Optional)                            │
│  ┌─────────────────────────────────────┐   │
│  │ fas fa-info-circle                  │   │
│  └─────────────────────────────────────┘   │
│  FontAwesome icon class                     │
│                                             │
│         [Cancel]  [Save Menu Item]          │
└─────────────────────────────────────────────┘
```

## Menu Structure Examples

### Example 1: Simple Top-Level Item
```
┌─────────────────────────────────────┐
│ Navigation Bar                      │
├─────────────────────────────────────┤
│ Home | Job Fair | About Us | Contact│
│                      ↑               │
│                  NEW ITEM            │
└─────────────────────────────────────┘
```

### Example 2: Dropdown Menu
```
┌──────────────────────────────────────────┐
│ Navigation Bar                           │
├──────────────────────────────────────────┤
│ Home | Resources ▼ | Contact             │
│           │                              │
│           ├─ Downloads                   │
│           ├─ FAQs                        │
│           └─ Gallery                     │
│                                          │
│      Parent: Resources (URL = #)         │
│      Children: Downloads, FAQs, Gallery  │
└──────────────────────────────────────────┘
```

## Creating a Dropdown - Step by Step

### Step 1: Create Parent Item
```
Label: Resources
URL: #  ← Important! Use # for dropdowns
Parent: None (Top Level)
Order: 5
```

### Step 2: Create First Child
```
Label: Downloads
URL: public/downloads.php
Parent: Resources  ← Select the parent
Order: 1
```

### Step 3: Create More Children
```
Label: FAQs
URL: public/faqs.php
Parent: Resources
Order: 2

Label: Gallery
URL: public/gallery.php
Parent: Resources
Order: 3
```

### Result on Website
```
┌─────────────────────────────────────┐
│ Home | Resources ▼ | Contact         │
│           │                          │
│           ├─ Downloads               │
│           ├─ FAQs                    │
│           └─ Gallery                 │
└─────────────────────────────────────┘
```

## Status Indicators

### Active Item (Green Badge)
```
┌──────────────────────────────────┐
│ Home    index.php    [Active]    │
│                       ↑ Green    │
└──────────────────────────────────┘
Visible on website
```

### Inactive Item (Gray Badge)
```
┌──────────────────────────────────┐
│ Test    test.php    [Inactive]   │
│                      ↑ Gray      │
└──────────────────────────────────┘
Hidden from website
```

## Action Buttons

```
┌─────────────────────────────────────────┐
│ Menu Item                               │
│                                         │
│  [✏️ Edit] [🔄 Toggle] [🗑️ Delete]      │
│     │         │           │             │
│     │         │           └─ Delete item│
│     │         └─ Show/Hide on website   │
│     └─ Modify item details              │
└─────────────────────────────────────────┘
```

## Display Order Visualization

```
Order 1  →  Home           (appears first)
Order 2  →  Job Fair       (appears second)
Order 3  →  Resources      (appears third)
Order 4  →  Contact        (appears last)

Lower numbers = Earlier position
```

## Parent-Child Relationship

```
Parent Item (Order 3)
  └─ Child 1 (Order 1)  ← First child
  └─ Child 2 (Order 2)  ← Second child
  └─ Child 3 (Order 3)  ← Third child

Parent's order controls where dropdown appears
Child's order controls order within dropdown
```

## Link Targets

### Same Window (_self)
```
Click → Opens in same tab
Default for internal links
```

### New Window (_blank)
```
Click → Opens in new tab
Good for external links
```

## Icons

### With Icon
```
🏠 Home
📚 Courses
📧 Contact
```

### Without Icon
```
Home
Courses
Contact
```

### Icon Classes (FontAwesome)
```
fas fa-home       → 🏠
fas fa-book       → 📚
fas fa-envelope   → 📧
fas fa-user       → 👤
fas fa-cog        → ⚙️
```

## Before and After

### Before (Hardcoded in index.php)
```php
<li><a href="index.php">Home</a></li>
<li><a href="courses.php">Courses</a></li>
↑ Must edit code to change
```

### After (Database-Driven)
```
Admin Panel → Edit Navigation Menu
→ Click, type, save
→ Changes appear instantly
↑ No code editing needed
```

## Common Tasks

### Add New Page to Menu
```
1. Create your page (e.g., public/about.php)
2. Go to: Admin → Edit Navigation Menu
3. Click: Add Menu Item
4. Fill in: Label, URL, Order
5. Save
6. Done! ✓
```

### Reorder Menu Items
```
1. Click Edit on item
2. Change Display Order number
3. Save
4. Refresh website to see change
```

### Hide Menu Item Temporarily
```
1. Click Toggle button (🔄)
2. Status changes to Inactive
3. Item disappears from website
4. Click Toggle again to show
```

### Create Dropdown
```
1. Add parent with URL = "#"
2. Add children with parent selected
3. Children appear under parent
4. Dropdown works automatically
```

## Tips & Tricks

✅ **Start with high order numbers** (10, 20, 30) so you can insert items between them later

✅ **Test as inactive first** - Create items inactive, verify they work, then activate

✅ **Use descriptive labels** - Clear, concise text that users understand

✅ **Keep URLs relative** - Use "public/about.php" not full URLs

✅ **Group related items** - Use dropdowns to organize similar pages

✅ **Add icons sparingly** - Too many icons can be distracting

## Troubleshooting Visual Guide

### Problem: Menu not showing
```
Check:
1. Is item Active? (green badge)
2. Is URL correct?
3. Clear browser cache
```

### Problem: Dropdown not working
```
Check:
1. Parent URL = "#" ?
2. Children have parent selected?
3. Bootstrap JS loaded?
```

### Problem: Wrong order
```
Fix:
1. Edit item
2. Change Display Order
3. Save
4. Refresh website
```

---

**Quick Reference Card**

```
┌─────────────────────────────────────────┐
│ NAVIGATION MENU QUICK REFERENCE         │
├─────────────────────────────────────────┤
│ Access: Admin → Homepage → Edit Nav     │
│                                         │
│ Add Item:    [+ Add Menu Item]          │
│ Edit Item:   [✏️ Edit] button            │
│ Hide Item:   [🔄 Toggle] button          │
│ Delete Item: [🗑️ Delete] button          │
│                                         │
│ Dropdown Parent: URL = "#"              │
│ Dropdown Child:  Select parent          │
│                                         │
│ Order: Lower = Earlier                  │
│ Status: Green = Visible                 │
│ Target: _self = Same window             │
│                                         │
│ Icons: FontAwesome classes              │
│ Example: fas fa-home                    │
└─────────────────────────────────────────┘
```
