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

if (!function_exists('is_nielit_baleshwar_location')) {
    function is_nielit_baleshwar_location($location): bool
    {
        return in_array((string) $location, ['NIELIT Balasore', 'NIELIT Baleshwar'], true);
    }
}

if (!function_exists('nielit_location_centre_label')) {
    function nielit_location_centre_label($location): string
    {
        return is_nielit_baleshwar_location($location) ? 'Baleshwar' : 'Bhubaneswar';
    }
}

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
