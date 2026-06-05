<?php
/**
 * Shared course category and sub-category definitions.
 * Single source of truth for dashboard, manage_courses, edit_course, and NSQF templates.
 */

if (!function_exists('get_course_main_categories')) {

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
            'NON-NSQF Course' => 'NON-NSQF Course',
            'Internship Program' => 'Internship Program',
            'Awareness Program' => 'Awareness Program',
            'FDP Program' => 'FDP Program',
            'Workshop' => 'Workshop',
            'GOVT/CORPORATE Training' => 'GOVT/CORPORATE Training',
        ];
    }

    /** Sub-categories available when creating NSQF course templates */
    function get_nsqf_template_sub_categories() {
        return [
            'NSQF Course' => 'NSQF Course',
            'NON-NSQF Course' => 'NON-NSQF Course',
        ];
    }

    /** Sub-categories that map to category (hide main category field) */
    function get_special_subcategories() {
        return [
            'Internship Program',
            'Awareness Program',
            'FDP Program',
            'Workshop',
            'GOVT/CORPORATE Training',
        ];
    }

    /** Legacy labels still stored in older DB rows */
    function get_legacy_course_categories() {
        return [
            'NSQF',
            'Long Term NSQF',
            'Short Term NSQF',
            'Internship Program',
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
            $sel = ($selected === $value) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($value) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
        }
        return $html;
    }
}
