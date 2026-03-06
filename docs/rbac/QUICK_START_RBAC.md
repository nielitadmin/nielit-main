# Quick Start: Simple 2-Role RBAC System

## What Was Implemented

A simplified admin access control system with 2 roles:

1. **Master Admin** - Full access including admin management
2. **Course Coordinator** - Limited access to core features only

## Step-by-Step Setup

### Step 1: Run the Migration (REQUIRED)
1. Open your browser
2. Navigate to: `http://your-site.com/migrations/add_simple_rbac.php`
3. You should see a success page showing:
   - Role column added
   - Timestamp columns added
   - All existing admins set to Master Admin
   - List of current admins with their roles

### Step 2: Test Your Login
1. Log out if currently logged in
2. Log in again with your admin credentials
3. You should now see your role loaded in the session

### Step 3: Explore New Features

#### For Master Admins:
You'll see these NEW menu items in the sidebar:
- **Add Admin** - Create new admin accounts with role selection
- **Manage Admins** - View, edit roles, and delete admin accounts

#### For Course Coordinators:
You'll see these menu items:
- Dashboard
- Students
- Courses
- Batches
- Approve Students
- Reset Password

You will NOT see:
- Add Admin
- Manage Admins
- Training Centres
- Themes
- Homepage Content

## How to Add a New Admin

1. Click "Add Admin" in the sidebar (Master Admin only)
2. Fill in the form:
   - Username
   - Email
   - Password
   - Phone
   - **Select Role:** Master Admin or Course Coordinator
3. Click "Send OTP"
4. Check email for OTP code
5. Enter OTP and click "Verify & Create Admin"
6. Done! New admin created with selected role

## How to Manage Existing Admins

1. Click "Manage Admins" in the sidebar (Master Admin only)
2. You'll see all admin accounts with:
   - Username and email
   - Current role (with colored badge)
   - Created/updated dates
3. To change a role:
   - Select new role from dropdown
   - Click "Update Role"
4. To delete an admin:
   - Click "Delete" button
   - Confirm deletion
5. Note: You cannot modify your own account

## Role Permissions Summary

| Feature | Master Admin | Course Coordinator |
|---------|-------------|-------------------|
| Dashboard | ✅ | ✅ |
| Students | ✅ | ✅ |
| Courses | ✅ | ✅ |
| Batches | ✅ | ✅ |
| Approve Students | ✅ | ✅ |
| Reset Password | ✅ | ✅ |
| Add Admin | ✅ | ❌ |
| Manage Admins | ✅ | ❌ |
| Training Centres | ✅ | ❌ |
| Themes | ✅ | ❌ |
| Homepage Content | ✅ | ❌ |

## Testing the System

### Test 1: Master Admin Access
1. Log in as Master Admin
2. Verify you see "Add Admin" and "Manage Admins" in sidebar
3. Try accessing both pages - should work

### Test 2: Course Coordinator Access
1. Create a new Course Coordinator account
2. Log in with that account
3. Verify "Add Admin" and "Manage Admins" are hidden
4. Try accessing `http://your-site.com/admin/add_admin.php` directly
5. Should redirect to dashboard with "Access denied" message

### Test 3: Role Changes
1. Log in as Master Admin
2. Go to "Manage Admins"
3. Change a Course Coordinator to Master Admin
4. That user should now see admin management features on next login

## Troubleshooting

### "Access denied" message when trying to add admin
**Problem:** You're logged in as Course Coordinator
**Solution:** Only Master Admins can add/manage admins. Ask a Master Admin to change your role.

### Menu items not showing correctly
**Problem:** Session not updated after migration
**Solution:** Log out and log back in to refresh your session.

### Migration page shows errors
**Problem:** Database connection or permissions issue
**Solution:** Check your database credentials in `config/database.php`

### Role column doesn't exist error
**Problem:** Migration not run yet
**Solution:** Run the migration script first: `migrations/add_simple_rbac.php`

## What's Next?

The system is ready to use! Here are some recommendations:

1. **Set up your admin team:**
   - Decide who should be Master Admins (usually 1-2 people)
   - Create Course Coordinator accounts for others

2. **Test thoroughly:**
   - Log in as different roles
   - Verify permissions work as expected
   - Test role changes

3. **Document your admins:**
   - Keep a list of who has which role
   - Document why each person has their role

4. **Security best practices:**
   - Use strong passwords
   - Don't share admin credentials
   - Regularly review admin accounts
   - Remove accounts for people who leave

## Need Help?

Check these files for more information:
- `docs/rbac/SIMPLE_RBAC_IMPLEMENTATION.md` - Full technical documentation
- `migrations/add_simple_rbac.php` - Migration script
- `admin/manage_admins.php` - Admin management page
- `includes/session_manager.php` - Session and permission handling

## Summary

You now have a working 2-role admin system! Master Admins can manage everything including other admins, while Course Coordinators have access to core student and course management features only.

**Remember:** Run the migration first, then log out and back in to see the changes!
