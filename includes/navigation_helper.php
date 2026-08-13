<?php
/**
 * Navigation Menu Helper Functions
 * Provides functions to load and render navigation menu from database
 */

require_once __DIR__ . '/url_helper.php';

/**
 * Get all active navigation menu items organized by parent-child relationship
 * @param mysqli $conn Database connection
 * @return array Hierarchical array of menu items
 */
function getNavigationMenu($conn) {
    // Fetch all active menu items ordered by display_order
    $sql = "SELECT * FROM navigation_menu WHERE is_active = 1 ORDER BY display_order ASC";
    $result = $conn->query($sql);
    
    if (!$result) {
        return [];
    }
    
    $menu_items = [];
    $children = [];
    
    // Organize items by parent-child relationship
    while ($item = $result->fetch_assoc()) {
        if ($item['parent_id'] === null) {
            // Top-level item
            $menu_items[$item['id']] = $item;
            $menu_items[$item['id']]['children'] = [];
        } else {
            // Child item
            if (!isset($children[$item['parent_id']])) {
                $children[$item['parent_id']] = [];
            }
            $children[$item['parent_id']][] = $item;
        }
    }
    
    // Attach children to their parents
    foreach ($children as $parent_id => $child_items) {
        if (isset($menu_items[$parent_id])) {
            $menu_items[$parent_id]['children'] = $child_items;
        }
    }
    
    return array_values($menu_items);
}

/**
 * Render navigation menu HTML
 * @param array $menu_items Array of menu items from getNavigationMenu()
 * @param string $current_page Current page filename for active state
 * @return string HTML markup for navigation menu
 */
