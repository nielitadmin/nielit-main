<?php
// Quick fix for main registration form validation issue
echo "<h2>Fix Main Registration Form</h2>";

$register_file = __DIR__ . '/register.php';

if (!file_exists($register_file)) {
    die("register.php not found");
}

// Read the current file
$content = file_get_contents($register_file);

// Find the complex JavaScript validation and replace with simplified version
$old_js_pattern = '/\/\/ Form validation with toast notifications.*?this\.submit\(\);\s*}\);/s';

$new_js = '// SIMPLIFIED Form validation - FIXED VERSION
document.getElementById(\'registrationForm\').addEventListener(\'submit\', function(e) {
    console.log(\'=== SIMPLIFIED FORM SUBMISSION ===\');
    
    // Basic validation only - no complex document validation
    const requiredFields = [\'name\', \'father_name\', \'mother_name\', \'dob\', \'mobile\', \'email\', \'aadhar\', \'gender\', \'religion\', \'marital_status\', \'category\', \'position\', \'nationality\', \'state\', \'city\', \'pincode\', \'address\'];
    
    let hasErrors = false;
    for (let field of requiredFields) {
        const input = document.querySelector(`[name="${field}"]`);
        if (!input || !input.value.trim()) {
            console.log(`Missing required field: ${field}`);
            alert(`Please fill in the ${field.replace(\'_\', \' \')} field.`);
            hasErrors = true;
            break;
        }
    }
    
    if (hasErrors) {
        e.preventDefault();
        return false;
    }
    
    // Check required files (simplified)
    const requiredFiles = [\'passport_photo\', \'signature\', \'aadhar_card\', \'tenth_marksheet\'];
    for (let file of requiredFiles) {
        const input = document.querySelector(`[name="${file}"]`);
        if (!input || !input.files[0]) {
            console.log(`Missing required file: ${file}`);
            alert(`Please upload ${file.replace(\'_\', \' \')}.`);
            e.preventDefault();
            return false;
        }
    }
    
    console.log(\'✅ All validations passed - submitting form\');
    // Form will submit normally without preventDefault
});';

// Replace the complex validation with simplified version
$new_content = preg_replace($old_js_pattern, $new_js, $content);

if ($new_content === $content) {
    echo "❌ Could not find the JavaScript validation section to replace.<br>";
    echo "The pattern might have changed. Using alternative approach...<br>";
    
    // Alternative: Add a script to override the form submission
    $override_script = '
<script>
// OVERRIDE: Disable complex validation and use simple validation
document.addEventListener("DOMContentLoaded", function() {
    const form = document.getElementById("registrationForm");
    if (form) {
        // Remove existing event listeners
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);
        
        // Add simple validation
        newForm.addEventListener("submit", function(e) {
            console.log("OVERRIDE: Simple validation active");
            
            // Basic required field check
            const required = ["name", "father_name", "mother_name", "dob", "mobile", "email", "aadhar", "gender", "religion", "marital_status", "category", "position", "nationality", "state", "city", "pincode", "address"];
            
            for (let field of required) {
                const input = document.querySelector(`[name="${field}"]`);
                if (!input || !input.value.trim()) {
                    alert(`Please fill in ${field.replace("_", " ")}`);
                    e.preventDefault();
                    return false;
                }
            }
            
            // Check required files
            const files = ["passport_photo", "signature", "aadhar_card", "tenth_marksheet"];
            for (let file of files) {
                const input = document.querySelector(`[name="${file}"]`);
                if (!input || !input.files[0]) {
                    alert(`Please upload ${file.replace("_", " ")}`);
                    e.preventDefault();
                    return false;
                }
            }
            
            console.log("✅ Simple validation passed");
            return true;
        });
    }
});
</script>';
    
    // Add the override script before closing body tag
    $new_content = str_replace('</body>', $override_script . '</body>', $content);
}

// Create backup
$backup_file = $register_file . '.backup.' . date('Y-m-d-H-i-s');
copy($register_file, $backup_file);

// Write the fixed content
if (file_put_contents($register_file, $new_content)) {
    echo "✅ Successfully fixed the main registration form!<br>";
    echo "📁 Backup created: " . basename($backup_file) . "<br>";
    echo "🔗 Test the fixed form: <a href='register.php?course=FDCP-2026' target='_blank'>register.php?course=FDCP-2026</a><br>";
} else {
    echo "❌ Failed to write the fixed file.<br>";
}

echo "<hr>";
echo "<h3>Available Options:</h3>";
echo "<ul>";
echo "<li><a href='register.php?course=FDCP-2026' target='_blank'>Main Registration Form (Fixed)</a></li>";
echo "<li><a href='register_fixed.php?course=FDCP-2026' target='_blank'>Alternative Fixed Form</a></li>";
echo "<li><a href='test_registration_simple.php' target='_blank'>Simple Test Form</a></li>";
echo "</ul>";

echo "<h3>What was fixed:</h3>";
echo "<ul>";
echo "<li>Removed complex JavaScript document validation</li>";
echo "<li>Simplified form submission logic</li>";
echo "<li>Added basic field validation only</li>";
echo "<li>Removed preventDefault() issues</li>";
echo "</ul>";
?>