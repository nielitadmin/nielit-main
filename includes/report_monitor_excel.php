<?php
/**
 * Multi-sheet Excel export for Report Monitor (SpreadsheetML .xls).
 * Opens in Excel and includes the course-level rows hidden behind "Show Details".
 */

function report_monitor_excel_sheet_name(string $name, array &$used): string
{
    $name = preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $name);
    $name = trim(preg_replace('/\s+/', ' ', (string) $name));
    if ($name === '') {
        $name = 'Sheet';
    }
    $base = function_exists('mb_substr') ? mb_substr($name, 0, 31) : substr($name, 0, 31);
    $candidate = $base;
    $n = 2;
    while (isset($used[$candidate])) {
        $suffix = ' ' . $n;
        $trim = 31 - strlen($suffix);
        $candidate = (function_exists('mb_substr') ? mb_substr($base, 0, $trim) : substr($base, 0, $trim)) . $suffix;
        $n++;
    }
    $used[$candidate] = true;
    return $candidate;
}

function report_monitor_excel_cell($value): string
{
    if (is_int($value) || (is_float($value) && is_finite($value))) {
        return '<Cell><Data ss:Type="Number">' . $value . '</Data></Cell>';
    }
    $text = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    return '<Cell><Data ss:Type="String">' . $text . '</Data></Cell>';
}

function report_monitor_excel_row(array $cells, string $style = ''): string
{
    $styleAttr = $style !== '' ? ' ss:StyleID="' . $style . '"' : '';
    $out = '<Row>';
    foreach ($cells as $cell) {
        if ($style !== '' && is_string($cell) && strpos($cell, '<Cell>') === 0) {
            $cell = str_replace('<Cell>', '<Cell' . $styleAttr . '>', $cell);
        } elseif ($style !== '') {
            $cell = str_replace('<Cell>', '<Cell' . $styleAttr . '>', report_monitor_excel_cell($cell));
        } else {
            $cell = is_string($cell) && strpos($cell, '<Cell>') === 0 ? $cell : report_monitor_excel_cell($cell);
        }
        $out .= $cell;
    }
    return $out . '</Row>';
}

function report_monitor_excel_worksheet(string $name, array $rows): string
{
    $xml = '<Worksheet ss:Name="' . htmlspecialchars($name, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"><Table>';
    foreach ($rows as $row) {
        $style = '';
        if (isset($row['_style'])) {
            $style = (string) $row['_style'];
            unset($row['_style']);
        }
        $cells = array_values($row);
        if ($cells === []) {
            $xml .= '<Row/>';
            continue;
        }
        $xml .= report_monitor_excel_row($cells, $style);
    }
    return $xml . '</Table></Worksheet>';
}

function report_monitor_excel_achievement($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, 1) . '%';
}

function report_monitor_excel_target($value): string
{
    $target = (int) $value;
    return $target > 0 ? (string) $target : 'Not set';
}

