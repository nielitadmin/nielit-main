<?php
/**
 * Interactive Monthly Git Report Viewer with Date Filters
 * 
 * Web interface to view and generate git activity reports
 * with month/year filters
 */

// Get parameters from URL or use current month
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$action = isset($_GET['action']) ? $_GET['action'] : 'view';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$perPage = 50; // Show 50 commits per page

// AJAX endpoint to get diff for a specific commit
if ($action === 'get_diff' && isset($_GET['hash'])) {
    header('Content-Type: application/json');
    
    $hash = $_GET['hash'];
    // Validate hash format (40 character hexadecimal)
    if (!preg_match('/^[a-f0-9]{40}$/i', $hash)) {
        echo json_encode(['success' => false, 'error' => 'Invalid commit hash']);
        exit;
    }
    
    // Change to repository directory
    $repoPath = dirname(__DIR__);
    chdir($repoPath);
    
    // Get the diff for this commit
    $diffCmd = sprintf('git show --pretty=format:"" %s 2>&1', escapeshellarg($hash));
    exec($diffCmd, $diffOutput, $returnCode);
    
    if ($returnCode === 0) {
        // Remove empty first line if present
        if (!empty($diffOutput) && trim($diffOutput[0]) === '') {
            array_shift($diffOutput);
        }
        
        echo json_encode([
            'success' => true,
            'diff' => $diffOutput,
            'hash' => $hash
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Failed to retrieve commit diff',
            'output' => implode("\n", $diffOutput)
        ]);
    }
    exit;
}

// Validate inputs
if ($month < 1 || $month > 12) $month = date('n');
if ($year < 2020 || $year > 2030) $year = date('Y');

$monthName = date('F', mktime(0, 0, 0, $month, 1, $year));
$startDate = sprintf("%04d-%02d-01", $year, $month);
$endDate = date('Y-m-d', strtotime("$startDate +1 month"));

// Change to repository directory
$repoPath = dirname(__DIR__);
chdir($repoPath);

