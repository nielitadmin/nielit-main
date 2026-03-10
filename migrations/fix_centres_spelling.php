<?php
/**
 * Fix Training Centre Spelling in Centres Table
 * Changes "Center" to "Centre" in centre names
 */

require_once __DIR__ . '/../config/database.php';

echo "=== Training Centre Spelling Fix ===\n";
echo "Checking centres table for American spelling...\n\n";

try {
    // First, let's see what we have
    $query = "SELECT id, name, code FROM centres WHERE is_active = 1";
    $result = $conn->query($query);
    
    echo "Current centres in database:\n";
    echo "ID | Name | Code\n";
    echo "---|------|-----\n";
    
    $centres_to_fix = [];
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo $row['id'] . " | " . $row['name'] . " | " . $row['code'] . "\n";
            
            // Check if name contains "Center" (American spelling)
            if (strpos($row['name'], 'Center') !== false) {
                $centres_to_fix[] = [
                    'id' => $row['id'],
                    'old_name' => $row['name'],
                    'new_name' => str_replace('Center', 'Centre', $row['name'])
                ];
            }
        }
    }
    
    echo "\n";
    
    if (empty($centres_to_fix)) {
        echo "✅ No centres found with American spelling 'Center'. All good!\n";
    } else {
        echo "Found " . count($centres_to_fix) . " centre(s) with American spelling:\n\n";
        
        foreach ($centres_to_fix as $centre) {
            echo "ID " . $centre['id'] . ":\n";
            echo "  OLD: " . $centre['old_name'] . "\n";
            echo "  NEW: " . $centre['new_name'] . "\n\n";
        }
        
        echo "Updating centres to use British spelling...\n";
        
        $updated_count = 0;
        foreach ($centres_to_fix as $centre) {
            $stmt = $conn->prepare("UPDATE centres SET name = ? WHERE id = ?");
            $stmt->bind_param("si", $centre['new_name'], $centre['id']);
            
            if ($stmt->execute()) {
                echo "✅ Updated: " . $centre['old_name'] . " → " . $centre['new_name'] . "\n";
                $updated_count++;
            } else {
                echo "❌ Failed to update ID " . $centre['id'] . ": " . $conn->error . "\n";
            }
            $stmt->close();
        }
        
        echo "\n=== Summary ===\n";
        echo "Updated $updated_count out of " . count($centres_to_fix) . " centres.\n";
        
        // Verify the changes
        echo "\nVerifying changes...\n";
        $verify_query = "SELECT id, name, code FROM centres WHERE is_active = 1";
        $verify_result = $conn->query($verify_query);
        
        echo "\nUpdated centres in database:\n";
        echo "ID | Name | Code\n";
        echo "---|------|-----\n";
        
        if ($verify_result && $verify_result->num_rows > 0) {
            while ($row = $verify_result->fetch_assoc()) {
                echo $row['id'] . " | " . $row['name'] . " | " . $row['code'] . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Fix Complete ===\n";
?>