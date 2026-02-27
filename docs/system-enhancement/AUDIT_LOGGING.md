# Audit Logging System

## Overview

The audit logging system tracks all administrative actions performed in the System Enhancement Module. Every create, update, delete, activate, deactivate, and reorder operation is logged with comprehensive details for security and compliance purposes.

**Validates: Requirements 11.5**

## Features

- **Comprehensive Logging**: All CRUD operations are automatically logged
- **Detailed Information**: Each log entry includes timestamp, admin user, action type, resource details, and result status
- **Database Storage**: Logs are stored in a dedicated `audit_logs` table for easy querying
- **Multiple Resource Types**: Supports logging for centres, themes, and homepage content
- **Success/Failure Tracking**: Records both successful and failed operations
- **Query Functions**: Built-in functions to retrieve and analyze audit logs

## Database Schema

### audit_logs Table

```sql
CREATE TABLE audit_logs (
    id INT(11) NOT NULL AUTO_INCREMENT,
    admin_username VARCHAR(100) NOT NULL,
    action_type ENUM('create', 'update', 'delete', 'activate', 'deactivate', 'reorder') NOT NULL,
    resource_type ENUM('centre', 'theme', 'homepage_content') NOT NULL,
    resource_id INT(11) DEFAULT NULL,
    resource_name VARCHAR(255) NOT NULL,
    result ENUM('success', 'failure') NOT NULL,
    details TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_admin_username (admin_username),
    KEY idx_action_type (action_type),
    KEY idx_resource_type (resource_type),
    KEY idx_resource_id (resource_id),
    KEY idx_result (result),
    KEY idx_created_at (created_at),
    KEY idx_resource_lookup (resource_type, resource_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Field Descriptions

- **id**: Unique identifier for each audit log entry
- **admin_username**: Username of the administrator who performed the action
- **action_type**: Type of action (create, update, delete, activate, deactivate, reorder)
- **resource_type**: Type of resource being modified (centre, theme, homepage_content)
- **resource_id**: ID of the specific resource (NULL for failed creates)
- **resource_name**: Human-readable name/identifier of the resource
- **result**: Whether the action succeeded or failed
- **details**: Additional information (error messages, changed fields, etc.)
- **created_at**: Timestamp when the action was performed

## Usage

### Including the Audit Logger

```php
require_once '../includes/audit_logger.php';
```

### Logging Actions

#### Centre Actions

```php
// Log successful centre creation
logCentreAction($conn, $_SESSION['admin'], 'create', $centre_id, $centre_name, 'success', 
    "Created centre: {$code} - {$name}");

// Log failed centre update
logCentreAction($conn, $_SESSION['admin'], 'update', $centre_id, $centre_name, 'failure', 
    "Database error: " . $conn->error);

// Log centre activation
logCentreAction($conn, $_SESSION['admin'], 'activate', $centre_id, $centre_name, 'success', 
    "Activated centre: {$name}");
```

#### Theme Actions

```php
// Log successful theme creation
logThemeAction($conn, $_SESSION['admin'], 'create', $theme_id, $theme_name, 'success', 
    "Created theme: {$theme_name}");

// Log theme activation
logThemeAction($conn, $_SESSION['admin'], 'activate', $theme_id, $theme_name, 'success', 
    "Activated theme: {$theme_name}");
```

#### Homepage Content Actions

```php
// Log successful content creation
logHomepageContentAction($conn, $_SESSION['admin'], 'create', $section_id, $section_key, 'success', 
    "Created content section: {$section_key} - {$section_title}");

// Log content reorder
logHomepageContentAction($conn, $_SESSION['admin'], 'reorder', null, 'multiple_sections', 'success', 
    "Reordered 5 content sections");
```

### Retrieving Audit Logs

#### Get Recent Logs

```php
// Get last 100 logs
$logs = getAuditLogs($conn, 100);

// Get last 50 logs for centres only
$logs = getAuditLogs($conn, 50, 'centre');

// Get last 50 logs by specific admin
$logs = getAuditLogs($conn, 50, null, 'admin_username');
```

#### Get Resource-Specific Logs

```php
// Get all logs for a specific centre
$logs = getResourceAuditLogs($conn, 'centre', $centre_id, 50);

// Get all logs for a specific theme
$logs = getResourceAuditLogs($conn, 'theme', $theme_id, 50);
```

#### Get Statistics

```php
// Get overall statistics
$stats = getAuditLogStatistics($conn);

