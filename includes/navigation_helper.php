<?php
/**
 * Navigation Menu Helper Functions
 * Provides functions to load and render navigation menu from database
 */

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
            $html .= '<a class="nav-link dropdown-toggle ' . $is_active . '" href="' . htmlspecialchars($item['url']) . '" data-bs-toggle="dropdown">';
            $html .= htmlspecialchars($item['label']);
            $html .= '</a>';
            $html .= '<ul class="dropdown-menu">';
            
            foreach ($item['children'] as $child) {
                $html .= '<li>';
                $html .= '<a class="dropdown-item" href="' . htmlspecialchars($child['url']) . '" target="' . htmlspecialchars($child['target']) . '">';
                $html .= htmlspecialchars($child['label']);
                $html .= '</a>';
                $html .= '</li>';
            }
            
            $html .= '</ul>';
            $html .= '</li>';
        } else {
            // Single item without dropdown
            $html .= '<li class="nav-item">';
            $html .= '<a class="nav-link ' . $is_active . '" href="' . htmlspecialchars($item['url']) . '" target="' . htmlspecialchars($item['target']) . '">';
            $html .= htmlspecialchars($item['label']);
            $html .= '</a>';
            $html .= '</li>';
        }
    }
    
    return $html;
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
 * Get fallback hardcoded navigation menu (for backward compatibility)
 * @return string HTML markup for hardcoded navigation menu
 */
function getFallbackNavigationMenu() {
    return '
        <li class="nav-item"><a class="nav-link active" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="DGR/index.php">Job Fair</a></li>
        
        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">PM SHRI KV JNV</a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="Membership_Form/index.php">Membership Form</a></li>
            </ul>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Student Zone</a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="public/courses.php">Courses Offered</a></li>
                <li><a class="dropdown-item" href="student/login.php">Student Portal</a></li>
            </ul>
        </li>

        <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Admin</a>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="admin/login.php">Admin Login</a></li>
                <li><a class="dropdown-item" href="/Salary_Slip/login.php">Finance Login</a></li>
                <li><a class="dropdown-item" href="/Nielit_Project/index.php">Certificate</a></li>
            </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="public/contact.php">Contact</a></li>
    ';
}
