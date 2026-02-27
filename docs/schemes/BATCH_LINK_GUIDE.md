# How to Link Batches to Schemes

## Step 1: Install Database Updates

Run this URL in your browser:
```
http://localhost/public_html/schemes_module/install_batch_scheme_link.php
```

This will add the `scheme_id` column to your batches table.

## Step 2: Link Batches to Schemes

### Option A: Edit Existing Batch
1. Go to **Batches → Manage Batches**
2. Click **Edit** on any batch
3. You'll see a new dropdown: **Scheme/Project**
4. Select a scheme (SCSP, TSP, PMKVY, etc.)
5. Click **Update Batch**

### Option B: When Creating New Batch
1. Go to **Batches → Manage Batches**
2. Click **Add New Batch**
3. Fill in all details
4. Select a **Scheme/Project** from the dropdown
5. Click **Create Batch**

## Step 3: Generate Admission Orders

Once batches are linked to schemes:

1. Go to **Schemes → Manage Schemes**
2. Click **Edit** on any scheme
3. Click **Generate Admission Order** button
4. Select the scheme and batch
5. Click **Generate Admission Order**
6. Preview the order
7. Download as PDF or Print

## Features

- **Scheme Selection**: Dropdown shows all active schemes
- **Optional Field**: You can leave scheme blank if not needed
- **Admission Orders**: Generate official NIELIT-style admission orders
- **PDF Export**: Download orders as PDF
- **Print**: Direct print functionality

## Scheme-Batch Relationship

- One batch can belong to ONE scheme
- One scheme can have MULTIPLE batches
- Batches without schemes work normally
- Only scheme-linked batches appear in admission order generator

## Troubleshooting

### "No batches found for this scheme"
- Make sure you've linked at least one batch to the scheme
- Check that the batch status is "Active"
- Verify the scheme_id column exists in batches table

### Scheme dropdown not showing
- Run the installation script first
- Check that schemes exist and are "Active"
- Clear browser cache

### Database error
- Make sure you ran `install_batch_scheme_link.php`
- Check MySQL error logs
- Verify foreign key constraints are working

## Database Structure

```sql
batches table:
- id
- batch_name
- course_id
- scheme_id (NEW - links to schemes table)
- start_date
- end_date
- ...

schemes table:
- id
- scheme_name
- scheme_code
- status
- ...
```

## Quick Reference

| Action | Location |
|--------|----------|
| Install | `schemes_module/install_batch_scheme_link.php` |
| Link Batch | Batches → Edit Batch → Scheme/Project dropdown |
| Generate Order | Schemes → Edit Scheme → Generate Admission Order |
| View Orders | Schemes → Generate Admission Order page |

---

**Note**: The admission order format matches official NIELIT documents with proper headers, student tables, and signatures.