function renderNavigationMenu($menu_items, $current_page = '') {
    if (empty($menu_items)) {
        return '';
    }
    
    $html = '';
    
    foreach ($menu_items as $item) {
        $has_children = !empty($item['children']);
        $is_active = ($current_page && strpos($item['url'], $current_page) !== false) ? 'active' : '';
        
        if ($has_children) {
            // Parent item with dropdown
            $html .= '<li class="nav-item dropdown">';
            $html .= '<a class="nav-link dropdown-toggle ' . $is_active . '" href="' . htmlspecialchars(clean_menu_href($item['url'])) . '" data-bs-toggle="dropdown">';
            $html .= htmlspecialchars($item['label']);
            $html .= '</a>';
            $html .= '<ul class="dropdown-menu">';
            
            foreach ($item['children'] as $child) {
                $html .= '<li>';
                $html .= '<a class="dropdown-item" href="' . htmlspecialchars(clean_menu_href($child['url'])) . '" target="' . htmlspecialchars($child['target']) . '">';
                $html .= htmlspecialchars($child['label']);
                $html .= '</a>';
                $html .= '</li>';
            }
            
            $html .= '</ul>';
            $html .= '</li>';
        } else {
            // Single item without dropdown
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link ' . $is_active . '" href="' . htmlspecialchars(clean_menu_href($item['url'])) . '" target="' . htmlspecialchars($item['target']) . '">';
            $html .= htmlspecialchars($item['label']);
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    
    return $html;
}

/**
 * Labels that must not appear on the public website navbar.
 */
function publicNavLabelIsExcluded(string $label): bool
{
    $label = strtolower(trim($label));
    if ($label === '') {
        return false;
    }

    return strpos($label, 'pm shri') !== false
        || strpos($label, 'kv jnv') !== false
        || $label === 'management'
        || $label === 'course registration'
        || $label === 'mock test portal'
        || strpos($label, 'organisational structure') !== false
        || strpos($label, 'organizational structure') !== false;
}

/**
 * URLs that must not appear on the public website navbar.
 */
function publicNavUrlIsExcluded(string $url): bool
{
    $url = strtolower(str_replace('\\', '/', trim($url)));
    if ($url === '') {
        return false;
    }

    return (bool) preg_match('#(^|/)(public/)?management(\.php)?(/|$|\?)#', $url)
        || (bool) preg_match('#membership_form#', $url);
}

/**
 * Remove PM SHRI / Management entries from a hierarchical navigation menu.
 *
 * @param array<int, array<string, mixed>> $menuItems
 * @return array<int, array<string, mixed>>
 */
function filterPublicNavigationMenuItems(array $menuItems): array
{
    $filtered = [];

    foreach ($menuItems as $item) {
        $label = (string) ($item['label'] ?? '');
        $url = (string) ($item['url'] ?? '');

        if (publicNavLabelIsExcluded($label) || publicNavUrlIsExcluded($url)) {
            continue;
        }

        if (!empty($item['children']) && is_array($item['children'])) {
            $children = [];
            foreach ($item['children'] as $child) {
                $childLabel = (string) ($child['label'] ?? '');
                $childUrl = (string) ($child['url'] ?? '');
                if (publicNavLabelIsExcluded($childLabel) || publicNavUrlIsExcluded($childUrl)) {
                    continue;
                }
                $children[] = $child;
            }
            $item['children'] = $children;
        }

        $filtered[] = $item;
    }

    return array_values($filtered);
}

/**
 * Shared public-site navbar items (homepage + public pages).
 * Uses DB menu when available, otherwise a consistent fallback.
 */
function getPublicSiteNavigationHtml($conn = null, string $currentPage = ''): string
{
    $html = '';

    if ($conn instanceof mysqli && navigationMenuTableExists($conn)) {
        $menuItems = getNavigationMenu($conn);
        $menuItems = filterPublicNavigationMenuItems($menuItems);
        $menuItems = ensurePublicAboutNavigationItems($menuItems);
        $html = renderNavigationMenu($menuItems, $currentPage);
    }

    if (trim($html) === '') {
        $html = getFallbackNavigationMenu($currentPage);
    }

    return $html;
}

/**
 * Ensure About dropdown always includes Our Team and News (never Management).
 *
 * @param array<int, array<string, mixed>> $menuItems
 * @return array<int, array<string, mixed>>
 */
function ensurePublicAboutNavigationItems(array $menuItems): array
{
    $required = [
        'Our Team' => app_url('public/team'),
        'News' => app_url('public/news'),
    ];

    $aboutIndex = null;
    foreach ($menuItems as $index => $item) {
        if (strcasecmp(trim((string) ($item['label'] ?? '')), 'About') === 0) {
            $aboutIndex = $index;
            break;
        }
    }

    if ($aboutIndex === null) {
        $menuItems[] = [
            'id' => 0,
            'label' => 'About',
            'url' => '#',
            'target' => '_self',
            'children' => [],
        ];
        $aboutIndex = count($menuItems) - 1;
    }

    $children = $menuItems[$aboutIndex]['children'] ?? [];
    if (!is_array($children)) {
        $children = [];
    }

    $existingLabels = [];
    foreach ($children as $child) {
        $existingLabels[strtolower(trim((string) ($child['label'] ?? '')))] = true;
    }

    foreach ($required as $label => $url) {
        if (!isset($existingLabels[strtolower($label)])) {
            $children[] = [
                'id' => 0,
                'label' => $label,
                'url' => $url,
                'target' => '_self',
            ];
        }
    }

    $menuItems[$aboutIndex]['children'] = $children;
    return $menuItems;
}

/**
 * Check if navigation menu table exists
 * @param mysqli $conn Database connection
 * @return bool True if table exists, false otherwise
 */
function navigationMenuTableExists($conn) {
    $result = $conn->query("SHOW TABLES LIKE 'navigation_menu'");
    return $result && $result->num_rows > 0;
}

/**
 * Official NIELIT Bhubaneswar Job Fair portal URL.
 */
function getJobFairPortalUrl() {
    return 'https://jobfair.nielitbhubaneswar.in/';
}

/**
 * Official NIELIT Bhubaneswar Mock Test portal URL.
 */
function getMockTestPortalUrl() {
    return 'https://test.nielitbhubaneswar.in/';
}

/**
 * Get fallback hardcoded navigation menu (for backward compatibility)
 * @param string $currentPage Current page filename for active state
 * @return string HTML markup for hardcoded navigation menu
 */
function getFallbackNavigationMenu($currentPage = '') {
    $job_fair_url = htmlspecialchars(getJobFairPortalUrl(), ENT_QUOTES, 'UTF-8');
    $mock_test_url = htmlspecialchars(getMockTestPortalUrl(), ENT_QUOTES, 'UTF-8');
    $currentPage = strtolower(basename((string) $currentPage));

    $is = static function (string $needle) use ($currentPage): string {
        return ($currentPage !== '' && strpos($currentPage, $needle) !== false) ? ' active' : '';
    };

    $aboutActive = ($is('team') || $is('news')) ? ' active' : '';

    return '
        <li class="nav-item"><a class="nav-link' . ($currentPage === '' || $currentPage === 'index.php' ? ' active' : '') . '" href="' . htmlspecialchars(app_url('index'), ENT_QUOTES, 'UTF-8') . '">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="' . $job_fair_url . '" target="_blank" rel="noopener">Job Fair</a></li>
        <li class="nav-item"><a class="nav-link" href="' . $mock_test_url . '" target="_blank" rel="noopener">Mock Test</a></li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle' . $is('courses') . '" href="#" data-bs-toggle="dropdown">Student Zone</a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="' . htmlspecialchars(app_url('public/courses'), ENT_QUOTES, 'UTF-8') . '">Courses Offered</a></li>
                <li><a class="dropdown-item" href="' . htmlspecialchars(app_url('student/login'), ENT_QUOTES, 'UTF-8') . '">Student Portal</a></li>
            </ul>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle' . $aboutActive . '" href="#" data-bs-toggle="dropdown">About</a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item' . $is('team') . '" href="' . htmlspecialchars(app_url('public/team'), ENT_QUOTES, 'UTF-8') . '">Our Team</a></li>
                <li><a class="dropdown-item' . $is('news') . '" href="' . htmlspecialchars(app_url('public/news'), ENT_QUOTES, 'UTF-8') . '">News</a></li>
            </ul>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle' . $is('login') . '" href="#" data-bs-toggle="dropdown">Admin</a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="' . htmlspecialchars(app_url('admin/login'), ENT_QUOTES, 'UTF-8') . '">Admin Login</a></li>
                <li><a class="dropdown-item" href="' . htmlspecialchars(app_url('faculty/login'), ENT_QUOTES, 'UTF-8') . '">Faculty Login</a></li>
                <li><a class="dropdown-item" href="/Salary_Slip/login">Finance Login</a></li>
                <li><a class="dropdown-item" href="/Certificate/index">Certificate</a></li>
            </ul>
        </li>
        <li class="nav-item"><a class="nav-link' . $is('contact') . '" href="' . htmlspecialchars(app_url('public/contact'), ENT_QUOTES, 'UTF-8') . '">Contact</a></li>
    ';
}
