# Navigation Menu System - Quick Start Guide

## What is This?

A new feature that lets you edit the website's navigation menu (Home, Student Zone, Admin, etc.) through the admin panel instead of editing code.

## Installation (3 Steps)

### Step 1: Run Database Migration

**Option A - Using phpMyAdmin:**
1. Open phpMyAdmin
2. Select your database
3. Click "Import" tab
4. Choose file: `migrations/add_navigation_menu.sql`
5. Click "Go"

**Option B - Using MySQL Command:**
```bash
mysql -u your_username -p your_database < migrations/add_navigation_menu.sql
```

### Step 2: Upload Files

Make sure these files are uploaded to your server:
- `admin/manage_navigation.php`
- `includes/navigation_helper.php`
- `migrations/add_navigation_menu.sql`

### Step 3: Verify

1. Log in to admin panel
2. Go to "Homepage Content"
3. You should see a new button: **"Edit Navigation Menu"**
4. Click it to manage menu items

## How to Use

### Access the Menu Editor
**Admin Panel** → **Homepage Content** → **Edit Navigation Menu**

### Add a Menu Item
1. Click **"Add Menu Item"**
2. Enter:
   - **Label**: What users see (e.g., "About Us")
   - **URL**: Where it goes (e.g., "public/about.php")
   - **Parent Menu**: Leave as "None" for top-level, or select a parent for dropdown
   - **Display Order**: Lower numbers appear first (e.g., 1, 2, 3...)
3. Click **"Save"**

### Create a Dropdown Menu
1. Add parent item with URL = "#"
2. Add child items and select the parent from dropdown

### Edit/Delete Items
- Click **pencil icon** to edit
- Click **trash icon** to delete
- Click **toggle icon** to show/hide

## Examples

### Simple Menu Item
```
Label: About Us
URL: public/about.php
Parent: None (Top Level)
Order: 5
```

### Dropdown Menu
```
Parent Item:
  Label: Resources
  URL: #
  Parent: None
  Order: 4

Child Items:
  Label: Downloads
  URL: public/downloads.php
  Parent: Resources
  Order: 1
  
  Label: FAQs
  URL: public/faqs.php
  Parent: Resources
  Order: 2
```

## Default Menu Items

The system comes pre-loaded with your current menu:
- Home
- Job Fair
- PM SHRI KV JNV (with Membership Form)
- Student Zone (with Courses, Portal)
- Admin (with logins)
- Contact

You can edit or delete these as needed.

## Tips

✅ **Use "#" for dropdown parents** - Items with children should have URL = "#"

✅ **Display Order matters** - Lower numbers appear first (1, 2, 3...)

✅ **Test inactive first** - Create items as inactive, test, then activate

✅ **Backup before deleting** - Deleting a parent deletes all children

✅ **Use icons** - Add FontAwesome icons like "fas fa-home" for visual appeal

## Troubleshooting

**Menu not showing on website?**
- Check if items are marked "Active" (green badge)
- Clear browser cache
- Verify URLs are correct

**Can't see the button?**
- Make sure you ran the database migration
- Check that files are uploaded
- Log out and log back in

**Dropdown not working?**
- Parent URL must be "#"
- Child items must have parent selected
- Check Bootstrap is loaded

## Need Help?

See full documentation: `docs/NAVIGATION_MENU_SYSTEM.md`

---

**Quick Reference:**
- **Admin Access**: admin/manage_navigation.php
- **Database Table**: navigation_menu
- **Migration File**: migrations/add_navigation_menu.sql
