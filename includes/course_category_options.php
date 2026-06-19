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

    /** Sub-categories that map to category (hide main category field) */
    function get_special_subcategories() {
        return [
            'Internship Program',
            'Awareness Program',
            'FDP Program',
            'Workshop',
            'Govt/Corporate Training',
        ];
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
