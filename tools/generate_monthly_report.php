<?php
/**
 * Monthly Git Activity Report Generator
 * 
 * Generates a comprehensive HTML report showing:
 * - All commits made in a given month
 * - Files changed in each commit
 * - Code additions/deletions statistics
 * - Visual diff for each commit
 * 
 * Usage: php generate_monthly_report.php [month] [year]
 * Example: php generate_monthly_report.php 1 2025
 * If no parameters provided, generates report for current month
 */

// Parse command line arguments or use current month
$month = isset($argv[1]) ? (int)$argv[1] : date('n');
$year = isset($argv[2]) ? (int)$argv[2] : date('Y');

// Validate inputs
if ($month < 1 || $month > 12) {
    die("Error: Month must be between 1 and 12\n");
}

if ($year < 2020 || $year > 2030) {
    die("Error: Year must be between 2020 and 2030\n");
}

$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
$startDate = sprintf("%04d-%02d-01", $year, $month);
$endDate = date('Y-m-d', strtotime("$startDate +1 month"));

echo "Generating report for $monthName $year...\n";
echo "Date range: $startDate to $endDate\n\n";

// Change to repository directory
$repoPath = dirname(__DIR__);
chdir($repoPath);

// Check if we're in a git repository
exec("git rev-parse --is-inside-work-tree 2>&1", $gitCheck, $returnCode);
if ($returnCode !== 0) {
    die("Error: Not a git repository or git is not installed\n");
}

// Get all commits in the date range
$gitLogCmd = sprintf(
    'git log --all --since="%s" --until="%s" --pretty=format:"%%H|%%an|%%ae|%%ad|%%s" --date=format:"%%Y-%%m-%%d %%H:%%M:%%S" 2>&1',
    $startDate,
    $endDate
);

exec($gitLogCmd, $commits, $returnCode);

if ($returnCode !== 0) {
    die("Error executing git log command\n");
}

if (empty($commits)) {
    echo "No commits found in $monthName $year\n";
    exit(0);
}

echo "Found " . count($commits) . " commits\n";

// Parse commits
$commitData = [];
foreach ($commits as $commitLine) {
    if (empty(trim($commitLine))) continue;
    
    $parts = explode('|', $commitLine);
    if (count($parts) >= 5) {
        $hash = $parts[0];
        $author = $parts[1];
        $email = $parts[2];
        $date = $parts[3];
        $message = implode('|', array_slice($parts, 4)); // Rejoin message in case it contains |
        
        // Get file statistics for this commit
        $statsCmd = sprintf('git show --stat --format="" %s 2>&1', escapeshellarg($hash));
        exec($statsCmd, $stats);
        
        // Get files changed with additions/deletions
        $diffStatCmd = sprintf('git diff --numstat %s^! 2>&1', escapeshellarg($hash));
        exec($diffStatCmd, $diffStats);
        
        // Get short diff
        $diffCmd = sprintf('git show --pretty=format:"" --no-color %s 2>&1', escapeshellarg($hash));
        exec($diffCmd, $diffOutput);
        
        $commitData[] = [
            'hash' => $hash,
            'short_hash' => substr($hash, 0, 7),
            'author' => $author,
            'email' => $email,
            'date' => $date,
            'message' => $message,
            'stats' => $stats,
            'diff_stats' => $diffStats,
            'diff' => $diffOutput
        ];
        
        // Clear arrays for next iteration
        unset($stats, $diffStats, $diffOutput);
    }
}

// Calculate total statistics
$totalCommits = count($commitData);
$totalFiles = 0;
$totalAdditions = 0;
$totalDeletions = 0;
$filesChanged = [];

foreach ($commitData as $commit) {
    foreach ($commit['diff_stats'] as $stat) {
        $parts = preg_split('/\s+/', trim($stat));
        if (count($parts) >= 3) {
            $additions = is_numeric($parts[0]) ? (int)$parts[0] : 0;
            $deletions = is_numeric($parts[1]) ? (int)$parts[1] : 0;
            $filename = $parts[2];
            
            $totalAdditions += $additions;
            $totalDeletions += $deletions;
            
            if (!in_array($filename, $filesChanged)) {
                $filesChanged[] = $filename;
                $totalFiles++;
            }
        }
    }
}

