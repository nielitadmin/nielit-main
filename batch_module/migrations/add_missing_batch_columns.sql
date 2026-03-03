-- Migration: Add missing columns to batches table
-- Run this on the server if columns are missing

-- Add scheme_id column if it doesn't exist
ALTER TABLE batches 
ADD COLUMN IF NOT EXISTS scheme_id INT NULL,
ADD FOREIGN KEY IF NOT EXISTS (scheme_id) REFERENCES schemes(id) ON DELETE SET NULL;

-- Add admission order columns if they don't exist
ALTER TABLE batches 
ADD COLUMN IF NOT EXISTS admission_order_ref VARCHAR(100) NULL,
ADD COLUMN IF NOT EXISTS admission_order_date DATE NULL,
ADD COLUMN IF NOT EXISTS examination_month VARCHAR(50) NULL,
ADD COLUMN IF NOT EXISTS class_time VARCHAR(100) DEFAULT '9:00 AM to 1:30 PM',
ADD COLUMN IF NOT EXISTS copy_to_list TEXT NULL,
ADD COLUMN IF NOT EXISTS location VARCHAR(255) DEFAULT 'NIELIT Bhubaneswar';

-- Verify the changes
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_NAME = 'batches'
AND TABLE_SCHEMA = DATABASE()
ORDER BY ORDINAL_POSITION;
