<?php
/**
 * Shared course category and sub-category definitions.
 * Single source of truth for dashboard, manage_courses, edit_course, and NSQF templates.
 */

if (!function_exists('get_course_main_categories')) {

    function get_default_non_nsqf_sub_category() {
        return 'Non-NSQF Course';
    }

    function get_govt_corporate_training_label() {
        return 'Govt/Corporate Training';
    }

    /** Maps legacy stored values to current canonical labels */
    function get_legacy_sub_category_map() {
        return [
            'NON-NSQF Course' => 'Non-NSQF Course',
            'NON-NSQF' => 'Non-NSQF',
            'GOVT/CORPORATE Training' => 'Govt/Corporate Training',
            'Bootcamp' => 'Boot Camps',
            'Boot Camp' => 'Boot Camps',
            'Bootcamp Program' => 'Boot Camps',
        ];
    }

    function normalize_course_sub_category($value) {
        if ($value === null || $value === '') {
            return $value;
        }
        $trimmed = trim((string) $value);
        $map = get_legacy_sub_category_map();
        return $map[$trimmed] ?? $trimmed;
    }

    function is_nsqf_course_sub_category($value) {
        return normalize_course_sub_category($value) === 'NSQF Course';
    }

    function sub_category_matches($value, $canonical) {
        return normalize_course_sub_category($value) === normalize_course_sub_category($canonical);
    }

    function is_special_subcategory($value) {
        $normalized = normalize_course_sub_category($value);
        return in_array($normalized, get_special_subcategories(), true);
    }

    /** Values that match a filter for a canonical sub-category (includes legacy DB rows) */
    function get_sub_category_filter_values($canonical) {
        $canonical = normalize_course_sub_category($canonical);
        $values = [$canonical];
        foreach (get_legacy_sub_category_map() as $legacy => $normalized) {
            if ($normalized === $canonical) {
                $values[] = $legacy;
            }
        }
        return array_values(array_unique($values));
    }

    function sub_category_option_selected($selected, $value) {
        return sub_category_matches($selected, $value) ? ' selected' : '';
    }

    function get_course_main_categories() {
        return [
            'Degree / Diploma / PG' => 'Degree / Diploma Courses / PG',
            'Skill Based (Long Term) >500 hrs' => 'Skill Based (Long Term) Courses > 500 hrs',
            'Skill Based (Short Term) 90-500 hrs' => 'Skill Based (Short Term) Courses >90 hrs to <=500 hrs',
            'Short Term / Digital Competency <=90 hrs' => 'Short Term Courses / Digital Competency Courses <= 90 hours',
            'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)' => "NIELIT HQ's Digital Literacy Courses (CCC / ECC / CCCP / BCC / ACC)",
        ];
    }

    function get_course_sub_categories() {
        return [
            'NSQF Course' => 'NSQF Course',
            'Non-NSQF Course' => 'Non-NSQF Course',
            'Internship Program' => 'Internship Program',
            'Boot Camps' => 'Boot Camps',
            'Awareness Program' => 'Awareness Program',
            'FDP Program' => 'FDP Program',
            'Workshop' => 'Workshop',
            'Govt/Corporate Training' => 'Govt/Corporate Training',
        ];
    }

    /** Sub-categories available when creating NSQF course templates */
    function get_nsqf_template_sub_categories() {
        return [
            'NSQF Course' => 'NSQF Course',
            'Non-NSQF Course' => 'Non-NSQF Course',
        ];
    }

    /** Sub-categories that are program types (not main skill categories). */
    function get_special_subcategories() {
        return [
            'Internship Program',
            'Boot Camps',
            'Awareness Program',
            'FDP Program',
            'Workshop',
            'Govt/Corporate Training',
        ];
    }

    function ensure_course_sub_category_column($conn): void {
        if (!($conn instanceof mysqli)) {
            return;
        }
        @$conn->query(
            "ALTER TABLE courses ADD COLUMN IF NOT EXISTS course_sub_category VARCHAR(80) NULL DEFAULT NULL AFTER category"
        );
    }

    function resolve_course_main_category_from_row(array $course): string {
        foreach (['category', 'course_type'] as $field) {
            $value = trim((string) ($course[$field] ?? ''));
            if ($value !== '' && in_array($value, array_keys(get_course_main_categories()), true)) {
                return $value;
            }
        }

        $legacyCategory = trim((string) ($course['category'] ?? ''));
        if ($legacyCategory !== '' && !is_special_subcategory($legacyCategory)) {
            return $legacyCategory;
        }

        return '';
    }

    function resolve_course_sub_category_from_row(array $course): string {
        $stored = trim((string) ($course['course_sub_category'] ?? ''));
        if ($stored !== '') {
            return normalize_course_sub_category($stored);
        }

        if (!empty($course['is_nsqf']) && (int) $course['is_nsqf'] === 1) {
            return 'NSQF Course';
        }

        $legacyCategory = trim((string) ($course['category'] ?? ''));
        if ($legacyCategory !== '' && is_special_subcategory($legacyCategory)) {
            return normalize_course_sub_category($legacyCategory);
        }

        $legacyType = trim((string) ($course['course_type'] ?? ''));
        if ($legacyType !== '' && is_special_subcategory($legacyType)) {
            return normalize_course_sub_category($legacyType);
        }

        return get_default_non_nsqf_sub_category();
    }

    /** Legacy labels still stored in older DB rows */
    function get_legacy_course_categories() {
        return [
            'NSQF',
            'Long Term NSQF',
            'Short Term NSQF',
            'Internship Program',
            'GOVT/CORPORATE Training',
            'Skill Based (Long Term) Courses (> 500 hrs)',
            'Skill Based (Short Term) Courses (90-500 hrs)',
            'Short Term / Digital Competency Courses (<= 90 hrs)',
            'NIELIT HQ Digital Literacy Courses (CCC/ECC/BCC/ACC)',
        ];
    }

    function get_all_valid_nsqf_template_categories() {
        return array_values(array_unique(array_merge(
            array_keys(get_course_main_categories()),
            get_legacy_course_categories()
        )));
    }

    /**
     * All DB category values that should match a selected course category
     * (canonical keys, display labels, and legacy stored values).
     */
    function get_equivalent_template_categories($category) {
        $category = trim((string) $category);
        if ($category === '' || $category === 'NSQF') {
            return [];
        }

        $values = [$category];

        foreach (get_course_main_categories() as $key => $label) {
            if ($category === $key || $category === $label) {
                $values[] = $key;
                $values[] = $label;
            }
        }

        $legacy_groups = [
            'Degree / Diploma / PG' => [
                'Degree / Diploma / PG',
            ],
            'Skill Based (Long Term) >500 hrs' => [
                'Long Term NSQF',
                'Skill Based (Long Term) Courses (> 500 hrs)',
            ],
            'Skill Based (Short Term) 90-500 hrs' => [
                'Short Term NSQF',
                'Skill Based (Short Term) Courses (90-500 hrs)',
                'Skill Based (Short Term) Courses >90 hrs to <=500 hrs',
            ],
            'Short Term / Digital Competency <=90 hrs' => [
                'Short Term NSQF',
                'Short Term / Digital Competency Courses (<= 90 hrs)',
            ],
            'NIELIT HQ Digital Literacy (CCC/ECC/BCC/ACC)' => [
                'NIELIT HQ Digital Literacy Courses (CCC/ECC/BCC/ACC)',
            ],
        ];

        $canonical = null;
        foreach ($legacy_groups as $canonical_key => $legacy_values) {
            $group_values = array_merge([$canonical_key], $legacy_values);
            if (in_array($category, $group_values, true)) {
                $canonical = $canonical_key;
                break;
            }
        }

        if ($canonical === null) {
            foreach (get_course_main_categories() as $key => $label) {
                if ($category === $key || $category === $label) {
                    $canonical = $key;
                    break;
                }
            }
        }

        if ($canonical !== null) {
            $values[] = $canonical;
            if (isset($legacy_groups[$canonical])) {
                $values = array_merge($values, $legacy_groups[$canonical]);
            }
            if (isset(get_course_main_categories()[$canonical])) {
                $values[] = get_course_main_categories()[$canonical];
            }
        }

        foreach (get_legacy_course_categories() as $legacy) {
            if ($legacy === $category) {
                $values[] = $legacy;
            }
        }

        return array_values(array_unique($values));
    }

    function render_course_category_options($selected = '', $placeholder = '--Select Category--') {
        $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        foreach (get_course_main_categories() as $value => $label) {
            $sel = ($selected === $value) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        return $html;
    }

    function render_course_sub_category_options($selected = '', $placeholder = '--Select Sub-Category--', $nsqf_template_mode = false) {
        $categories = $nsqf_template_mode ? get_nsqf_template_sub_categories() : get_course_sub_categories();
        $html = '<option value="">' . htmlspecialchars($placeholder) . '</option>';
        foreach ($categories as $value => $label) {
            $sel = sub_category_option_selected($selected, $value);
            $html .= '<option value="' . htmlspecialchars($value) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        return $html;
    }

    function format_course_sub_category_display($value) {
        return normalize_course_sub_category($value ?? get_default_non_nsqf_sub_category());
    }
}
