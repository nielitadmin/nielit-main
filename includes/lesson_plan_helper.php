<?php
/**
 * Lesson Plan helper — monthly/weekly day-wise topics + daily faculty logs.
 */

if (!function_exists('ensureLessonPlanTables')) {
    function ensureLessonPlanTables($conn): bool
    {
        static $ready = false;
        if ($ready) {
            return true;
        }

        $plans = "CREATE TABLE IF NOT EXISTS lesson_plans (
            id INT PRIMARY KEY AUTO_INCREMENT,
            batch_id INT NULL,
            course_id INT NULL,
            faculty_id INT NULL,
            faculty_name VARCHAR(255) NULL,
            course_name VARCHAR(255) NULL,
            plan_title VARCHAR(255) NOT NULL DEFAULT '',
            module_code VARCHAR(50) NULL,
            semester VARCHAR(50) NULL,
            days_per_week TINYINT NOT NULL DEFAULT 5,
            total_weeks INT NOT NULL DEFAULT 16,
            total_hours DECIMAL(8,1) NULL,
            plan_start_date DATE NULL,
            notes TEXT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_lp_batch (batch_id),
            KEY idx_lp_faculty (faculty_id),
            KEY idx_lp_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $rows = "CREATE TABLE IF NOT EXISTS lesson_plan_rows (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lesson_plan_id INT NOT NULL,
            week_number INT NOT NULL,
            class_day TINYINT NOT NULL COMMENT '1=1st class day … 5=5th',
            topic TEXT NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            KEY idx_lpr_plan (lesson_plan_id),
            UNIQUE KEY uq_lpr_slot (lesson_plan_id, week_number, class_day)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $logs = "CREATE TABLE IF NOT EXISTS lesson_plan_daily_logs (
            id INT PRIMARY KEY AUTO_INCREMENT,
            lesson_plan_id INT NOT NULL,
            lesson_plan_row_id INT NULL,
            batch_id INT NULL,
            log_date DATE NOT NULL,
            week_number INT NULL,
            class_day TINYINT NULL,
            topic_planned TEXT NULL,
            topic_covered TEXT NULL,
            status ENUM('planned','completed','partial','skipped','rescheduled') NOT NULL DEFAULT 'planned',
            remarks TEXT NULL,
            updated_by VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_lpd_plan_date (lesson_plan_id, log_date),
            KEY idx_lpd_batch_date (batch_id, log_date),
            UNIQUE KEY uq_lpd_plan_date (lesson_plan_id, log_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        if (!$conn->query($plans)) {
            error_log('ensureLessonPlanTables plans failed: ' . $conn->error);
            return false;
        }
        if (!$conn->query($rows)) {
            error_log('ensureLessonPlanTables rows failed: ' . $conn->error);
            return false;
        }
        if (!$conn->query($logs)) {
            error_log('ensureLessonPlanTables logs failed: ' . $conn->error);
            return false;
        }

        // Allow plans/logs without a batch (existing installs may still be NOT NULL)
        @$conn->query('ALTER TABLE lesson_plans MODIFY batch_id INT NULL');
        @$conn->query('ALTER TABLE lesson_plan_daily_logs MODIFY batch_id INT NULL');
        // Optional plan start date for monthly calendar when batch is blank
        $colCheck = $conn->query("SHOW COLUMNS FROM lesson_plans LIKE 'plan_start_date'");
        if ($colCheck && $colCheck->num_rows === 0) {
            @$conn->query('ALTER TABLE lesson_plans ADD COLUMN plan_start_date DATE NULL AFTER total_hours');
        }

        $ready = true;
        return true;
    }
}

if (!function_exists('lessonPlanOrdinal')) {
    function lessonPlanOrdinal(int $n): string
    {
        $suffix = 'th';
        if ($n % 100 < 11 || $n % 100 > 13) {
            switch ($n % 10) {
                case 1: $suffix = 'st'; break;
                case 2: $suffix = 'nd'; break;
                case 3: $suffix = 'rd'; break;
            }
        }
        return $n . $suffix;
    }
}

if (!function_exists('lessonPlanEffectiveStartDate')) {
    /**
     * Prefer plan_start_date, else batch start_date. Returns Y-m-d or null.
     * @param array<string,mixed> $plan
     */
    function lessonPlanEffectiveStartDate(array $plan): ?string
    {
        $planStart = trim((string) ($plan['plan_start_date'] ?? ''));
        if ($planStart !== '' && strtotime($planStart)) {
            return date('Y-m-d', strtotime($planStart));
        }
        $batchStart = trim((string) ($plan['batch_start_date'] ?? ''));
        if ($batchStart !== '' && strtotime($batchStart)) {
            return date('Y-m-d', strtotime($batchStart));
        }
        return null;
    }
}

if (!function_exists('lessonPlanM1R5Template')) {
    /**
     * Sample Detailed Lesson Plan — M1-R5 IT Tools and Network Basics (O Level).
     * @return array{header:array,rows:list<array{week:int,day:int,topic:string}>}
     */
    function lessonPlanM1R5Template(): array
    {
        $header = [
            'plan_title' => "Detailed Lesson Plan - M1-R5 Information Technology Tools and Network Basics",
            'course_name' => "'O' Level",
            'module_code' => 'M1-R5',
            'semester' => '1st',
            'faculty_name' => 'Miss. Ashmita Ghatani',
            'days_per_week' => 5,
            'total_weeks' => 16,
            'total_hours' => 120,
        ];

        $topics = [
            1 => [
                1 => 'Unit-1: Introduction to Computer',
                2 => '1.1 Computer & Latest IT Gadgets, Evolution of Computers',
                3 => '1.2 Types of Computer, Characteristics',
                4 => '1.3 Applications of Computers, IT Gadgets Applications',
                5 => '1.4 Hardware & Software Basics, CPU',
            ],
            2 => [
                1 => '1.5 Input Devices, Output Devices',
                2 => '1.6 Computer Memory & Storage Devices',
                3 => '1.7 Application Software, System Software, Utility Software',
                4 => '1.8 Open Source & Proprietary Software, Mobile Apps',
                5 => 'MCQs Discussion (Unit-1)',
            ],
            3 => [
                1 => 'Unit-2: Introduction to Operating System',
                2 => '2.1 Operating System, Basics of OS, Functions of OS, Desktop & Laptop Operating Systems',
                3 => '2.2 Mobile Phone & Tablet Operating Systems, DOS, Windows',
                4 => '2.3 Linux OS, User Interface, Taskbar, Icons & Shortcuts',
                5 => '2.4 Running Applications, Simple OS Settings',
            ],
            4 => [
                1 => '2.5 Mouse Properties, Date & Time Settings',
                2 => '2.6 Display Properties',
                3 => '2.7 Add/Remove Programs & Features',
                4 => '2.8 Printers: Add, Remove & Share',
                5 => '2.9 File/Folder Management, File Extensions',
            ],
            5 => [
                1 => 'MCQs Discussion (Unit-2)',
                2 => 'Unit-3: Word Processing',
                3 => '3.1 Word Processing Basics, Interface, Creating, Opening & Closing Documents, Save, Save As, Help Menu',
                4 => '3.2 Title Bar, Menu Bar, Toolbars & Side Bar',
                5 => '3.3 Page Setup, Layout, Borders, Watermark',
            ],
            6 => [
                1 => '3.4 Print Preview, Printing, PDF Creation',
                2 => '3.5 Text Creation & Editing',
                3 => '3.6 Text Selection, Cut, Copy, Paste',
                4 => '3.7 Font, Color, Style, Size, Alignment',
                5 => '3.8 Undo, Redo, AutoCorrect',
            ],
            7 => [
                1 => '3.9 Spelling & Grammar, Find & Replace',
                2 => '3.10 Formatting Text, Styles',
                3 => '3.11 Indentation, Bullets, Numbering, Change Case',
                4 => '3.12 Header & Footer, Hyperlink',
                5 => '3.13 Table Creation & Manipulation',
            ],
            8 => [
                1 => '3.14 Rows, Columns, Merge & Split Cells',
                2 => '3.15 Borders & Shading',
                3 => '3.16 Mail Merge, Table of Contents, Index, Comments',
                4 => '3.17 Tracking Changes, Macros, Shortcut Keys for LibreOffice Writer',
                5 => 'MCQs Discussion / Practical Question Solving (Unit-3)',
            ],
            9 => [
                1 => 'Unit-4: Spreadsheet',
                2 => '4.1 Introduction to LibreOffice Calc Spreadsheet, Cell Address, Data Entry: Text, Number, Date, Page Setup, Print, Save, Open',
                3 => '4.2 Cell & Sheet Manipulation, Formatting Cells, Cut, Copy, Paste Special',
                4 => '4.3 Row/Column Insert Delete, AutoFill, Sorting & Filtering, Freezing Panes',
                5 => '4.4 Formulas & AutoSum, Functions: SUM, COUNT, MAX, MIN, AVERAGE',
            ],
            10 => [
                1 => '4.5 Types of Functions, Advanced Filter, Database Functions',
                2 => '4.6 What-if Analysis, Pivot Tables, Charts: Bar, Pie, Line, Column',
                3 => '4.7 Data Validation, Shortcut Keys for LibreOffice Calc',
                4 => 'MCQs Discussion / Practical Question Solving (Unit-4)',
                5 => 'Unit-5: Presentation',
            ],
            11 => [
                1 => '5.1 Introduction to LibreOffice Impress, Presentation Basics',
                2 => '5.2 Templates & Blank Presentations, Insert/Edit Text, Add/Delete Slides',
                3 => '5.3 Save Presentation, Tables, Pictures, Objects, Resize, Master Slide',
                4 => '5.4 Slide Show Setup & Presentation, Transitions, Timings, Animation',
                5 => '5.5 Providing Aesthetics to slides & Printing, Color & Line Styles',
            ],
            12 => [
                1 => '5.6 Adding Movie, Sound, Header, Footer, Notes, Printing Slides & Handouts',
                2 => '5.7 Shortcut Keys for LibreOffice Impress + MCQs Discussion (Unit-5)',
                3 => 'Unit-6: Introduction to Internet and WWW',
                4 => 'Basic of Computer Networks, Local Area Network, Wide Area Network, Difference between Internet, Intranet and Extranet',
                5 => 'Network Topologies, Internet, WWW, Applications of Internet, URL, IP Address, ISP and Role of ISP',
            ],
            13 => [
                1 => 'Internet Protocol, Connection Modes: Hotspot, WiFi, LAN, Broadband',
                2 => 'Identifying and uses of IP/MAC/IMEI of various devices, Popular Web Browsers',
                3 => 'Exploring the Internet, Surfing the web, Popular Search Engines',
                4 => 'Searching on Internet, Downloading Web Pages, Printing Web Pages',
                5 => 'MCQs Discussion (Unit-6)',
            ],
            14 => [
                1 => 'Unit-7: E-mail, Social Networking and e-Governance Services',
                2 => 'Structure of E-mail, Using E-mails, Opening Email account',
                3 => 'Mailbox: Inbox and Outbox, Creating, Sending & Replying a new E-mail, CC, BCC, Spam, Drafts, Attachments',
                4 => 'Forwarding an E-mail message, Searching emails, Attaching files with email, Email Signature',
                5 => 'Social Networking & e-Commerce, Facebook, Twitter, LinkedIn, Instagram, Instant Messaging',
            ],
            15 => [
                1 => 'Introduction to Blogs, Basics of E-commerce, Netiquettes, Overview of e-Governance Services + MCQs Discussion (Unit-7)',
                2 => 'Unit-8: Digital Financial Tools and Applications',
                3 => 'Digital Financial Tools: Understanding OTP, QR, UPI, AEPS, USSD',
                4 => 'Card, eWallet, PoS, Internet Banking, NEFT, RTGS, IMPS',
                5 => 'Online Bill Payment + MCQs Discussion (Unit-8)',
            ],
            16 => [
                1 => 'Unit-9: Overview of Future skills and Cyber Security',
                2 => 'Introduction to Internet of Things (IoT), Big Data Analytics, Cloud Computing',
                3 => 'Virtual Reality, Artificial Intelligence, Social & Mobile, Blockchain Technology',
                4 => '3D Printing/ Additive Manufacturing, Robotics Process Automation',
                5 => 'Cyber Security + Final MCQs/Revision (Units 7-9)',
            ],
        ];

        $rows = [];
        foreach ($topics as $week => $days) {
            foreach ($days as $day => $topic) {
                $rows[] = ['week' => (int) $week, 'day' => (int) $day, 'topic' => $topic];
            }
        }

        return ['header' => $header, 'rows' => $rows];
    }
}

if (!function_exists('listLessonPlansAdmin')) {
    /** @return list<array<string,mixed>> */
    function listLessonPlansAdmin($conn, ?int $batchId = null): array
    {
        ensureLessonPlanTables($conn);
        $sql = "SELECT lp.*, b.batch_name, b.batch_code, b.start_date AS batch_start_date
                FROM lesson_plans lp
                LEFT JOIN batches b ON b.id = lp.batch_id
                WHERE 1=1";
        $types = '';
        $params = [];
        if ($batchId !== null && $batchId > 0) {
            $sql .= ' AND lp.batch_id = ?';
            $types .= 'i';
            $params[] = $batchId;
        }
        $sql .= ' ORDER BY lp.updated_at DESC, lp.id DESC';

        $rows = [];
        if ($types !== '') {
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                return [];
            }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $rows[] = $row;
            }
            $stmt->close();
        } else {
            $res = $conn->query($sql);
            if ($res) {
                while ($row = $res->fetch_assoc()) {
                    $rows[] = $row;
                }
            }
        }
        return $rows;
    }
}