function report_monitor_send_excel(array $ctx): void
{
    $scope = (string) ($ctx['scope'] ?? 'Report');
    $fyLabel = (string) ($ctx['fy_label'] ?? '');
    $period = (string) ($ctx['period'] ?? '');
    $centre = (string) ($ctx['centre'] ?? '');
    $generated = date('d M Y, h:i A');

    $usedNames = [];
    $sheets = [];

    $overview = [
        ['_style' => 'Title', 'NIELIT Report Monitor'],
        ['Centre', $centre],
        ['Financial year', $fyLabel],
        ['Period', $period],
        ['Generated', $generated],
        [],
        ['_style' => 'Header', 'Metric', 'Value'],
        ['Total enrolled students', (int) ($ctx['kpi_registered'] ?? 0)],
        ['Admissions (students in batch)', (int) ($ctx['kpi_admission'] ?? 0)],
        ['Active batches', (int) ($ctx['kpi_batches'] ?? 0)],
        ['Completed batches', (int) ($ctx['kpi_completed'] ?? 0)],
        ['Total courses', (int) ($ctx['overall']['total_courses'] ?? 0)],
        ['Active courses', (int) ($ctx['overall']['active_courses'] ?? 0)],
        ['Pending applications', (int) ($ctx['overall']['pending_applications'] ?? 0)],
        ['Approved students', (int) ($ctx['overall']['approved_students'] ?? 0)],
    ];
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Overview', $usedNames),
        $overview
    );

    $courseRows = [
        ['_style' => 'Title', 'Course Wise Summary', $centre, $period],
        [],
        ['_style' => 'Header', 'Sl No.', 'Course', 'Code', 'Applied', 'Admission', 'Pending', 'Approved', 'Batches'],
    ];
    $sl = 1;
    foreach (($ctx['courses'] ?? []) as $course) {
        $courseRows[] = [
            $sl++,
            (string) ($course['course_name'] ?? ''),
            (string) ($course['course_code'] ?? ''),
            (int) ($course['applications'] ?? 0),
            (int) ($course['batch_enrolled'] ?? 0),
            (int) ($course['pending'] ?? 0),
            (int) ($course['approved'] ?? 0),
            (int) ($course['batch_count'] ?? 0),
        ];
    }
    if ($sl === 1) {
        $courseRows[] = ['No course records for this period.'];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Courses', $usedNames),
        $courseRows
    );

    $studentRows = [
        ['_style' => 'Title', 'All student records', $centre, $period],
        ['Every active application in this period. Contact details are not included.'],
        [],
        ['_style' => 'Header', 'Sl No.', 'Student ID', 'Name', 'Course', 'Code', 'Centre', 'Status', 'Applied On', 'Batch', 'Batch Code', 'Gender', 'Category'],
    ];
    $sl = 1;
    foreach (($ctx['students'] ?? []) as $student) {
        $studentRows[] = [
            $sl++,
            (string) ($student['student_id'] ?? ''),
            (string) ($student['name'] ?? ''),
            (string) ($student['course_name'] ?? ''),
            (string) ($student['course_code'] ?? ''),
            (string) ($student['centre_name'] ?? ''),
            (string) ($student['status'] ?? ''),
            (string) ($student['applied_on'] ?? ''),
            (string) ($student['batch_name'] ?? ''),
            (string) ($student['batch_code'] ?? ''),
            (string) ($student['gender'] ?? ''),
            (string) ($student['category'] ?? ''),
        ];
    }
    if ($sl === 1) {
        $studentRows[] = ['No student records for this period.'];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Students', $usedNames),
        $studentRows
    );

    $categoryRows = [
        ['_style' => 'Title', 'Category Quarterly Admissions', $scope, 'FY - ' . $fyLabel],
        [],
        ['_style' => 'Header', 'Sl No.', 'Type', 'Category / Course', 'Schemes', 'Start Date – End Date', 'Target', 'Q1', 'Q2', 'Q3', 'Q4', 'Total', 'Achievement'],
    ];
    $sl = 1;
    $qSums = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
    foreach (($ctx['category_summary'] ?? []) as $categoryRow) {
        $key = (string) ($categoryRow['key'] ?? '');
        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q) {
            $qSums[$q] += (int) ($categoryRow[$q] ?? 0);
        }
        $categoryRows[] = [
            '_style' => 'Group',
            $sl++,
            'Category',
            (string) ($categoryRow['label'] ?? ''),
            '—',
            '—',
            report_monitor_excel_target($categoryRow['target'] ?? 0),
            (int) ($categoryRow['Q1'] ?? 0),
            (int) ($categoryRow['Q2'] ?? 0),
            (int) ($categoryRow['Q3'] ?? 0),
            (int) ($categoryRow['Q4'] ?? 0),
            (int) ($categoryRow['total'] ?? 0),
            report_monitor_excel_achievement($categoryRow['achievement_pct'] ?? null),
        ];
        foreach (($ctx['category_courses'][$key] ?? []) as $courseRow) {
            $label = (string) ($courseRow['course_name'] ?? '');
            if (!empty($courseRow['course_code'])) {
                $label .= ' (' . $courseRow['course_code'] . ')';
            }
            $categoryRows[] = [
                '',
                'Course',
                $label,
                (string) ($courseRow['scheme_names'] ?? '—'),
                (string) ($courseRow['batch_period_label'] ?? '—'),
                '—',
                (int) ($courseRow['Q1'] ?? 0),
                (int) ($courseRow['Q2'] ?? 0),
                (int) ($courseRow['Q3'] ?? 0),
                (int) ($courseRow['Q4'] ?? 0),
                (int) ($courseRow['total'] ?? 0),
                '—',
            ];
        }
    }
    $categoryRows[] = [
        '_style' => 'Header',
        '',
        'Grand Total',
        '',
        '',
        '',
        report_monitor_excel_target($ctx['category_grand_target'] ?? 0),
        $qSums['Q1'],
        $qSums['Q2'],
        $qSums['Q3'],
        $qSums['Q4'],
        (int) ($ctx['category_grand_total'] ?? 0),
        report_monitor_excel_achievement($ctx['category_grand_achievement'] ?? null),
    ];
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Category Quarterly', $usedNames),
        $categoryRows
    );

    $socialRows = [
        ['_style' => 'Title', 'Social Category Quarterly Admissions', $scope, 'FY - ' . $fyLabel],
        [],
        ['_style' => 'Header', 'Sl No.', 'Type', 'Social Category / Course', 'Schemes', 'Start Date – End Date', 'Target', 'Q1', 'Q2', 'Q3', 'Q4', 'Total', 'Achievement'],
    ];
    $sl = 1;
    $qSums = ['Q1' => 0, 'Q2' => 0, 'Q3' => 0, 'Q4' => 0];
    foreach (($ctx['social_summary'] ?? []) as $categoryRow) {
        $key = (string) ($categoryRow['key'] ?? '');
        foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q) {
            $qSums[$q] += (int) ($categoryRow[$q] ?? 0);
        }
        $socialRows[] = [
            '_style' => 'Group',
            $sl++,
            'Category',
            (string) ($categoryRow['label'] ?? ''),
            '—',
            '—',
            report_monitor_excel_target($categoryRow['target'] ?? 0),
            (int) ($categoryRow['Q1'] ?? 0),
            (int) ($categoryRow['Q2'] ?? 0),
            (int) ($categoryRow['Q3'] ?? 0),
            (int) ($categoryRow['Q4'] ?? 0),
            (int) ($categoryRow['total'] ?? 0),
            report_monitor_excel_achievement($categoryRow['achievement_pct'] ?? null),
        ];
        foreach (($ctx['social_courses'][$key] ?? []) as $courseRow) {
            $label = (string) ($courseRow['course_name'] ?? '');
            if (!empty($courseRow['course_code'])) {
                $label .= ' (' . $courseRow['course_code'] . ')';
            }
            $socialRows[] = [
                '',
                'Course',
                $label,
                (string) ($courseRow['scheme_names'] ?? '—'),
                (string) ($courseRow['batch_period_label'] ?? '—'),
                '—',
                (int) ($courseRow['Q1'] ?? 0),
                (int) ($courseRow['Q2'] ?? 0),
                (int) ($courseRow['Q3'] ?? 0),
                (int) ($courseRow['Q4'] ?? 0),
                (int) ($courseRow['total'] ?? 0),
                '—',
            ];
        }
    }
    $socialRows[] = [
        '_style' => 'Header',
        '',
        'Grand Total',
        '',
        '',
        '',
        report_monitor_excel_target($ctx['social_grand_target'] ?? 0),
        $qSums['Q1'],
        $qSums['Q2'],
        $qSums['Q3'],
        $qSums['Q4'],
        (int) ($ctx['social_grand_total'] ?? 0),
        report_monitor_excel_achievement($ctx['social_grand_achievement'] ?? null),
    ];
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Social Category', $usedNames),
        $socialRows
    );

    $genderRows = [
        ['_style' => 'Title', 'Gender Quarterly Admissions', $scope, 'FY - ' . $fyLabel],
        [],
        ['_style' => 'Header', 'Gender', 'Social Category', 'Q1', 'Q2', 'Q3', 'Q4', 'Total'],
    ];
    foreach (($ctx['gender_social'] ?? []) as $genderRow) {
        $genderRows[] = [
            '_style' => 'Group',
            (string) ($genderRow['label'] ?? ''),
            'All',
            (int) ($genderRow['Q1'] ?? 0),
            (int) ($genderRow['Q2'] ?? 0),
            (int) ($genderRow['Q3'] ?? 0),
            (int) ($genderRow['Q4'] ?? 0),
            (int) ($genderRow['total'] ?? 0),
        ];
        $categories = $genderRow['categories'] ?? [];
        if (is_array($categories)) {
            foreach ($categories as $catRow) {
                $genderRows[] = [
                    (string) ($genderRow['label'] ?? ''),
                    (string) ($catRow['label'] ?? ''),
                    (int) ($catRow['Q1'] ?? 0),
                    (int) ($catRow['Q2'] ?? 0),
                    (int) ($catRow['Q3'] ?? 0),
                    (int) ($catRow['Q4'] ?? 0),
                    (int) ($catRow['total'] ?? 0),
                ];
            }
        }
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Gender', $usedNames),
        $genderRows
    );

    $centreRows = [
        ['_style' => 'Title', 'Centre Wise Report', $period],
        [],
        ['_style' => 'Header', 'Sl No.', 'Centre', 'Code', 'Courses', 'Enrolled', 'Admissions', 'Batches', 'Unassigned'],
    ];
    $sl = 1;
    foreach (($ctx['centres'] ?? []) as $row) {
        $centreRows[] = [
            $sl++,
            (string) ($row['centre_name'] ?? ''),
            (string) ($row['centre_code'] ?? ''),
            (int) ($row['course_count'] ?? 0),
            (int) ($row['applications'] ?? 0),
            (int) ($row['batch_enrolled'] ?? 0),
            (int) ($row['batch_count'] ?? 0),
            (int) ($row['unassigned'] ?? 0),
        ];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Centres', $usedNames),
        $centreRows
    );

    $certifiedRows = [
        ['_style' => 'Title', 'Certified and Placed', $period],
        [],
        ['_style' => 'Header', 'Sl No.', 'Centre', 'Certified', 'Placed'],
    ];
    $sl = 1;
    foreach (($ctx['certified_placed'] ?? []) as $row) {
        $certifiedRows[] = [
            $sl++,
            (string) ($row['centre_name'] ?? ''),
            (int) ($row['certified'] ?? 0),
            (int) ($row['placed'] ?? 0),
        ];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Certified and Placed', $usedNames),
        $certifiedRows
    );

    $monthly = $ctx['monthly'] ?? [];
    $labels = $monthly['labels'] ?? [];
    $monthlyRows = [
        ['_style' => 'Title', 'Monthly Trend', $period],
        [],
        ['_style' => 'Header', 'Month', 'Applied', 'Admissions', 'Batches created'],
    ];
    $applications = $monthly['applications'] ?? [];
    $enrollments = $monthly['batch_enrollments'] ?? [];
    $created = $monthly['batches_created'] ?? [];
    foreach ($labels as $index => $label) {
        $monthlyRows[] = [
            (string) $label,
            (int) ($applications[$index] ?? 0),
            (int) ($enrollments[$index] ?? 0),
            (int) ($created[$index] ?? 0),
        ];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Monthly Trend', $usedNames),
        $monthlyRows
    );

    $timeline = $ctx['timeline'] ?? [];
    $timelineRows = [
        ['_style' => 'Title', 'Course FY Timeline Totals', (string) ($timeline['fy_label'] ?? $fyLabel)],
        [],
        ['_style' => 'Header', 'Sl No.', 'Course', 'Code', 'Applied', 'Admissions', 'Footfall', 'Batches'],
    ];
    $sl = 1;
    foreach (($timeline['courses'] ?? []) as $course) {
        $timelineRows[] = [
            $sl++,
            (string) ($course['course_name'] ?? ''),
            (string) ($course['course_code'] ?? ''),
            (int) ($course['total_registered'] ?? 0),
            (int) ($course['total_admissions'] ?? 0),
            (int) ($course['total_footfall'] ?? 0),
            (int) ($course['total_batches'] ?? 0),
        ];
    }
    $grand = $timeline['grand_totals'] ?? [];
    $timelineRows[] = [
        '_style' => 'Header',
        '',
        'Total',
        '',
        (int) ($grand['registered'] ?? 0),
        (int) ($grand['admissions'] ?? 0),
        (int) ($grand['footfall'] ?? 0),
        (int) ($grand['batches'] ?? 0),
    ];
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Course Timeline', $usedNames),
        $timelineRows
    );

    $admissionRows = [
        ['_style' => 'Title', 'Admissions by Batch', $period],
        [],
        ['_style' => 'Header', 'Sl No.', 'Batch', 'Batch Code', 'Course', 'Centre', 'Admissions'],
    ];
    $sl = 1;
    foreach (($ctx['admissions_by_batch'] ?? []) as $row) {
        $admissionRows[] = [
            $sl++,
            (string) ($row['batch_name'] ?? ''),
            (string) ($row['batch_code'] ?? ''),
            (string) ($row['course_name'] ?? ''),
            (string) ($row['centre_name'] ?? ''),
            (int) ($row['admissions'] ?? 0),
        ];
    }
    if ($sl === 1) {
        $admissionRows[] = ['No admissions found for this period.'];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Admissions by Batch', $usedNames),
        $admissionRows
    );

    $resultRows = [
        ['_style' => 'Title', 'Result Status', $period],
        [],
        ['_style' => 'Header', 'Sl No.', 'Batch', 'Batch Code', 'Course Code', 'Course', 'Centre', 'Total', 'Pass / Certified', 'Failed', 'Absent', 'Exam-not applied'],
    ];
    $sl = 1;
    foreach (($ctx['result_batches'] ?? []) as $row) {
        $resultRows[] = [
            $sl++,
            (string) ($row['batch_name'] ?? ''),
            (string) ($row['batch_code'] ?? ''),
            (string) ($row['course_code'] ?? ''),
            (string) ($row['course_name'] ?? ''),
            (string) ($row['centre_name'] ?? ''),
            (int) ($row['total'] ?? 0),
            (int) ($row['pass'] ?? 0),
            (int) ($row['failed'] ?? 0),
            (int) ($row['absent'] ?? 0),
            (int) ($row['exam_not_applied'] ?? 0),
        ];
    }
    $totals = $ctx['result_totals'] ?? [];
    $resultRows[] = [
        '_style' => 'Header',
        '',
        'Total',
        '',
        '',
        '',
        '',
        (int) ($totals['total'] ?? 0),
        (int) ($totals['pass'] ?? 0),
        (int) ($totals['failed'] ?? 0),
        (int) ($totals['absent'] ?? 0),
        (int) ($totals['exam_not_applied'] ?? 0),
    ];
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Result Status', $usedNames),
        $resultRows
    );

    $batchRows = [
        ['_style' => 'Title', 'Batch Details', $period],
        [],
        ['_style' => 'Header', 'Sl No.', 'Batch', 'Batch Code', 'Course', 'Category', 'Centre', 'Start Date', 'End Date', 'Faculty', 'Students', 'Seats', 'Fill %', 'Status'],
    ];
    $sl = 1;
    foreach (($ctx['batches'] ?? []) as $row) {
        $batchRows[] = [
            $sl++,
            (string) ($row['batch_name'] ?? ''),
            (string) ($row['batch_code'] ?? ''),
            (string) ($row['course_name'] ?? ''),
            (string) ($row['course_category'] ?? ''),
            (string) ($row['centre_name'] ?? ''),
            (string) ($row['start_date'] ?? ''),
            (string) ($row['end_date'] ?? ''),
            (string) ($row['faculty_names'] ?? ''),
            (int) ($row['enrolled'] ?? 0),
            (int) ($row['seats_total'] ?? 0),
            (float) ($row['fill_rate'] ?? 0),
            (string) ($row['status'] ?? ''),
        ];
    }
    if ($sl === 1) {
        $batchRows[] = ['No batches found for this period.'];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Batches', $usedNames),
        $batchRows
    );

    $facultyRows = [
        ['_style' => 'Title', 'Faculty Training Summary', $centre, $period],
        [],
        ['_style' => 'Header', 'Sl No.', 'Faculty Name', 'Designation', 'Department', 'Batches', 'Courses', 'Students Trained'],
    ];
    $sl = 1;
    foreach (($ctx['faculty'] ?? []) as $row) {
        $facultyRows[] = [
            $sl++,
            (string) ($row['name'] ?? ''),
            (string) ($row['designation'] ?? ''),
            (string) ($row['department'] ?? ''),
            (int) ($row['batch_count'] ?? 0),
            (int) ($row['course_count'] ?? 0),
            (int) ($row['students_trained'] ?? 0),
        ];
    }
    if ($sl === 1) {
        $facultyRows[] = ['No faculty training data for this period.'];
    }
    $sheets[] = report_monitor_excel_worksheet(
        report_monitor_excel_sheet_name('Faculty', $usedNames),
        $facultyRows
    );

    $safeScope = preg_replace('/[^A-Za-z0-9_-]+/', '_', $scope);
    $safePeriod = preg_replace('/[^A-Za-z0-9_-]+/', '_', $period);
    $filename = 'NIELIT_Report_' . trim($safeScope, '_') . '_' . trim($safePeriod, '_') . '.xls';

    header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
    echo '<Styles>';
    echo '<Style ss:ID="Title"><Font ss:Bold="1" ss:Size="14" ss:Color="#1E3A8A"/></Style>';
    echo '<Style ss:ID="Header"><Font ss:Bold="1" ss:Color="#FFFFFF"/><Interior ss:Color="#1E3A8A" ss:Pattern="Solid"/></Style>';
    echo '<Style ss:ID="Group"><Font ss:Bold="1"/><Interior ss:Color="#E2E8F0" ss:Pattern="Solid"/></Style>';
    echo '</Styles>';
    echo implode('', $sheets);
    echo '</Workbook>';
}
