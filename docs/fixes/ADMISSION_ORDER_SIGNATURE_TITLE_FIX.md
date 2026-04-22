# Admission Order Signature Title Fix

## Issue
In admission orders, the signature line was showing "(regular) Incharge" when the batch scheme was "regular". The user wanted it to show "Project Incharge" instead for regular schemes only.

## Current Behavior (Before Fix)
```
Signature
22-04-2026
testname
(regular) Incharge,
NIELIT Bhubaneswar.
```

## Expected Behavior (After Fix)
```
Signature
22-04-2026
testname
Project Incharge,
NIELIT Bhubaneswar.
```

## Solution Applied
Modified `batch_module/admin/generate_admission_order_ajax.php` line 483 to add conditional logic:

### Before:
```php
<p style="margin: 3px 0;"><strong>(<?php echo htmlspecialchars($batch['scheme_code'] ?? 'SCSP/TSP'); ?>) Incharge,</strong></p>
```

### After:
```php
<p style="margin: 3px 0;"><strong><?php 
    $scheme_code = $batch['scheme_code'] ?? 'SCSP/TSP';
    if (strtolower($scheme_code) === 'regular') {
        echo 'Project Incharge,';
    } else {
        echo '(' . htmlspecialchars($scheme_code) . ') Incharge,';
    }
?></strong></p>
```

## Logic
- If `scheme_code` is "regular" (case-insensitive), display "Project Incharge,"
- For all other scheme codes (SCSP/TSP, etc.), display "(scheme_code) Incharge,"
- Maintains backward compatibility for existing schemes

## Files Modified
- `batch_module/admin/generate_admission_order_ajax.php`

## Testing
1. Create/edit a batch with scheme_code = "regular"
2. Generate admission order
3. Verify signature shows "Project Incharge," instead of "(regular) Incharge,"
4. Test with other scheme codes to ensure they still show "(scheme_code) Incharge,"

## Status
✅ **COMPLETE** - Signature title now displays correctly based on scheme type