if (!function_exists('getLessonPlan')) {
    /** @return array<string,mixed>|null */
    function getLessonPlan($conn, int $id): ?array
    {
        ensureLessonPlanTables($conn);
        $stmt = $conn->prepare(
            "SELECT lp.*, b.batch_name, b.batch_code, b.start_date AS batch_start_date, c.course_name AS linked_course_name
             FROM lesson_plans lp
             LEFT JOIN batches b ON b.id = lp.batch_id
             LEFT JOIN courses c ON c.id = COALESCE(lp.course_id, b.course_id)
             WHERE lp.id = ? LIMIT 1"
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}

if (!function_exists('getLessonPlanRows')) {
    /**
     * @return array<int, array<int, array<string,mixed>>> week => day => row
     */
    function getLessonPlanRows($conn, int $planId): array
    {
        ensureLessonPlanTables($conn);
        $grid = [];
        $stmt = $conn->prepare(
            'SELECT * FROM lesson_plan_rows WHERE lesson_plan_id = ? ORDER BY week_number ASC, class_day ASC'
        );
        if (!$stmt) {
            return [];
        }
        $stmt->bind_param('i', $planId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $w = (int) $row['week_number'];
            $d = (int) $row['class_day'];
            $grid[$w][$d] = $row;
        }
        $stmt->close();
        return $grid;
    }
}

if (!function_exists('saveLessonPlanHeader')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string,id?:int}
     */
    function saveLessonPlanHeader($conn, array $data, ?int $id = null): array
    {
        ensureLessonPlanTables($conn);

        $batchId = (int) ($data['batch_id'] ?? 0);
        $courseId = !empty($data['course_id']) ? (int) $data['course_id'] : null;
        $facultyId = !empty($data['faculty_id']) ? (int) $data['faculty_id'] : null;
        $facultyName = trim((string) ($data['faculty_name'] ?? ''));
        $courseName = trim((string) ($data['course_name'] ?? ''));
        $planTitle = trim((string) ($data['plan_title'] ?? ''));
        $moduleCode = trim((string) ($data['module_code'] ?? ''));
        $semester = trim((string) ($data['semester'] ?? ''));
        $daysPerWeek = max(1, min(6, (int) ($data['days_per_week'] ?? 5)));
        $totalWeeks = max(1, min(52, (int) ($data['total_weeks'] ?? 16)));
        $totalHours = isset($data['total_hours']) && $data['total_hours'] !== '' ? (float) $data['total_hours'] : null;
        $planStartRaw = trim((string) ($data['plan_start_date'] ?? ''));
        $notes = trim((string) ($data['notes'] ?? ''));
        $isActive = !empty($data['is_active']) ? 1 : 0;
        $createdBy = trim((string) ($data['created_by'] ?? 'admin'));

        if ($planTitle === '') {
            return ['success' => false, 'message' => 'Plan title is required.'];
        }

        $batchIdVal = $batchId > 0 ? $batchId : 0;
        $batchStartFromBatch = null;
        if ($batchIdVal > 0) {
            $check = $conn->prepare('SELECT id, course_id, start_date FROM batches WHERE id = ? LIMIT 1');
            if (!$check) {
                return ['success' => false, 'message' => 'Could not validate batch.'];
            }
            $check->bind_param('i', $batchIdVal);
            $check->execute();
            $batchRow = $check->get_result()->fetch_assoc();
            $check->close();
            if (!$batchRow) {
                return ['success' => false, 'message' => 'Selected batch was not found.'];
            }
            if ($courseId === null && !empty($batchRow['course_id'])) {
                $courseId = (int) $batchRow['course_id'];
            }
            if (!empty($batchRow['start_date'])) {
                $batchStartFromBatch = date('Y-m-d', strtotime((string) $batchRow['start_date']));
            }
        }

        // Prefer posted plan start; else inherit from batch start when available
        $planStartDate = null;
        if ($planStartRaw !== '' && strtotime($planStartRaw)) {
            $planStartDate = date('Y-m-d', strtotime($planStartRaw));
        } elseif ($batchStartFromBatch) {
            $planStartDate = $batchStartFromBatch;
        }
        $planStartVal = $planStartDate ?? '';
        $hasPlanStart = $planStartDate !== null ? 1 : 0;

        $facultyVal = $facultyName !== '' ? $facultyName : null;
        $courseNameVal = $courseName !== '' ? $courseName : null;
        $moduleVal = $moduleCode !== '' ? $moduleCode : null;
        $semesterVal = $semester !== '' ? $semester : null;
        $notesVal = $notes !== '' ? $notes : null;
        $courseIdVal = $courseId !== null && $courseId > 0 ? $courseId : 0;
        $facultyIdVal = $facultyId !== null && $facultyId > 0 ? $facultyId : 0;
        $totalHoursVal = $totalHours !== null ? (float) $totalHours : 0.0;
        $hasHours = $totalHours !== null ? 1 : 0;

        if ($id !== null && $id > 0) {
            $stmt = $conn->prepare(
                'UPDATE lesson_plans
                 SET batch_id=NULLIF(?,0), course_id=NULLIF(?,0), faculty_id=NULLIF(?,0), faculty_name=?, course_name=?,
                     plan_title=?, module_code=?, semester=?, days_per_week=?, total_weeks=?,
                     total_hours=IF(?=1, ?, NULL), plan_start_date=IF(?=1, ?, NULL), notes=?, is_active=?
                 WHERE id=?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'iiisssssiidisissi',
                $batchIdVal,
                $courseIdVal,
                $facultyIdVal,
                $facultyVal,
                $courseNameVal,
                $planTitle,
                $moduleVal,
                $semesterVal,
                $daysPerWeek,
                $totalWeeks,
                $hasHours,
                $totalHoursVal,
                $hasPlanStart,
                $planStartVal,
                $notesVal,
                $isActive,
                $id
            );
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            if (!$ok) {
                return ['success' => false, 'message' => 'Could not update plan: ' . $err];
            }
            return ['success' => true, 'message' => 'Lesson plan updated.', 'id' => $id];
        }

        $stmt = $conn->prepare(
            'INSERT INTO lesson_plans
             (batch_id, course_id, faculty_id, faculty_name, course_name, plan_title, module_code, semester,
              days_per_week, total_weeks, total_hours, plan_start_date, notes, is_active, created_by)
             VALUES (NULLIF(?,0), NULLIF(?,0), NULLIF(?,0), ?, ?, ?, ?, ?, ?, ?, IF(?=1, ?, NULL), IF(?=1, ?, NULL), ?, ?, ?)'
        );
        if (!$stmt) {
            return ['success' => false, 'message' => 'Database error: ' . $conn->error];
        }
        $stmt->bind_param(
            'iiisssssiidisissis',
            $batchIdVal,
            $courseIdVal,
            $facultyIdVal,
            $facultyVal,
            $courseNameVal,
            $planTitle,
            $moduleVal,
            $semesterVal,
            $daysPerWeek,
            $totalWeeks,
            $hasHours,
            $totalHoursVal,
            $hasPlanStart,
            $planStartVal,
            $notesVal,
            $isActive,
            $createdBy
        );
        $ok = $stmt->execute();
        $newId = (int) $stmt->insert_id;
        $err = $stmt->error;
        $stmt->close();
        if (!$ok) {
            return ['success' => false, 'message' => 'Could not create plan: ' . $err];
        }
        return ['success' => true, 'message' => 'Lesson plan created.', 'id' => $newId];
    }
}

if (!function_exists('saveLessonPlanRows')) {
    /**
     * Replace all rows for a plan from posted topics[week][day] = topic.
     * @param array<int,array<int,string>> $topics
     * @return array{success:bool,message:string}
     */
    function saveLessonPlanRows($conn, int $planId, array $topics): array
    {
        ensureLessonPlanTables($conn);
        if ($planId <= 0) {
            return ['success' => false, 'message' => 'Invalid plan.'];
        }

        $conn->begin_transaction();
        try {
            $del = $conn->prepare('DELETE FROM lesson_plan_rows WHERE lesson_plan_id = ?');
            if (!$del) {
                throw new RuntimeException($conn->error);
            }
            $del->bind_param('i', $planId);
            $del->execute();
            $del->close();

            $ins = $conn->prepare(
                'INSERT INTO lesson_plan_rows (lesson_plan_id, week_number, class_day, topic, sort_order)
                 VALUES (?, ?, ?, ?, ?)'
            );
            if (!$ins) {
                throw new RuntimeException($conn->error);
            }

            $count = 0;
            foreach ($topics as $week => $days) {
                $week = (int) $week;
                if ($week < 1 || !is_array($days)) {
                    continue;
                }
                foreach ($days as $day => $topic) {
                    $day = (int) $day;
                    $topic = trim((string) $topic);
                    if ($day < 1 || $day > 6 || $topic === '') {
                        continue;
                    }
                    $sort = ($week * 10) + $day;
                    $ins->bind_param('iiisi', $planId, $week, $day, $topic, $sort);
                    if (!$ins->execute()) {
                        throw new RuntimeException($ins->error);
                    }
                    $count++;
                }
            }
            $ins->close();
            $conn->commit();
            return ['success' => true, 'message' => "Saved $count topic rows."];
        } catch (Throwable $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Could not save topics: ' . $e->getMessage()];
        }
    }
}

if (!function_exists('importLessonPlanTemplate')) {
    /**
     * Create/fill plan from a template (e.g. M1-R5).
     * @return array{success:bool,message:string,id?:int}
     */
    function importLessonPlanTemplate($conn, int $batchId, string $templateKey, string $createdBy = 'admin', string $planStartDate = ''): array
    {
        if ($templateKey !== 'm1_r5') {
            return ['success' => false, 'message' => 'Unknown template.'];
        }
        $tpl = lessonPlanM1R5Template();
        $header = $tpl['header'];
        $header['batch_id'] = $batchId;
        $header['plan_start_date'] = $planStartDate;
        $header['created_by'] = $createdBy;
        $header['is_active'] = 1;

        $result = saveLessonPlanHeader($conn, $header, null);
        if (!$result['success'] || empty($result['id'])) {
            return $result;
        }
        $planId = (int) $result['id'];
        $topics = [];
        foreach ($tpl['rows'] as $row) {
            $topics[$row['week']][$row['day']] = $row['topic'];
        }
        $rowResult = saveLessonPlanRows($conn, $planId, $topics);
        if (!$rowResult['success']) {
            return $rowResult;
        }
        return [
            'success' => true,
            'message' => 'Imported M1-R5 O Level lesson plan (' . count($tpl['rows']) . ' topics).',
            'id' => $planId,
        ];
    }
}

if (!function_exists('deleteLessonPlan')) {
    /** @return array{success:bool,message:string} */
    function deleteLessonPlan($conn, int $id): array
    {
        ensureLessonPlanTables($conn);
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid plan.'];
        }
        $conn->begin_transaction();
        try {
            $stmt1 = $conn->prepare('DELETE FROM lesson_plan_daily_logs WHERE lesson_plan_id = ?');
            $stmt1->bind_param('i', $id);
            $stmt1->execute();
            $stmt1->close();

            $stmt2 = $conn->prepare('DELETE FROM lesson_plan_rows WHERE lesson_plan_id = ?');
            $stmt2->bind_param('i', $id);
            $stmt2->execute();
            $stmt2->close();

            $stmt3 = $conn->prepare('DELETE FROM lesson_plans WHERE id = ?');
            $stmt3->bind_param('i', $id);
            $stmt3->execute();
            $affected = $stmt3->affected_rows;
            $stmt3->close();
            $conn->commit();
            if ($affected < 1) {
                return ['success' => false, 'message' => 'Plan not found.'];
            }
            return ['success' => true, 'message' => 'Lesson plan deleted.'];
        } catch (Throwable $e) {
            $conn->rollback();
            return ['success' => false, 'message' => 'Could not delete plan.'];
        }
    }
}

