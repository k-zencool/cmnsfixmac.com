<?php
// includes/ua_parser.php
// Dependency-free User-Agent -> device label parser (no external lib/build step).
// Covers the common cases; falls back gracefully for anything unrecognized.

if (!function_exists('parse_device_label')) {
    function parse_device_label(string $ua): string
    {
        if (trim($ua) === '') return 'ไม่ทราบอุปกรณ์';

        // Browser — order matters: Edge/Opera/Chrome UAs all also contain "Safari"
        if (preg_match('/Edg\//i', $ua))                 $browser = 'Edge';
        elseif (preg_match('/OPR\//i', $ua))              $browser = 'Opera';
        elseif (preg_match('/CriOS/i', $ua))              $browser = 'Chrome'; // Chrome on iOS
        elseif (preg_match('/Chrome\//i', $ua))           $browser = 'Chrome';
        elseif (preg_match('/FxiOS/i', $ua))              $browser = 'Firefox'; // Firefox on iOS
        elseif (preg_match('/Firefox\//i', $ua))          $browser = 'Firefox';
        elseif (preg_match('/Version\/.*Safari/i', $ua))  $browser = 'Safari';
        elseif (preg_match('/Safari/i', $ua))             $browser = 'Safari';
        else                                              $browser = 'เบราว์เซอร์อื่น';

        // OS / device
        if (preg_match('/iPhone/i', $ua))                 $os = 'iPhone';
        elseif (preg_match('/iPad/i', $ua))                $os = 'iPad';
        elseif (preg_match('/Android/i', $ua))             $os = 'Android';
        elseif (preg_match('/Macintosh|Mac OS X/i', $ua))  $os = 'macOS';
        elseif (preg_match('/Windows/i', $ua))             $os = 'Windows';
        elseif (preg_match('/Linux/i', $ua))               $os = 'Linux';
        else                                                $os = '';

        return $os ? "{$browser} · {$os}" : $browser;
    }
}