// Generate HTML report
$reportFilename = sprintf("monthly_report_%s_%d.html", strtolower($monthName), $year);
$reportPath = __DIR__ . '/' . $reportFilename;

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Activity Report - <?php echo $monthName; ?> <?php echo $year; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            padding: 40px;
            background: #f8f9fa;
        }
        
        .summary-card {
            background: white;
            padding: 25px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .summary-card:hover {
            transform: translateY(-5px);
        }
        
        .summary-card h3 {
            color: #667eea;
            font-size: 2.5em;
            margin-bottom: 10px;
        }
        
        .summary-card p {
            color: #666;
            font-size: 0.9em;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .commits-section {
            padding: 40px;
        }
        
        .commits-section h2 {
            color: #333;
            margin-bottom: 30px;
            font-size: 2em;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        
        .commit-card {
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 30px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        
        .commit-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
        }
        
        .commit-header h3 {
            font-size: 1.3em;
            margin-bottom: 10px;
        }
        
        .commit-meta {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
            font-size: 0.9em;
            opacity: 0.9;
        }
        
        .commit-hash {
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 4px;
            font-family: monospace;
        }
        
        .commit-body {
            padding: 20px;
        }
        
        .files-changed {
            margin-bottom: 20px;
        }
        
        .files-changed h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        
        .file-list {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
        }
        
        .file-item {
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
            font-family: monospace;
            font-size: 0.9em;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-stats {
            display: inline-block;
            margin-left: 10px;
            font-size: 0.85em;
        }
        
        .additions {
            color: #28a745;
            font-weight: bold;
        }
        
        .deletions {
            color: #dc3545;
            font-weight: bold;
        }
        
        .code-diff {
            margin-top: 20px;
        }
        
        .code-diff h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        
        .diff-content {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
        }
        
        .diff-line {
            font-family: monospace;
            font-size: 0.85em;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-all;
        }
        
        .toggle-diff {
            background: #667eea;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9em;
            transition: background 0.3s ease;
        }
        
        .toggle-diff:hover {
            background: #764ba2;
        }
        
        .footer {
            background: #333;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 0.9em;
        }
        
        .print-button {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: #667eea;
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 50px;
            cursor: pointer;
            font-size: 1em;
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            z-index: 1000;
        }
        
        .print-button:hover {
            background: #764ba2;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.6);
        }
        
        @media print {
            body {
                background: white;
                padding: 0;
            }
            
            .print-button {
                display: none;
            }
            
            .toggle-diff {
                display: none;
            }
            
            .diff-content {
                max-height: none;
            }
            
            .commit-card {
                page-break-inside: avoid;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            
            .summary {
                grid-template-columns: 1fr;
            }
            
            .commits-section {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Git Activity Report</h1>
            <p><?php echo $monthName; ?> <?php echo $year; ?></p>
        </div>
        
        <div class="summary">
            <div class="summary-card">
                <h3><?php echo $totalCommits; ?></h3>
                <p>Total Commits</p>
            </div>
            <div class="summary-card">
                <h3><?php echo $totalFiles; ?></h3>
                <p>Files Changed</p>
            </div>
            <div class="summary-card">
                <h3 class="additions">+<?php echo number_format($totalAdditions); ?></h3>
                <p>Lines Added</p>
            </div>
            <div class="summary-card">
                <h3 class="deletions">-<?php echo number_format($totalDeletions); ?></h3>
                <p>Lines Deleted</p>
            </div>
        </div>
        
        <div class="commits-section">
            <h2>📝 Commit Details</h2>
            
            <?php foreach ($commitData as $index => $commit): ?>
            <div class="commit-card">
                <div class="commit-header">
                    <h3><?php echo htmlspecialchars($commit['message']); ?></h3>
                    <div class="commit-meta">
                        <span>👤 <?php echo htmlspecialchars($commit['author']); ?></span>
                        <span>📅 <?php echo $commit['date']; ?></span>
                        <span class="commit-hash"><?php echo $commit['short_hash']; ?></span>
                    </div>
                </div>
                
                <div class="commit-body">
                    <?php if (!empty($commit['diff_stats'])): ?>
                    <div class="files-changed">
                        <h4>📁 Files Changed (<?php echo count($commit['diff_stats']); ?>)</h4>
                        <div class="file-list">
                            <?php foreach ($commit['diff_stats'] as $stat): ?>
                                <?php
                                $parts = preg_split('/\s+/', trim($stat));
                                if (count($parts) >= 3):
                                    $additions = is_numeric($parts[0]) ? (int)$parts[0] : 0;
                                    $deletions = is_numeric($parts[1]) ? (int)$parts[1] : 0;
                                    $filename = $parts[2];
                                ?>
                                <div class="file-item">
                                    <strong><?php echo htmlspecialchars($filename); ?></strong>
                                    <span class="file-stats">
                                        <span class="additions">+<?php echo $additions; ?></span>
                                        <span class="deletions">-<?php echo $deletions; ?></span>
                                    </span>
                                </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($commit['diff'])): ?>
                    <div class="code-diff">
                        <h4>🔍 Code Changes</h4>
                        <button class="toggle-diff" onclick="toggleDiff(<?php echo $index; ?>)">Show Diff</button>
                        <div class="diff-content" id="diff-<?php echo $index; ?>" style="display: none;">
                            <?php foreach ($commit['diff'] as $line): ?>
                                <div class="diff-line"><?php echo htmlspecialchars($line); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="footer">
            <p>Report generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>Repository: <?php echo htmlspecialchars($repoPath); ?></p>
        </div>
    </div>
    
    <button class="print-button" onclick="window.print()">🖨️ Print Report</button>
    
    <script>
        function toggleDiff(index) {
            const diffElement = document.getElementById('diff-' + index);
            const button = event.target;
            
            if (diffElement.style.display === 'none') {
                diffElement.style.display = 'block';
                button.textContent = 'Hide Diff';
            } else {
                diffElement.style.display = 'none';
                button.textContent = 'Show Diff';
            }
        }
    </script>
</body>
</html>
<?php

$htmlContent = ob_get_clean();
file_put_contents($reportPath, $htmlContent);

echo "\n✅ Report generated successfully!\n";
echo "📄 File: $reportPath\n";
echo "🌐 Open in browser: file://$reportPath\n";
echo "\nYou can also print this report directly from the browser or save as PDF!\n";
?>
