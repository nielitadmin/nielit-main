<?php
/**
 * Create Organized Directory Structure for Categorized Documents
 * 
 * This script creates the subdirectories needed for the document upload enhancement feature.
 * It creates directories with appropriate permissions and adds .htaccess files for security.
 * 
 * Task: 2.1 - Create organized directory structure for categorized documents
 * Requirements: 11.1, 11.4
 */

// Define the base uploads directory
$baseDir = __DIR__ . '/../uploads/';

// Define the directory structure to create
$directories = [
    'aadhar',
    'caste_certificates',
    'marksheets/10th',
    'marksheets/12th',
    'marksheets/graduation',
    'other'
];

// .htaccess content for security
$htaccessContent = <<<'HTACCESS'
# Prevent direct access to uploaded files
# Files should only be served through the application's access control

# Deny access to PHP files
<FilesMatch "\.php$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Prevent directory listing
Options -Indexes

# Prevent execution of scripts
<FilesMatch "\.(php|phtml|php3|php4|php5|pl|py|jsp|asp|sh|cgi)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow only specific file types
<FilesMatch "\.(jpg|jpeg|pdf)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
HTACCESS;

// Track results
$results = [
    'created' => [],
    'exists' => [],
    'errors' => []
];

echo "=== Creating Document Directory Structure ===\n\n";

// Create each directory
foreach ($directories as $dir) {
    $fullPath = $baseDir . $dir;
    
    echo "Processing: $dir\n";
    
    // Check if directory already exists
    if (is_dir($fullPath)) {
        echo "  ✓ Directory already exists\n";
        $results['exists'][] = $dir;
    } else {
        // Create directory with 0755 permissions (recursive)
        if (mkdir($fullPath, 0755, true)) {
            echo "  ✓ Directory created successfully\n";
            $results['created'][] = $dir;
        } else {
            echo "  ✗ Failed to create directory\n";
            $results['errors'][] = $dir;
            continue;
        }
    }
    
    // Add .htaccess file for security
    $htaccessPath = $fullPath . '/.htaccess';
    if (!file_exists($htaccessPath)) {
        if (file_put_contents($htaccessPath, $htaccessContent)) {
            echo "  ✓ .htaccess file created\n";
        } else {
            echo "  ⚠ Warning: Could not create .htaccess file\n";
        }
    } else {
        echo "  ✓ .htaccess file already exists\n";
    }
    
    // Verify permissions
    $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
    echo "  ℹ Permissions: $perms\n";
    
    echo "\n";
}

// Display summary
echo "=== Summary ===\n";
echo "Created: " . count($results['created']) . " directories\n";
echo "Already existed: " . count($results['exists']) . " directories\n";
echo "Errors: " . count($results['errors']) . " directories\n\n";

if (!empty($results['created'])) {
    echo "Newly created directories:\n";
    foreach ($results['created'] as $dir) {
        echo "  - $dir\n";
    }
    echo "\n";
}

if (!empty($results['errors'])) {
    echo "Failed to create:\n";
    foreach ($results['errors'] as $dir) {
        echo "  - $dir\n";
    }
    echo "\n";
}

// Verify the structure
echo "=== Verification ===\n";
foreach ($directories as $dir) {
    $fullPath = $baseDir . $dir;
    $exists = is_dir($fullPath) ? '✓' : '✗';
    $writable = is_writable($fullPath) ? '✓' : '✗';
    $htaccess = file_exists($fullPath . '/.htaccess') ? '✓' : '✗';
    
    echo "$exists Exists | $writable Writable | $htaccess .htaccess | $dir\n";
}

echo "\n=== Directory Structure Created Successfully ===\n";
echo "All document upload directories are ready for use.\n";
?>