if (!function_exists('lessonPlanBuildMonthCalendar')) {
    /**
     * Map plan weeks onto calendar months from batch start date.
     * @param array<int, array<int, array<string,mixed>>> $rows week=>day=>row
     * @return list<array{year:int,month:int,label:string,weeks:list<array{week:int,range:string,days:list<array{dow:int,date:string,label:string,topic:?string,row_id:?int}>}>}>
     */
    function lessonPlanBuildMonthCalendar(?string $batchStart, int $totalWeeks, int $daysPerWeek, array $rows): array
    {
        $totalWeeks = max(1, min(52, $totalWeeks));
        $daysPerWeek = max(1, min(6, $daysPerWeek));
        if (!$batchStart || !strtotime($batchStart)) {
            $batchStart = date('Y-m-d');
        }

        $startTs = strtotime($batchStart . ' 00:00:00');
        $startDow = (int) date('N', $startTs); // 1=Mon
        // First Monday on/before batch start
        $week1Monday = strtotime('-' . ($startDow - 1) . ' days', $startTs);

        $byMonth = [];
        for ($w = 1; $w <= $totalWeeks; $w++) {
            $monday = strtotime('+' . (($w - 1) * 7) . ' days', $week1Monday);
            $days = [];
            for ($d = 1; $d <= $daysPerWeek; $d++) {
                $dayTs = strtotime('+' . ($d - 1) . ' days', $monday);
                $date = date('Y-m-d', $dayTs);
                $year = (int) date('Y', $dayTs);
                $month = (int) date('n', $dayTs);
                $topic = $rows[$w][$d]['topic'] ?? null;
                $rowId = isset($rows[$w][$d]['id']) ? (int) $rows[$w][$d]['id'] : null;
                $days[] = [
                    'dow' => $d,
                    'date' => $date,
                    'label' => date('D j M', $dayTs),
                    'day_name' => date('l', $dayTs),
                    'topic' => $topic,
                    'row_id' => $rowId,
                    'week' => $w,
                ];
            }
            // Place week under the month of its Monday (or first day)
            $anchorTs = strtotime($days[0]['date']);
            $year = (int) date('Y', $anchorTs);
            $month = (int) date('n', $anchorTs);
            $key = sprintf('%04d-%02d', $year, $month);
            if (!isset($byMonth[$key])) {
                $byMonth[$key] = [
                    'year' => $year,
                    'month' => $month,
                    'label' => date('F Y', $anchorTs),
                    'weeks' => [],
                ];
            }
            $first = $days[0]['date'];
            $last = $days[count($days) - 1]['date'];
            $byMonth[$key]['weeks'][] = [
                'week' => $w,
                'range' => date('j M', strtotime($first)) . ' – ' . date('j M Y', strtotime($last)),
                'days' => $days,
            ];
        }

        ksort($byMonth);
        return array_values($byMonth);
    }
}

