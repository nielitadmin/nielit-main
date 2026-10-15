<?php
/**
 * Institute display name — single source of truth (tracked in git for production deploy).
 */
if (!defined('INSTITUTE_NAME_EN')) {
    define(
        'INSTITUTE_NAME_EN',
        'National Institute of Electronics & Information Technology, Bhubaneswar | Raipur | Baleshwar'
    );
}

if (!defined('INSTITUTE_NAME_HI')) {
    define(
        'INSTITUTE_NAME_HI',
        'राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान, भुवनेश्वर | रायपुर | बालेश्वर'
    );
}

if (!defined('INSTITUTE_NAME_HI_FORMAL')) {
    define(
        'INSTITUTE_NAME_HI_FORMAL',
        'राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान (रा.इ.सू.प्रौ. सं) भुवनेश्वर | रायपुर | बालेश्वर'
    );
}

if (!defined('NIELIT_BALESHWAR_EXTENSION')) {
    define('NIELIT_BALESHWAR_EXTENSION', 'NIELIT Baleshwar Extension');
}

if (!defined('SUPPORT_ENQUIRY_EMAIL')) {
    define('SUPPORT_ENQUIRY_EMAIL', 'admin@nielitbhubaneswar.in');
}

if (!function_exists('is_nielit_baleshwar_location')) {
    function is_nielit_baleshwar_location($location): bool
    {
        return in_array((string) $location, [
            'NIELIT Balasore',
            'NIELIT Balasore Extension',
            'NIELIT Baleshwar',
            NIELIT_BALESHWAR_EXTENSION,
        ], true);
    }
}

if (!function_exists('normalize_nielit_centre_name')) {
    function normalize_nielit_centre_name($name): string
    {
        $name = trim((string) $name);
        $aliases = [
            'NIELIT Balasore Extension' => NIELIT_BALESHWAR_EXTENSION,
            'NIELIT Balasore Extension Centre' => NIELIT_BALESHWAR_EXTENSION,
            'NIELIT Balasore' => NIELIT_BALESHWAR_EXTENSION,
            'NIELIT Baleshwar' => NIELIT_BALESHWAR_EXTENSION,
            'NIELIT Baleshwar Extension Centre' => NIELIT_BALESHWAR_EXTENSION,
        ];

        return $aliases[$name] ?? $name;
    }
}

if (!function_exists('normalize_nielit_batch_location')) {
    function normalize_nielit_batch_location($location): string
    {
        if (is_nielit_baleshwar_location($location)) {
            return NIELIT_BALESHWAR_EXTENSION;
        }

        $location = trim((string) $location);
        return $location !== '' ? $location : 'NIELIT Bhubaneswar';
    }
}

if (!function_exists('nielit_location_centre_label')) {
    function nielit_location_centre_label($location): string
    {
        $location = normalize_nielit_batch_location($location);
        if (is_nielit_baleshwar_location($location)) {
            return 'Baleshwar';
        }
        if (stripos($location, 'raipur') !== false) {
            return 'Raipur';
        }
        if (preg_match('/^NIELIT\s+(.+)$/i', $location, $match)) {
            $label = trim((string) $match[1]);
            $label = preg_replace('/\s+Extension(?:\s+Centre)?$/i', '', $label);
            if ($label !== '' && strcasecmp($label, 'Bhubaneswar') !== 0) {
                return $label;
            }
        }
        return 'Bhubaneswar';
    }
}

if (!function_exists('nielit_batch_location_options')) {
    function nielit_batch_location_options($conn, $current = ''): array
    {
        $options = [];
        if ($conn instanceof mysqli) {
            $result = $conn->query('SELECT name FROM centres WHERE is_active = 1 ORDER BY name ASC');
            if ($result) {
                while ($row = $result->fetch_assoc()) {
                    $name = normalize_nielit_centre_name(trim((string) ($row['name'] ?? '')));
                    if ($name !== '') {
                        $options[$name] = $name;
                    }
                }
            }
        }

        foreach (['NIELIT Bhubaneswar', NIELIT_BALESHWAR_EXTENSION, 'NIELIT Raipur'] as $required) {
            $options[$required] = $required;
        }

        $current = normalize_nielit_batch_location($current);
        if ($current !== '' && !isset($options[$current])) {
            $options[$current] = $current;
        }

        $preferred = ['NIELIT Bhubaneswar', NIELIT_BALESHWAR_EXTENSION, 'NIELIT Raipur'];
        $ordered = [];
        foreach ($preferred as $name) {
            if (isset($options[$name])) {
                $ordered[] = $name;
                unset($options[$name]);
            }
        }
        $rest = array_values($options);
        natcasesort($rest);
        return array_merge($ordered, array_values($rest));
    }
}

if (!defined('MINISTRY_NAME_HI')) {
    define(
        'MINISTRY_NAME_HI',
        'इलेक्ट्रॉनिकी और सूचना प्रौद्योगिकी मंत्रालय'
    );
}

if (!defined('MINISTRY_NAME_EN')) {
    define(
        'MINISTRY_NAME_EN',
        'Ministry of Electronics & Information Technology'
    );
}

require_once __DIR__ . '/visitor_counter.php';