// If action is generate, create the report
if ($action === 'generate') {
    // Increase PHP limits for large reports
    set_time_limit(300); // 5 minutes
    ini_set('memory_limit', '512M');
    
    // Get all commits in the date range
    $gitLogCmd = sprintf(
        'git log --all --since="%s" --until="%s" --pretty=format:"%%H|%%an|%%ae|%%ad|%%s" --date=format:"%%Y-%%m-%%d %%H:%%M:%%S" 2>&1',
        $startDate,
        $endDate
    );
    
    exec($gitLogCmd, $commits, $returnCode);
    
    if ($returnCode === 0 && !empty($commits)) {
        // Parse commits with pagination
        $commitData = [];
        $totalCommitsCount = count($commits);
        $totalPages = ceil($totalCommitsCount / $perPage);
        $page = min($page, $totalPages); // Ensure page doesn't exceed total pages
        
        $startIndex = ($page - 1) * $perPage;
        $endIndex = min($startIndex + $perPage, $totalCommitsCount);
        
        for ($i = $startIndex; $i < $endIndex; $i++) {
            $commitLine = $commits[$i];
            if (empty(trim($commitLine))) continue;
            
            $parts = explode('|', $commitLine);
            if (count($parts) >= 5) {
                $hash = $parts[0];
                $author = $parts[1];
                $email = $parts[2];
                $date = $parts[3];
                $message = implode('|', array_slice($parts, 4));
                
                // Get files changed with additions/deletions (faster, no full diff)
                $diffStatCmd = sprintf('git diff --numstat %s^! 2>&1', escapeshellarg($hash));
                exec($diffStatCmd, $diffStats);
                
                // Don't load full diff by default - too slow
                // Users can expand individual commits if needed
                
                $commitData[] = [
                    'hash' => $hash,
                    'short_hash' => substr($hash, 0, 7),
                    'author' => $author,
                    'email' => $email,
                    'date' => $date,
                    'message' => $message,
                    'diff_stats' => $diffStats,
                    'diff' => [] // Load on demand via AJAX later
                ];
                
                unset($diffStats);
            }
        }
        
        // Calculate statistics
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
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Git Activity Reports - <?php echo $monthName; ?> <?php echo $year; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .filter-bar {
            background: white;
            border-radius: 10px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .filter-bar h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .filter-controls {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: end;
        }
        
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: #666;
            font-weight: 500;
            font-size: 0.9em;
        }
        
        .filter-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 1em;
            background: white;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }
        
        .filter-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #28a745;
            color: white;
            margin-left: 10px;
        }
        
        .btn-secondary:hover {
            background: #218838;
            transform: translateY(-2px);
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
        
        .additions { color: #28a745 !important; }
        .deletions { color: #dc3545 !important; }
        
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
        
        .files-changed h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1em;
        }
        
        .file-list {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 20px;
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
        
        .code-diff h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 1.1em;
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
        
        .diff-content {
            background: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            overflow-x: auto;
            max-height: 400px;
            overflow-y: auto;
            margin-top: 15px;
        }
        
        .diff-line {
            font-family: 'Courier New', Consolas, monospace;
            font-size: 0.85em;
            line-height: 1.5;
            white-space: pre-wrap;
            word-break: break-all;
            padding: 2px 8px;
            border-left: 3px solid transparent;
        }
        
        .diff-add {
            background: #e6ffed;
            border-left-color: #28a745;
            color: #22863a;
        }
        
        .diff-remove {
            background: #ffeef0;
            border-left-color: #d73a49;
            color: #b31d28;
        }
        
        .diff-meta {
            background: #f1f8ff;
            color: #0366d6;
            font-weight: bold;
            border-left-color: #0366d6;
        }
        
        .btn-view-changes {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-view-changes:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-view-changes:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            color: #667eea;
            font-style: italic;
        }
        
        .error {
            background: #ffeef0;
            color: #d73a49;
            padding: 15px;
            border-radius: 6px;
            border-left: 4px solid #d73a49;
            margin-top: 10px;
        }
        
        .no-commits {
            text-align: center;
            padding: 60px 40px;
            color: #666;
        }
        
        .no-commits h2 {
            color: #333;
            margin-bottom: 15px;
            border: none;
        }
        
        .no-commits p {
            font-size: 1.1em;
            margin-bottom: 25px;
        }
        
        .footer {
            background: #333;
            color: white;
            padding: 20px;
            text-align: center;
            font-size: 0.9em;
        }
        
        .pagination {
            background: #f8f9fa;
            padding: 20px;
            margin-top: 30px;
            border-top: 2px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .pagination-info {
            color: #666;
            font-size: 0.95em;
            font-weight: 500;
        }
        
        .pagination-buttons {
            display: flex;
            gap: 10px;
        }
        
        .pagination-buttons .btn {
            padding: 10px 20px;
            font-size: 0.9em;
        }
        
        .action-buttons {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        @media print {
            .filter-bar {
                display: none;
            }
            
            .toggle-diff {
                display: none;
            }
            
            .diff-content {
                max-height: none;
            }
            
            .btn {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .header h1 {
                font-size: 1.8em;
            }
            
            .summary {
                grid-template-columns: 1fr;
            }
            
            .filter-controls {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <!-- Filter Bar -->
    <div class="filter-bar">
        <h2>📊 Git Activity Reports</h2>
        <form method="GET" action="">
            <input type="hidden" name="action" value="generate">
            <div class="filter-controls">
                <div class="filter-group">
                    <label for="month">Select Month</label>
                    <select name="month" id="month">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?php echo $m; ?>" <?php echo $m == $month ? 'selected' : ''; ?>>
                                <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="year">Select Year</label>
                    <select name="year" id="year">
                        <?php for ($y = 2020; $y <= 2030; $y++): ?>
                            <option value="<?php echo $y; ?>" <?php echo $y == $year ? 'selected' : ''; ?>>
                                <?php echo $y; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <button type="submit" class="btn btn-primary">🔍 Generate Report</button>
                    <button type="button" class="btn btn-secondary" onclick="window.print()">🖨️ Print</button>
                </div>
            </div>
        </form>
    </div>
    
    <!-- Report Container -->
    <div class="container">
        <div class="header">
            <h1>📊 Git Activity Report</h1>
            <p><?php echo $monthName; ?> <?php echo $year; ?></p>
        </div>
        
        <?php if ($action === 'generate' && isset($commitData) && !empty($commitData)): ?>
        
        <!-- Summary Section -->
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
        
        <!-- Commits Section -->
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
                    
                    <!-- Code Changes - Load on Demand -->
                    <div class="code-diff">
                        <h4>🔍 Code Changes</h4>
                        <button class="btn-view-changes" onclick="loadDiff('<?php echo $commit['hash']; ?>', <?php echo $index; ?>)">
                            View Code Changes
                        </button>
                        <div class="diff-content" id="diff-<?php echo $index; ?>" style="display: none;">
                            <div class="loading">Loading changes...</div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination Controls -->
        <?php if (isset($totalCommitsCount) && $totalCommitsCount > $perPage): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?php echo $startIndex + 1; ?> - <?php echo min($endIndex, $totalCommitsCount); ?> 
                of <?php echo number_format($totalCommitsCount); ?> commits 
                (Page <?php echo $page; ?> of <?php echo $totalPages; ?>)
            </div>
            <div class="pagination-buttons">
                <?php if ($page > 1): ?>
                <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&action=generate&page=<?php echo $page - 1; ?>" class="btn btn-secondary">
                    ← Previous
                </a>
                <?php endif; ?>
                
                <?php if ($page < $totalPages): ?>
                <a href="?month=<?php echo $month; ?>&year=<?php echo $year; ?>&action=generate&page=<?php echo $page + 1; ?>" class="btn btn-secondary">
                    Next →
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <?php elseif ($action === 'generate'): ?>
        
        <!-- No Commits Found -->
        <div class="no-commits">
            <h2>📭 No Commits Found</h2>
            <p>There are no commits for <?php echo $monthName; ?> <?php echo $year; ?></p>
            <p>Try selecting a different month or year above.</p>
        </div>
        
        <?php else: ?>
        
        <!-- Initial State -->
        <div class="no-commits">
            <h2>👋 Welcome to Git Activity Reports</h2>
            <p>Select a month and year above, then click "Generate Report" to view your Git activity.</p>
            <div class="action-buttons">
                <form method="GET" action="" style="display: inline;">
                    <input type="hidden" name="action" value="generate">
                    <input type="hidden" name="month" value="<?php echo date('n'); ?>">
                    <input type="hidden" name="year" value="<?php echo date('Y'); ?>">
                    <button type="submit" class="btn btn-primary">📊 View Current Month</button>
                </form>
            </div>
        </div>
        
        <?php endif; ?>
        
        <div class="footer">
            <p>Report generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>Repository: <?php echo htmlspecialchars($repoPath); ?></p>
        </div>
    </div>
    
    <script>
        function loadDiff(commitHash, index) {
            const diffElement = document.getElementById('diff-' + index);
            const button = event.target;
            
            // If already loaded, just toggle
            if (diffElement.dataset.loaded === 'true') {
                if (diffElement.style.display === 'none') {
                    diffElement.style.display = 'block';
                    button.textContent = 'Hide Code Changes';
                } else {
                    diffElement.style.display = 'none';
                    button.textContent = 'View Code Changes';
                }
                return;
            }
            
            // Show loading state
            diffElement.style.display = 'block';
            diffElement.innerHTML = '<div class="loading">⏳ Loading changes...</div>';
            button.textContent = 'Loading...';
            button.disabled = true;
            
            // Fetch diff via AJAX
            fetch('?action=get_diff&hash=' + encodeURIComponent(commitHash))
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.diff) {
                        let diffHtml = '';
                        data.diff.forEach(line => {
                            let cssClass = 'diff-line';
                            if (line.startsWith('+') && !line.startsWith('+++')) {
                                cssClass += ' diff-add';
                            } else if (line.startsWith('-') && !line.startsWith('---')) {
                                cssClass += ' diff-remove';
                            } else if (line.startsWith('@@')) {
                                cssClass += ' diff-meta';
                            }
                            
                            diffHtml += '<div class="' + cssClass + '">' + 
                                escapeHtml(line) + '</div>';
                        });
                        
                        diffElement.innerHTML = diffHtml;
                        diffElement.dataset.loaded = 'true';
                        button.textContent = 'Hide Code Changes';
                        button.disabled = false;
                    } else {
                        diffElement.innerHTML = '<div class="error">Failed to load changes: ' + 
                            (data.error || 'Unknown error') + '</div>';
                        button.textContent = 'Retry';
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    diffElement.innerHTML = '<div class="error">Error loading changes: ' + 
                        error.message + '</div>';
                    button.textContent = 'Retry';
                    button.disabled = false;
                });
        }
        
        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    </script>
</body>
</html>