if (!function_exists('lessonPlanWeekNumberForDate')) {
    /** Week number relative to batch start date (Mon-based weeks). */
    function lessonPlanWeekNumberForDate(?string $batchStart, string $dateYmd): int
    {
        if (!$batchStart) {
            return 1;
        }
        $start = strtotime($batchStart . ' 00:00:00');
        $date = strtotime($dateYmd . ' 00:00:00');
        if ($start === false || $date === false || $date < $start) {
            return 1;
        }
        // Align to Monday of start week
        $startDow = (int) date('N', $start); // 1=Mon
        $weekStart = strtotime('-' . ($startDow - 1) . ' days', $start);
        $diffDays = (int) floor(($date - $weekStart) / 86400);
        return max(1, (int) floor($diffDays / 7) + 1);
    }
}

if (!function_exists('lessonPlanClassDayForDate')) {
    /** Map calendar date to class day 1–5 (Mon=1 … Fri=5). Sat/Sun = 0. */
    function lessonPlanClassDayForDate(string $dateYmd): int
    {
        $n = (int) date('N', strtotime($dateYmd));
        return ($n >= 1 && $n <= 5) ? $n : 0;
    }
}

if (!function_exists('getLessonPlanTopicForDate')) {
    /** @return array<string,mixed>|null */
    function getLessonPlanTopicForDate($conn, int $planId, string $dateYmd, ?string $batchStart = null): ?array
    {
        $plan = getLessonPlan($conn, $planId);
        if (!$plan) {
            return null;
        }
        if ($batchStart === null) {
            $batchStart = lessonPlanEffectiveStartDate($plan);
        }
        $week = lessonPlanWeekNumberForDate($batchStart, $dateYmd);
        $day = lessonPlanClassDayForDate($dateYmd);
        if ($day < 1) {
            return [
                'week_number' => $week,
                'class_day' => 0,
                'topic' => null,
                'is_holiday' => true,
                'plan' => $plan,
            ];
        }
        $grid = getLessonPlanRows($conn, $planId);
        $row = $grid[$week][$day] ?? null;
        return [
            'week_number' => $week,
            'class_day' => $day,
            'topic' => $row['topic'] ?? null,
            'row_id' => $row['id'] ?? null,
            'is_holiday' => false,
            'plan' => $plan,
            'row' => $row,
        ];
    }
}