// Get statistics for a date range
$stats = getAuditLogStatistics($conn, '2024-01-01', '2024-12-31');
```

## Integration Points

The audit logging system is integrated into the following management pages:

### 1. admin/manage_centres.php

Logs the following actions:
- Centre creation (success/failure)
- Centre updates (success/failure)
- Centre activation/deactivation (success/failure)

### 2. admin/manage_themes.php

Logs the following actions:
- Theme creation (success/failure)
- Theme updates (success/failure)
- Theme activation (success/failure)

### 3. admin/manage_homepage.php

Logs the following actions:
- Content section creation (success/failure)
- Content section updates (success/failure)
- Content section activation/deactivation (success/failure)
- Content section reordering (success/failure)

## Example Log Entries

### Successful Centre Creation

```
admin_username: admin@nielit.gov.in
action_type: create
resource_type: centre
resource_id: 5
resource_name: NIELIT Balasore Extension
result: success
details: Created centre: BAL - NIELIT Balasore Extension
created_at: 2024-01-15 10:30:45
```

### Failed Theme Update

```
admin_username: admin@nielit.gov.in
action_type: update
resource_type: theme
resource_id: 3
resource_name: Blue Theme
result: failure
details: Database error: Duplicate entry for theme_name
created_at: 2024-01-15 11:15:22
```

### Content Section Reorder

```
admin_username: admin@nielit.gov.in
action_type: reorder
resource_type: homepage_content
resource_id: NULL
resource_name: multiple_sections
result: success
details: Reordered 5 content sections
created_at: 2024-01-15 14:20:10
```

## Security Considerations

1. **Automatic Logging**: All administrative actions are logged automatically - no manual intervention required
2. **Immutable Records**: Audit logs should never be modified or deleted (except for system maintenance)
3. **Access Control**: Only administrators should have access to audit logs
4. **Data Retention**: Consider implementing a retention policy for old audit logs
5. **Performance**: Indexes are optimized for common query patterns

## Testing

Run the audit logging test suite:

```bash
php tests/test_audit_logging.php
```

The test suite verifies:
- Centre action logging
- Theme action logging
- Homepage content action logging
- Failed action logging
- Log retrieval functions
- Resource-specific log queries
- Statistics generation

## Maintenance

### Viewing Recent Logs

```sql
-- View last 50 audit logs
SELECT * FROM audit_logs 
ORDER BY created_at DESC 
LIMIT 50;

-- View failed actions only
SELECT * FROM audit_logs 
WHERE result = 'failure' 
ORDER BY created_at DESC;

-- View actions by specific admin
SELECT * FROM audit_logs 
WHERE admin_username = 'admin@nielit.gov.in' 
ORDER BY created_at DESC;
```

### Cleanup Old Logs

```sql
-- Delete logs older than 1 year (use with caution!)
DELETE FROM audit_logs 
WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR);
```

### Statistics Queries

```sql
-- Count actions by type
SELECT action_type, COUNT(*) as count 
FROM audit_logs 
GROUP BY action_type;

-- Count actions by resource type
SELECT resource_type, COUNT(*) as count 
FROM audit_logs 
GROUP BY resource_type;

-- Success vs failure rate
SELECT result, COUNT(*) as count 
FROM audit_logs 
GROUP BY result;

-- Most active administrators
SELECT admin_username, COUNT(*) as action_count 
FROM audit_logs 
GROUP BY admin_username 
ORDER BY action_count DESC 
LIMIT 10;
```

## Troubleshooting

### Logs Not Being Created

1. Check that the `audit_logs` table exists:
   ```sql
   SHOW TABLES LIKE 'audit_logs';
   ```

2. Verify the audit_logger.php file is included:
   ```php
   require_once '../includes/audit_logger.php';
   ```

3. Check for database errors in PHP error logs

### Performance Issues

If audit logging causes performance issues:

1. Verify indexes are created properly
2. Consider archiving old logs to a separate table
3. Implement asynchronous logging for high-traffic systems

## Future Enhancements

Potential improvements for the audit logging system:

1. **Web Interface**: Create an admin page to view and search audit logs
2. **Export Functionality**: Allow exporting logs to CSV/PDF for reporting
3. **Real-time Alerts**: Send notifications for critical actions or failures
4. **Advanced Filtering**: Add more sophisticated search and filter options
5. **Log Archiving**: Automatic archiving of old logs to reduce table size
6. **Compliance Reports**: Generate compliance reports for audits

## Related Documentation

- [System Enhancement Module Design](../../.kiro/specs/system-enhancement-module/design.md)
- [Security Implementation](./INPUT_SANITIZATION_COMPLETE.md)
- [Installation Guide](../../migrations/README.md)
