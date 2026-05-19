<?php
/**
 * Fix Navigation Menu Certificate URL
 * Updates any existing navigation_menu rows that still point to the old certificate path.
 */

require_once __DIR__ . '/../config/config.php';

echo "<h2>Navigation Menu Certificate URL Fix</h2>";

try {
    $old_url = '/Nielit_Project/index.php';
    $new_url = '/Certificate/index.php';

    $check_stmt = $conn->prepare("SELECT id, label, url FROM navigation_menu WHERE url = ?");
    $check_stmt->bind_param('s', $old_url);
    $check_stmt->execute();
    $result = $check_stmt->get_result();

    if ($result->num_rows === 0) {
        echo "<div style='color: green;'>✅ No navigation menu rows need updating.</div>";
    } else {
        echo "<div style='color: orange;'>⚠️ Found " . $result->num_rows . " row(s) with the old certificate URL.</div>";

        echo "<table border='1' cellpadding='8' cellspacing='0' style='border-collapse: collapse; margin: 12px 0;'>";
        echo "<tr><th>ID</th><th>Label</th><th>Old URL</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . (int) $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['label']) . "</td>";
            echo "<td>" . htmlspecialchars($row['url']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";

        if (isset($_POST['apply_fix'])) {
            $update_stmt = $conn->prepare("UPDATE navigation_menu SET url = ? WHERE url = ?");
            $update_stmt->bind_param('ss', $new_url, $old_url);

            if ($update_stmt->execute()) {
                $affected = $conn->affected_rows;
                echo "<div style='color: green;'>✅ Updated " . (int) $affected . " row(s) to the new certificate URL.</div>";
            } else {
                echo "<div style='color: red;'>❌ Failed to update navigation menu: " . htmlspecialchars($conn->error) . "</div>";
            }
        } else {
            echo "<form method='POST'>";
            echo "<button type='submit' name='apply_fix' style='background:#28a745;color:#fff;padding:10px 18px;border:none;border-radius:5px;'>Apply URL Fix</button>";
            echo "</form>";
        }
    }
} catch (Exception $e) {
    echo "<div style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

$conn->close();
?>

<p><a href="../admin/manage_navigation.php">← Back to Navigation Menu</a></p>