if (!function_exists('saveLessonPlanDailyLog')) {
    /**
     * @param array<string,mixed> $data
     * @return array{success:bool,message:string}
     */
    function saveLessonPlanDailyLog($conn, array $data): array
    {
        ensureLessonPlanTables($conn);
        $planId = (int) ($data['lesson_plan_id'] ?? 0);
        $batchId = (int) ($data['batch_id'] ?? 0);
        $logDate = trim((string) ($data['log_date'] ?? ''));
        $rowId = (int) ($data['lesson_plan_row_id'] ?? 0);
        $week = (int) ($data['week_number'] ?? 0);
        $day = (int) ($data['class_day'] ?? 0);
        $planned = trim((string) ($data['topic_planned'] ?? ''));
        $covered = trim((string) ($data['topic_covered'] ?? ''));
        $status = (string) ($data['status'] ?? 'completed');
        $allowed = ['planned', 'completed', 'partial', 'skipped', 'rescheduled'];
        if (!in_array($status, $allowed, true)) {
            $status = 'completed';
        }
        $remarks = trim((string) ($data['remarks'] ?? ''));
        $updatedBy = trim((string) ($data['updated_by'] ?? 'admin'));

        if ($planId <= 0 || $logDate === '') {
            return ['success' => false, 'message' => 'Missing plan or date.'];
        }
        if ($covered === '') {
            return ['success' => false, 'message' => 'Please enter the topic covered today.'];
        }

        // Use 0 instead of NULL for optional ints (safer with mysqli bind_param)
        $batchIdVal = $batchId > 0 ? $batchId : 0;
        $rowIdVal = $rowId > 0 ? $rowId : 0;
        $weekVal = $week > 0 ? $week : 0;
        $dayVal = $day > 0 ? $day : 0;
        $plannedVal = $planned !== '' ? $planned : '';
        $remarksVal = $remarks !== '' ? $remarks : '';

        // Prefer update-or-insert (compatible with MariaDB / older MySQL)
        $existing = getLessonPlanDailyLog($conn, $planId, $logDate);
        if ($existing) {
            $stmt = $conn->prepare(
                'UPDATE lesson_plan_daily_logs
                 SET lesson_plan_row_id = NULLIF(?, 0), batch_id = NULLIF(?, 0),
                     week_number = NULLIF(?, 0), class_day = NULLIF(?, 0),
                     topic_planned = ?, topic_covered = ?, status = ?, remarks = ?, updated_by = ?
                 WHERE lesson_plan_id = ? AND log_date = ?'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'iiiisssssis',
                $rowIdVal,
                $batchIdVal,
                $weekVal,
                $dayVal,
                $plannedVal,
                $covered,
                $status,
                $remarksVal,
                $updatedBy,
                $planId,
                $logDate
            );
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO lesson_plan_daily_logs
                 (lesson_plan_id, lesson_plan_row_id, batch_id, log_date, week_number, class_day,
                  topic_planned, topic_covered, status, remarks, updated_by)
                 VALUES (?, NULLIF(?, 0), NULLIF(?, 0), ?, NULLIF(?, 0), NULLIF(?, 0), ?, ?, ?, ?, ?)'
            );
            if (!$stmt) {
                return ['success' => false, 'message' => 'Database error: ' . $conn->error];
            }
            $stmt->bind_param(
                'iiisiisssss',
                $planId,
                $rowIdVal,
                $batchIdVal,
                $logDate,
                $weekVal,
                $dayVal,
                $plannedVal,
                $covered,
                $status,
                $remarksVal,
                $updatedBy
            );
        }

        try {
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
        } catch (Throwable $e) {
            return ['success' => false, 'message' => 'Could not save daily update: ' . $e->getMessage()];
        }

        if (!$ok) {
            return ['success' => false, 'message' => 'Could not save daily update: ' . $err];
        }

        // Keep master lesson plan topic in sync when faculty edits planned topic
        if ($weekVal > 0 && $dayVal > 0 && $plannedVal !== '') {
            upsertLessonPlanTopicCell($conn, $planId, $weekVal, $dayVal, $plannedVal);
        }

        return ['success' => true, 'message' => 'Daily lesson update saved.'];
    }
}

