-- Add payment_details_required column to courses table
-- This allows administrators to control whether payment details are required or optional per course

ALTER TABLE courses 
ADD COLUMN payment_details_required ENUM('optional', 'required') DEFAULT 'optional' 
AFTER enrollment_status;

-- Update existing courses to have optional payment details by default
UPDATE courses 
SET payment_details_required = 'optional' 
WHERE payment_details_required IS NULL;