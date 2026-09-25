<?php
/**
 * Per-course required documents for student registration.
 * Stored as JSON array of form field keys on courses.required_documents.
 */

if (!function_exists('courseRequiredDocumentsCatalog')) {
    /**
     * Catalog of selectable registration document fields.
     * Keys match student/register.php + submit_registration.php file input names.
     *
     * @return list<array{key:string,label:string,group:string,default:bool}>
     */
    function courseRequiredDocumentsCatalog(): array
    {
        return [
            ['key' => 'passport_photo', 'label' => 'Passport photo', 'group' => 'Photo & Signature', 'default' => true],
            ['key' => 'signature', 'label' => 'Signature', 'group' => 'Photo & Signature', 'default' => true],
            ['key' => 'left_thumb_impression', 'label' => 'Left thumb impression', 'group' => 'Photo & Signature', 'default' => false],
            ['key' => 'aadhar_card', 'label' => 'Aadhaar card', 'group' => 'Identity', 'default' => true],
            ['key' => 'tenth_marksheet', 'label' => '10th certificate / marksheet', 'group' => 'Education', 'default' => true],
            ['key' => 'twelfth_certificate', 'label' => '12th certificate / diploma', 'group' => 'Education', 'default' => false],
            ['key' => 'twelfth_marksheet', 'label' => '12th marksheet / diploma', 'group' => 'Education', 'default' => false],
            ['key' => 'graduation_certificate', 'label' => 'Graduation certificate', 'group' => 'Education', 'default' => false],
            ['key' => 'caste_certificate', 'label' => 'Caste certificate', 'group' => 'Additional', 'default' => false],
            ['key' => 'other_documents', 'label' => 'Other supporting documents', 'group' => 'Additional', 'default' => false],
            ['key' => 'bank_passbook', 'label' => 'Bank passbook', 'group' => 'DGE / Project', 'default' => false],
            ['key' => 'income_certificate', 'label' => 'Income certificate', 'group' => 'DGE / Project', 'default' => false],
            ['key' => 'aadhaar_bank_seeding_proof', 'label' => 'Aadhaar bank seeding proof', 'group' => 'DGE / Project', 'default' => false],
        ];
    }
}

if (!function_exists('courseRequiredDocumentsDefaultKeys')) {
    /**
     * @return list<string>
     */
    function courseRequiredDocumentsDefaultKeys(): array
    {
        $keys = [];
        foreach (courseRequiredDocumentsCatalog() as $item) {
            if (!empty($item['default'])) {
                $keys[] = $item['key'];
            }
        }
        return $keys;
    }
}

if (!function_exists('courseRequiredDocumentsAllowedKeys')) {
    /**
     * @return list<string>
     */
    function courseRequiredDocumentsAllowedKeys(): array
    {
        return array_values(array_map(static function ($item) {
            return $item['key'];
        }, courseRequiredDocumentsCatalog()));
    }
}

if (!function_exists('ensureCourseRequiredDocumentsColumn')) {
    function ensureCourseRequiredDocumentsColumn($conn): bool
    {
        if (!($conn instanceof mysqli)) {
            return false;
        }
        static $done = false;
        if ($done) {
            return true;
        }
        $check = $conn->query("SHOW COLUMNS FROM courses LIKE 'required_documents'");
        if ($check && $check->num_rows > 0) {
            $done = true;
            return true;
        }
        $ok = $conn->query(
            "ALTER TABLE courses
             ADD COLUMN required_documents TEXT NULL DEFAULT NULL
             COMMENT 'JSON array of required registration document field keys'"
        );
        if ($ok) {
            $done = true;
        }
        return (bool) $ok;
    }
}

