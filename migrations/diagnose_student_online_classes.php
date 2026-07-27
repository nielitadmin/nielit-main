<?php
/**
 * Diagnose why a student may not see an online class.
 * CLI: php migrations/diagnose_student_online_classes.php [student_id]
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/online_class_helper.php';

$isCli = (PHP_SAPI === 'cli');
$nl = $isCli ? "\n" : "<br>\n";
$studentId = trim((string) ($argv[1] ?? ($_GET['student_id'] ?? '')));

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family:monospace;padding:16px;">';
}

ensureOnlineClassesTable($conn);

echo "=== Online classes ===" . $nl;
$oc = $conn->query("SELECT id, batch_id, title, join_token, is_active, status, scheduled_at FROM online_classes ORDER BY id DESC LIMIT 10");
if ($oc) {
    while ($r = $oc->fetch_assoc()) {
        echo json_encode($r, JSON_UNESCAPED_SLASHES) . $nl;
    }
}

echo $nl . "=== Batches (sample) ===" . $nl;
$bq = $conn->query("SELECT id, batch_name, batch_code, status FROM batches ORDER BY id DESC LIMIT 15");
if ($bq) {
    while ($r = $bq->fetch_assoc()) {
        echo json_encode($r, JSON_UNESCAPED_SLASHES) . $nl;
    }
}

if ($studentId !== '') {
    echo $nl . "=== Student lookup: {$studentId} ===" . $nl;
    $stmt = $conn->prepare("SELECT id, student_id, name, batch_id, course_id, status FROM students WHERE student_id = ? ORDER BY id DESC LIMIT 20");
    $stmt->bind_param('s', $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        echo "students row: " . json_encode($r, JSON_UNESCAPED_SLASHES) . $nl;
    }
    $stmt->close();

    $ids = getStudentOnlineClassBatchIds($conn, $studentId, null);
    echo "resolved batch ids: " . json_encode($ids) . $nl;

    $classes = listOnlineClassesForBatches($conn, $ids);
    echo "visible classes: " . count($classes) . $nl;
    foreach ($classes as $c) {
        echo "  - #" . $c['id'] . " " . $c['title'] . " batch=" . $c['batch_id'] . $nl;
    }

    // batch_students links
    echo $nl . "=== batch_students for this login ===" . $nl;
    $sql = "SELECT bs.*, s.student_id, s.id AS sid FROM batch_students bs
            INNER JOIN students s ON s.id = bs.student_id
            WHERE s.student_id = ? LIMIT 50";
    $st = $conn->prepare($sql);
    if ($st) {
        $st->bind_param('s', $studentId);
        $st->execute();
        $rr = $st->get_result();
        while ($row = $rr->fetch_assoc()) {
            echo json_encode($row, JSON_UNESCAPED_SLASHES) . $nl;
        }
        $st->close();
    }

    $col = @$conn->query("SHOW COLUMNS FROM batch_students LIKE 'student_record_id'");
    if ($col && $col->num_rows > 0) {
        echo $nl . "=== batch_students via student_record_id ===" . $nl;
        $sql2 = "SELECT bs.*, s.student_id, s.id AS sid FROM batch_students bs
                 INNER JOIN students s ON s.id = bs.student_record_id
                 WHERE s.student_id = ? LIMIT 50";
        $st2 = $conn->prepare($sql2);
        if ($st2) {
            $st2->bind_param('s', $studentId);
            $st2->execute();
            $rr2 = $st2->get_result();
            while ($row = $rr2->fetch_assoc()) {
                echo json_encode($row, JSON_UNESCAPED_SLASHES) . $nl;
            }
            $st2->close();
        }
    }
} else {
    echo $nl . "Pass student_id to inspect a student." . $nl;
}

if (!$isCli) {
    echo '</pre>';
}
