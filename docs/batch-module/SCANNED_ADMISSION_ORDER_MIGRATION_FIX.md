# 🔧 Scanned Admission Order Migration Fix

## Issue Resolved
Fixed the SQL migration error that occurred when trying to create indexes on columns that hadn't been created yet.

### ❌ **Original Error:**
```
Error executing SQL: Key column 'scanned_order_uploaded_at' doesn't exist in table
Statement: CREATE INDEX `idx_scanned_order_uploaded_at` ON `batches` (`scanned_order_uploaded_at`)
```

### 🔧 **Root Cause:**
The original SQL migration file was trying to execute all statements in a single ALTER TABLE command, and then create indexes immediately after. However, the SQL parser was executing the CREATE INDEX statements before the columns were actually created.

### ✅ **Solution Applied:**

#### 1. **Separated Column Creation**
Changed from single ALTER TABLE with multiple ADD COLUMN statements to individual ALTER TABLE statements:

```sql
-- Before (problematic)
ALTER TABLE `batches` 
ADD COLUMN `scanned_admission_order` VARCHAR(255) NULL,
ADD COLUMN `scanned_order_uploaded_at` TIMESTAMP NULL,
-- ... more columns

-- After (fixed)
ALTER TABLE `batches` ADD COLUMN `scanned_admission_order` VARCHAR(255) NULL;
ALTER TABLE `batches` ADD COLUMN `scanned_order_uploaded_at` TIMESTAMP NULL;
-- ... individual statements
```

#### 2. **Enhanced Error Handling**
Added comprehensive error handling in the PHP migration script:

```php
// Check for duplicate column errors
if (strpos($conn->error, 'Duplicate column name') !== false) {
    echo "⚠️ Column already exists, skipping...\n";
    continue;
}

// Check for duplicate constraint errors  
if (strpos($conn->error, 'Duplicate key name') !== false) {
    echo "⚠️ Constraint already exists, skipping...\n";
    continue;
}
```

#### 3. **Sequential Execution**
Modified the migration to execute in proper order:
1. ✅ Create columns first
2. ✅ Add foreign key constraints second  
3. ✅ Create indexes last
4. ✅ Create directories and security files

### 📁 **Files Updated:**

#### **migrations/add_scanned_admission_order.sql**
- Separated multi-column ALTER TABLE into individual statements
- Removed problematic batch execution

#### **migrations/install_scanned_admission_order.php**
- Added comprehensive error handling
- Sequential execution of SQL statements
- Better feedback messages
- Duplicate detection and skipping

#### **test_scanned_order_migration.php**
- Applied same fixes as main migration
- Enhanced error reporting
- Better user feedback

#### **.gitignore**
- Added scanned admission orders directory exclusion

### 🚀 **How to Run the Fixed Migration:**

#### **Option 1: Via Web Browser**
```
http://localhost/nielit-main/test_scanned_order_migration.php
```

#### **Option 2: Via Command Line (if PHP CLI available)**
```bash
php test_scanned_order_migration.php
```

#### **Option 3: Manual SQL Execution**
Execute these SQL statements in phpMyAdmin or MySQL client:

```sql
-- Add columns
ALTER TABLE `batches` ADD COLUMN `scanned_admission_order` VARCHAR(255) NULL AFTER `updated_at`;
ALTER TABLE `batches` ADD COLUMN `scanned_order_uploaded_at` TIMESTAMP NULL AFTER `scanned_admission_order`;
ALTER TABLE `batches` ADD COLUMN `scanned_order_uploaded_by` INT(11) NULL AFTER `scanned_order_uploaded_at`;
ALTER TABLE `batches` ADD COLUMN `scanned_order_locked` TINYINT(1) DEFAULT 0 AFTER `scanned_order_uploaded_by`;
ALTER TABLE `batches` ADD COLUMN `scanned_order_locked_at` TIMESTAMP NULL AFTER `scanned_order_locked`;
ALTER TABLE `batches` ADD COLUMN `scanned_order_locked_by` INT(11) NULL AFTER `scanned_order_locked_at`;

-- Add foreign keys (optional, may fail if admin table structure differs)
ALTER TABLE `batches` ADD CONSTRAINT `fk_scanned_order_uploaded_by` FOREIGN KEY (`scanned_order_uploaded_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL;
ALTER TABLE `batches` ADD CONSTRAINT `fk_scanned_order_locked_by` FOREIGN KEY (`scanned_order_locked_by`) REFERENCES `admin` (`id`) ON DELETE SET NULL;

-- Add indexes
CREATE INDEX `idx_scanned_order_locked` ON `batches` (`scanned_order_locked`);
CREATE INDEX `idx_scanned_order_uploaded_at` ON `batches` (`scanned_order_uploaded_at`);
```

### 📂 **Directory Creation:**
The migration will also create:
```
uploads/scanned_admission_orders/
├── .htaccess          # Security protection
└── .gitkeep          # Git tracking
```

### ✅ **Expected Output:**
```
Testing Scanned Admission Order Migration...

📝 Adding scanned admission order columns...
✅ Columns added successfully!
📝 Adding foreign key constraints...
✅ Foreign key constraints added!
📝 Adding indexes...
✅ Database schema updated!
✅ Created upload directory: uploads/scanned_admission_orders
✅ Created .htaccess security file
✅ Created .gitkeep file

🎉 Scanned Admission Order Upload Feature installed successfully!

📋 Next steps:
1. Navigate to any batch details page
2. Look for the 'Scanned Admission Order' section
3. Upload a PDF file of the signed admission order
4. Lock the document when ready
5. Download the document anytime
```

### 🔍 **Verification Steps:**

#### 1. **Check Database Schema:**
```sql
DESCRIBE batches;
-- Should show the new scanned_* columns
```

#### 2. **Check Upload Directory:**
```bash
ls -la uploads/scanned_admission_orders/
# Should show .htaccess and .gitkeep files
```

#### 3. **Test Feature:**
1. Navigate to batch details page
2. Look for "Scanned Admission Order" section
3. Upload a test PDF file
4. Verify lock/unlock functionality

### 🎯 **Status: READY FOR USE**

The migration fix has been applied and the scanned admission order upload feature is now ready for production use. The feature includes:

- ✅ Secure PDF file upload
- ✅ Document locking mechanism  
- ✅ Role-based access control
- ✅ Download functionality
- ✅ Complete audit trail
- ✅ Error handling and validation

---

**Migration Fix Applied:** March 25, 2026  
**Status:** ✅ RESOLVED - Ready for deployment