if (!function_exists('normalizeCourseRequiredDocumentKeys')) {
    /**
     * @param mixed $raw
     * @return list<string>
     */
    function normalizeCourseRequiredDocumentKeys($raw): array
    {
        $allowed = array_flip(courseRequiredDocumentsAllowedKeys());
        $keys = [];

        if (is_string($raw)) {
            $raw = trim($raw);
            if ($raw === '') {
                return courseRequiredDocumentsDefaultKeys();
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return courseRequiredDocumentsDefaultKeys();
            }
            $raw = $decoded;
        }

        if (!is_array($raw)) {
            return courseRequiredDocumentsDefaultKeys();
        }

        foreach ($raw as $key) {
            $key = is_string($key) ? trim($key) : '';
            if ($key !== '' && isset($allowed[$key]) && !in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }
}

if (!function_exists('parseCourseRequiredDocumentsFromPost')) {
    /**
     * @param array<string,mixed> $post
     * @return list<string>
     */
    function parseCourseRequiredDocumentsFromPost(array $post): array
    {
        $raw = $post['required_documents'] ?? [];
        if (!is_array($raw)) {
            $raw = [];
        }
        return normalizeCourseRequiredDocumentKeys($raw);
    }
}

if (!function_exists('getCourseRequiredDocumentKeys')) {
    /**
     * @param mysqli $conn
     * @param array|int|null $course Course row or course id
     * @return list<string>
     */
    function getCourseRequiredDocumentKeys($conn, $course = null): array
    {
        ensureCourseRequiredDocumentsColumn($conn);

        $raw = null;
        if (is_array($course)) {
            if (array_key_exists('required_documents', $course)) {
                $raw = $course['required_documents'];
            } elseif (!empty($course['id']) && $conn instanceof mysqli) {
                $id = (int) $course['id'];
                $stmt = $conn->prepare('SELECT required_documents FROM courses WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $raw = $row['required_documents'] ?? null;
                }
            }
        } elseif (is_int($course) || (is_string($course) && ctype_digit($course))) {
            $id = (int) $course;
            if ($id > 0 && $conn instanceof mysqli) {
                $stmt = $conn->prepare('SELECT required_documents FROM courses WHERE id = ? LIMIT 1');
                if ($stmt) {
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $row = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    $raw = $row['required_documents'] ?? null;
                }
            }
        }

        if ($raw === null || $raw === '') {
            return courseRequiredDocumentsDefaultKeys();
        }

        return normalizeCourseRequiredDocumentKeys($raw);
    }
}

if (!function_exists('courseDocumentIsRequired')) {
    /**
     * @param list<string> $requiredKeys
     */
    function courseDocumentIsRequired(array $requiredKeys, string $field): bool
    {
        return in_array($field, $requiredKeys, true);
    }
}

if (!function_exists('courseDocumentRequiredMarkHtml')) {
    /**
     * @param list<string> $requiredKeys
     */
    function courseDocumentRequiredMarkHtml(array $requiredKeys, string $field): string
    {
        if (!courseDocumentIsRequired($requiredKeys, $field)) {
            return '';
        }
        return '<span class="required-mark">*</span>';
    }
}

if (!function_exists('courseDocumentRequiredAttr')) {
    /**
     * @param list<string> $requiredKeys
     */
    function courseDocumentRequiredAttr(array $requiredKeys, string $field): string
    {
        return courseDocumentIsRequired($requiredKeys, $field) ? 'required' : '';
    }
}

if (!function_exists('saveCourseRequiredDocuments')) {
    /**
     * @param list<string> $keys
     */
    function saveCourseRequiredDocuments($conn, int $courseId, array $keys): bool
    {
        if (!($conn instanceof mysqli) || $courseId <= 0) {
            return false;
        }
        ensureCourseRequiredDocumentsColumn($conn);
        $keys = normalizeCourseRequiredDocumentKeys($keys);
        $json = json_encode(array_values($keys), JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            $json = '[]';
        }
        $stmt = $conn->prepare('UPDATE courses SET required_documents = ? WHERE id = ?');
        if (!$stmt) {
            return false;
        }
        $stmt->bind_param('si', $json, $courseId);
        $ok = $stmt->execute();
        $stmt->close();
        return (bool) $ok;
    }
}

if (!function_exists('renderCourseRequiredDocumentsCheckboxes')) {
    /**
     * Admin UI checkboxes for required documents.
     *
     * @param list<string>|null $selectedKeys null = use defaults
     * @param string $inputName
     * @param string $idPrefix
     */
    function renderCourseRequiredDocumentsCheckboxes(?array $selectedKeys = null, string $inputName = 'required_documents[]', string $idPrefix = 'req_doc'): string
    {
        if ($selectedKeys === null) {
            $selectedKeys = courseRequiredDocumentsDefaultKeys();
        } else {
            $selectedKeys = normalizeCourseRequiredDocumentKeys($selectedKeys);
        }
        $selectedFlip = array_flip($selectedKeys);
        $groups = [];
        foreach (courseRequiredDocumentsCatalog() as $item) {
            $groups[$item['group']][] = $item;
        }

        $html = '<div class="course-required-docs-box" style="border:1px solid #dee2e6;border-radius:8px;padding:14px 16px;background:#f8fafc;">';
        $html .= '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:10px;">';
        $html .= '<div><strong><i class="fas fa-file-alt"></i> Required Documents for Registration</strong>';
        $html .= '<div class="text-muted" style="font-size:0.85rem;">Tick documents students must upload on the Apply / Register form for this course.</div></div>';
        $html .= '<div class="btn-group btn-group-sm" role="group">';
        $html .= '<button type="button" class="btn btn-outline-secondary" onclick="courseRequiredDocsSelectDefaults(this)">Default</button>';
        $html .= '<button type="button" class="btn btn-outline-secondary" onclick="courseRequiredDocsSelectAll(this)">All</button>';
        $html .= '<button type="button" class="btn btn-outline-secondary" onclick="courseRequiredDocsClear(this)">Clear</button>';
        $html .= '</div></div>';

        foreach ($groups as $groupName => $items) {
            $html .= '<div style="margin-top:10px;"><div style="font-weight:600;font-size:0.9rem;color:#334155;margin-bottom:6px;">'
                . htmlspecialchars($groupName) . '</div>';
            $html .= '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:6px 12px;">';
            foreach ($items as $item) {
                $id = $idPrefix . '_' . preg_replace('/[^a-z0-9_]/i', '_', $item['key']);
                $checked = isset($selectedFlip[$item['key']]) ? ' checked' : '';
                $html .= '<label class="form-check" style="margin:0;display:flex;align-items:flex-start;gap:8px;" for="'
                    . htmlspecialchars($id) . '">';
                $html .= '<input class="form-check-input course-required-doc-cb" type="checkbox" name="'
                    . htmlspecialchars($inputName) . '" id="' . htmlspecialchars($id) . '" value="'
                    . htmlspecialchars($item['key']) . '" data-default="'
                    . (!empty($item['default']) ? '1' : '0') . '"' . $checked . ' style="margin-top:3px;">';
                $html .= '<span>' . htmlspecialchars($item['label']);
                if (!empty($item['default'])) {
                    $html .= ' <small class="text-muted">(default)</small>';
                }
                $html .= '</span></label>';
            }
            $html .= '</div></div>';
        }

        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('courseRequiredDocumentsAdminScript')) {
    function courseRequiredDocumentsAdminScript(): string
    {
        return <<<'JS'
<script>
function courseRequiredDocsBoxFromBtn(btn) {
    return btn ? btn.closest('.course-required-docs-box') : null;
}
function courseRequiredDocsSelectDefaults(btn) {
    var box = courseRequiredDocsBoxFromBtn(btn);
    if (!box) return;
    box.querySelectorAll('.course-required-doc-cb').forEach(function (cb) {
        cb.checked = cb.getAttribute('data-default') === '1';
    });
}
function courseRequiredDocsSelectAll(btn) {
    var box = courseRequiredDocsBoxFromBtn(btn);
    if (!box) return;
    box.querySelectorAll('.course-required-doc-cb').forEach(function (cb) { cb.checked = true; });
}
function courseRequiredDocsClear(btn) {
    var box = courseRequiredDocsBoxFromBtn(btn);
    if (!box) return;
    box.querySelectorAll('.course-required-doc-cb').forEach(function (cb) { cb.checked = false; });
}
function courseRequiredDocsApplyKeys(box, keys) {
    if (!box) return;
    var set = {};
    (keys || []).forEach(function (k) { set[k] = true; });
    box.querySelectorAll('.course-required-doc-cb').forEach(function (cb) {
        cb.checked = !!set[cb.value];
    });
}
</script>
JS;
    }
}
