<?php
/**
 * Restructure courses.php to reorder sections:
 * OLD: Page Header -> New 6 Categories -> Training Centre -> Old Categories
 * NEW: Page Header -> Training Centre -> New 6 Categories -> Footer
 */

$file_path = __DIR__ . '/public/courses.php';
$content = file_get_contents($file_path);

// Find critical line positions
$pos_page_header_end = strpos($content, '<!-- Additional Course Categories -->');
$pos_new_categories_start = $pos_page_header_end;
$pos_new_categories_end = strpos($content, '<!-- Filter Section -->');
$pos_training_centre_start = $pos_new_categories_end;
$pos_training_centre_end = strpos($content, '<!-- Courses Offered Section -->');
$pos_old_sections_start = $pos_training_centre_end;
$pos_footer_start = strpos($content, '<!-- Footer (Matching Index.php) -->');

// Extract sections
$section_page_header = substr($content, 0, $pos_page_header_end);
$section_new_categories = substr($content, $pos_new_categories_start, $pos_new_categories_end - $pos_new_categories_start);
$section_training_centre = substr($content, $pos_training_centre_start, $pos_training_centre_end - $pos_training_centre_start);
$section_footer = substr($content, $pos_footer_start);

// Reconstruct in new order
$new_content = $section_page_header . 
               $section_training_centre . 
               $section_new_categories .
               "\n\n" . 
               $section_footer;

// Update quick nav buttons to remove old section references
// They should only link to the 6 new sections
$quick_nav_updates = array(
    'long-term' => 'degree-pg',  // Remove old Long Term NSQF button
    'short-term' => 'skill-long', // Remove old Short Term NSQF button
    'non-nsqf' => 'skill-short',  // Remove old Non-NSQF button
    'internship' => 'digital-lit', // Keep Internship but point to new sections
);

// Actually, better to just remove those buttons entirely
// Find and remove the old nav buttons from the quick nav section

// Quick nav buttons to remove (for old sections)
$buttons_to_remove = array(
    '<button class="quick-nav-btn" onclick="scrollToSection(\'long-term\')"[^>]*>.*?Long Term NSQF.*?<\/button>',
    '<button class="quick-nav-btn" onclick="scrollToSection(\'short-term\')"[^>]*>.*?Short Term NSQF.*?<\/button>',
    '<button class="quick-nav-btn" onclick="scrollToSection(\'non-nsqf\')"[^>]*>.*?Non-NSQF.*?<\/button>',
    '<button class="quick-nav-btn" onclick="scrollToSection(\'internship\')"[^>]*>.*?Internship.*?<\/button>'
);

foreach ($buttons_to_remove as $pattern) {
    $new_content = preg_replace('/' . $pattern . '/s', '', $new_content);
}

// Also remove the old section switch case statements from scrollToSection function
$new_content = preg_replace('/case \'long-term\':.*?break;/s', '', $new_content);
$new_content = preg_replace('/case \'short-term\':.*?break;/s', '', $new_content);
$new_content = preg_replace('/case \'non-nsqf\':.*?break;/s', '', $new_content);
// Keep internship case as we have a new one

// Write back
file_put_contents($file_path, $new_content);

echo "Restructuring complete!\n";
echo "File size: " . strlen($new_content) . " bytes\n";
echo "Line count: " . count(explode("\n", $new_content)) . " lines\n";
?>
