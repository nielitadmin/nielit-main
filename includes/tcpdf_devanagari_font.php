<?php
/**
 * Register Devanagari (Hindi) font for TCPDF.
 */

if (!function_exists('getTcpdfDevanagariFontName')) {

    function getTcpdfDevanagariFontName(): ?string {
        static $cached = null;
        if ($cached !== null) {
            return $cached === false ? null : $cached;
        }

        require_once __DIR__ . '/../libraries/tcpdf/tcpdf.php';

        $fontFile = __DIR__ . '/../assets/fonts/NotoSansDevanagari-Regular.ttf';
        if (!is_file($fontFile)) {
            $cached = false;
            return null;
        }

        $fontName = TCPDF_FONTS::addTTFfont($fontFile, 'TrueTypeUnicode', '', 32);
        if (!$fontName) {
            $cached = false;
            return null;
        }

        $cached = $fontName;
        return $fontName;
    }

    function getAdmissionFormHindiTexts(): array {
        if (!defined('INSTITUTE_NAME_HI')) {
            require_once __DIR__ . '/institute_branding.php';
        }
        return [
            'institute_line' => INSTITUTE_NAME_HI,
            'declaration' => 'मैं एतद्द्वारा घोषणा करता/करती हूं कि ऊपर दी गई जानकारी मेरी जानकारी के अनुसार सत्य और सही है। मैं समझता/समझती हूं कि कोई भी गलत जानकारी मेरे प्रवेश/प्रंजीकरण को रद्द कर सकती है।',
        ];
    }
}
