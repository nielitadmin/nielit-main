<?php
/**
 * Preview / apply: approve WhatsApp-list students on NIELIT O Level 'IT' (NOL'D-2026)
 * and return the other students in that course/batch to pending.
 *
 * Default = preview only (no writes).
 * Apply: php migrations/approve_olevel_it_nold_2026_students.php apply
 *    or: open via Manage Migrations after setting $apply = true below.
 *
 * Backup the database before apply.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/multi_course_helper.php';

header('Content-Type: text/plain; charset=utf-8');

$apply = false;
if (PHP_SAPI === 'cli' && isset($argv) && in_array('apply', $argv, true)) {
    $apply = true;
}

$names = [
    'AJIT BIVAR',
    'AMIT KANHAR',
    'ANIMA DIGAL',
    'ARJUNA HUIKA',
    'BALARAM HANSDAH',
    'BARSHA NAYAK',
    'BHIMA CHARAN SOY',
    'BIKASH SETHI',
    'BIRANG BULIULI',
    'BUDHIRAM BEHERA',
    'DEEPAK KUMAR SIRKA',
    'DEEPTIMAYEE BEHERA',
    'DHANABANTA MAJHI',
    'DINESH KUMAR NAIK',
    'DULARAM BASKEY',
    'DULARI SOREN',
    'ITISHREE GAGARAI',
    'JAGAT HEMBRAM',
    'JAYANTI RAITA',
    'KUNI JARIKA',
    'LIJA NAYAK',
    'MAMITA PURTY',
    'MONALISA TUDU',
    'PANKAJ KUMAR NAIK',
    'PRIYANKA BIRUA',
    'PUJA DIGAL',
    'PUSPALATA HEMBRAM',
    'RANIMA PRADHAN',
    'RATNARAJ PRADHAN',
    'RITA MURMU',
    'RUJANTA BARGE',
    'RUPALI MURMU',
    'SABITRI KISKU',
    'SACHIN KUMAR SETHY',
    'SAHIL TUDU',
    'SAMUKA MALLICK',
    'SANDHYA RANI NAIK',
    'SANIMA PRADHAN',
    'SANJEEB KUMAR MURMU',
    'SARNA DUMNIMAI SOREN',
    'SATYABAN BHOSAGAR',
    'SHAKTI BEHERA',
    'SITA HASDAH',
    'SOUMYA RANJAN SETHI',
    'SRUSTISUDESHNA MAJHI',
    'SUBHALAXMI KANHAR',
    'SUNITA HANSDAH',
    'TAPASWINI MALLICK',
    'URMILA RAITA',
    'UTTAM KUMAR MUKHI',
];

$norm = static function ($name): string {
    $name = strtoupper(trim((string) $name));
    $name = preg_replace('/[^A-Z ]/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim((string) $name);
};

$want = [];
foreach ($names as $n) {
    $want[$norm($n)] = $n;
}

echo "=== O Level 'IT' / NOL'D-2026 approve list ===\n";
echo $apply ? "MODE: APPLY\n\n" : "MODE: PREVIEW (no writes). Pass argument 'apply' to write.\n\n";

$courses = $conn->query("SELECT id, course_code, course_name FROM courses
    WHERE course_code LIKE 'OLIT%'
       OR course_name LIKE '%O Level%IT%'
       OR course_name LIKE '%O Level ''IT''%'
       OR course_name LIKE '%NIELIT O Level%'");
echo "-- Courses --\n";
$courseIds = [];
if ($courses) {
    while ($row = $courses->fetch_assoc()) {
        $courseIds[] = (int) $row['id'];
        echo (int) $row['id'] . "\t" . $row['course_code'] . "\t" . $row['course_name'] . "\n";
    }
}

$batches = $conn->query("SELECT b.id, b.batch_code, b.batch_name, b.course_id
    FROM batches b
    WHERE REPLACE(REPLACE(IFNULL(b.batch_code,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
       OR REPLACE(REPLACE(IFNULL(b.batch_name,''), '''', ''), '’', '') LIKE '%NOLD-2026%'
       OR IFNULL(b.batch_code,'') LIKE '%NOL%2026%'
       OR IFNULL(b.batch_name,'') LIKE '%NOL%2026%'");
echo "\n-- Batches --\n";
$batchIds = [];
if ($batches) {
    while ($row = $batches->fetch_assoc()) {
        $batchIds[] = (int) $row['id'];
        echo (int) $row['id'] . "\t" . $row['batch_code'] . "\t" . $row['batch_name'] . "\t course=" . (int) $row['course_id'] . "\n";
        if ((int) $row['course_id'] > 0) {
            $courseIds[] = (int) $row['course_id'];
        }
    }
}
$courseIds = array_values(array_unique(array_filter($courseIds)));
$batchIds = array_values(array_unique(array_filter($batchIds)));

if ($courseIds === [] && $batchIds === []) {
    echo "\nERROR: Could not find O Level 'IT' course or NOL'D-2026 batch.\n";
    exit(1);
}

$where = ['LOWER(IFNULL(s.status,\'\')) NOT IN (\'rejected\', \'inactive\')'];
$or = [];
if ($courseIds !== []) {
    $or[] = 's.course_id IN (' . implode(',', $courseIds) . ')';
    $or[] = 'se.course_id IN (' . implode(',', $courseIds) . ')';
}
if ($batchIds !== []) {
    $or[] = 's.batch_id IN (' . implode(',', $batchIds) . ')';
    $or[] = 'bs.batch_id IN (' . implode(',', $batchIds) . ')';
}
$where[] = '(' . implode(' OR ', $or) . ')';

$sql = "SELECT DISTINCT s.id, s.student_id, s.name, s.status, s.course_id, s.batch_id
        FROM students s
        LEFT JOIN batch_students bs ON bs.student_id = s.student_id
        LEFT JOIN student_enrollments se ON se.student_record_id = s.id
        WHERE " . implode(' AND ', $where);
$res = $conn->query($sql);
if (!$res) {
    echo 'ERROR: ' . $conn->error . "\n";
    exit(1);
}

$pool = [];
while ($row = $res->fetch_assoc()) {
    $key = $norm($row['name']);
    $pool[] = [
        'id' => (int) $row['id'],
        'student_id' => (string) $row['student_id'],
        'name' => (string) $row['name'],
        'status' => (string) $row['status'],
        'norm' => $key,
        'on_list' => isset($want[$key]),
    ];
}

$matched = [];
$extras = [];
$seenNorm = [];
foreach ($pool as $p) {
    if ($p['on_list']) {
        $matched[] = $p;
        $seenNorm[$p['norm']] = true;
    } else {
        $extras[] = $p;
    }
}
$missing = [];
foreach ($want as $k => $label) {
    if (empty($seenNorm[$k])) {
        $missing[] = $label;
    }
}

echo "\nPool students: " . count($pool) . " (you said ~55)\n";
echo "WhatsApp names: " . count($want) . "\n";
echo "Matched (approve): " . count($matched) . "\n";
echo "Not on list (de-approve if active): " . count($extras) . "\n";
echo "List names with no DB match: " . count($missing) . "\n";

echo "\n-- APPROVE --\n";
foreach ($matched as $p) {
    echo $p['student_id'] . "\t" . $p['status'] . "\t" . $p['name'] . "\n";
}
echo "\n-- DE-APPROVE (extras) --\n";
foreach ($extras as $p) {
    echo $p['student_id'] . "\t" . $p['status'] . "\t" . $p['name'] . "\n";
}
echo "\n-- UNMATCHED LIST NAMES (fix spelling then re-run) --\n";
foreach ($missing as $label) {
    echo $label . "\n";
}

if (!$apply) {
    echo "\nPreview only. To apply: php migrations/approve_olevel_it_nold_2026_students.php apply\n";
    exit(0);
}

$adminName = 'migration:olevel-nold-2026';
$okApprove = 0;
$okPending = 0;
$errors = 0;

foreach ($matched as $p) {
    $r = adminApproveStudent($conn, $p['student_id'], $adminName);
    if (!empty($r['success'])) {
        $okApprove++;
    } else {
        $errors++;
        echo 'APPROVE FAIL ' . $p['student_id'] . ' ' . ($r['message'] ?? '') . "\n";
    }
}
foreach ($extras as $p) {
    $st = strtolower(trim($p['status']));
    if (!in_array($st, ['active', 'approved'], true)) {
        continue;
    }
    $r = adminDeapproveStudent($conn, $p['student_id'], $adminName);
    if (!empty($r['success'])) {
        $okPending++;
    } else {
        $errors++;
        echo 'DEAPPROVE FAIL ' . $p['student_id'] . ' ' . ($r['message'] ?? '') . "\n";
    }
}

echo "\nApplied. Approved: {$okApprove}. De-approved: {$okPending}. Errors: {$errors}.\n";
exit($errors > 0 ? 1 : 0);