if (!function_exists('upsertLessonPlanTopicCell')) {
    /** Create or update one week×day topic on the master lesson plan. */
    function upsertLessonPlanTopicCell($conn, int $planId, int $week, int $day, string $topic): bool
    {
        ensureLessonPlanTables($conn);
        $planId = (int) $planId;
        $week = (int) $week;
        $day = (int) $day;
        $topic = trim($topic);
        if ($planId <= 0 || $week < 1 || $day < 1 || $day > 6 || $topic === '') {
            return false;
        }
        $sort = ($week * 10) + $day;

        $sel = $conn->prepare(
            'SELECT id FROM lesson_plan_rows WHERE lesson_plan_id = ? AND week_number = ? AND class_day = ? LIMIT 1'
        );
        if (!$sel) {
            return false;
        }
        $sel->bind_param('iii', $planId, $week, $day);
        $sel->execute();
        $existing = $sel->get_result()->fetch_assoc();
        $sel->close();

        if ($existing) {
            $upd = $conn->prepare('UPDATE lesson_plan_rows SET topic = ?, sort_order = ? WHERE id = ?');
            if (!$upd) {
                return false;
            }
            $id = (int) $existing['id'];
            $upd->bind_param('sii', $topic, $sort, $id);
            $ok = $upd->execute();
            $upd->close();
            return (bool) $ok;
        }

        $ins = $conn->prepare(
            'INSERT INTO lesson_plan_rows (lesson_plan_id, week_number, class_day, topic, sort_order)
             VALUES (?, ?, ?, ?, ?)'
        );
        if (!$ins) {
            return false;
        }
        $ins->bind_param('iiisi', $planId, $week, $day, $topic, $sort);
        $ok = $ins->execute();
        $ins->close();
        return (bool) $ok;
    }
}

if (!function_exists('getLessonPlanDailyLog')) {
    /** @return array<string,mixed>|null */
    function getLessonPlanDailyLog($conn, int $planId, string $dateYmd): ?array
    {
        ensureLessonPlanTables($conn);
        $stmt = $conn->prepare(
            'SELECT * FROM lesson_plan_daily_logs WHERE lesson_plan_id = ? AND log_date = ? LIMIT 1'
        );
        if (!$stmt) {
            return null;
        }
        $stmt->bind_param('is', $planId, $dateYmd);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
