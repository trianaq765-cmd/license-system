<?php
// Konfigurasi dari Environment Variables (Render)
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'license_system');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('SECRET_KEY', getenv('SECRET_KEY') ?: 'your-default-secret-key-123');

// Konfigurasi Lisensi
define('LICENSE_PREFIX', [
    'TRIAL' => 'TR',
    'BASIC' => 'BS',
    'PRO' => 'PR',
    'ENTERPRISE' => 'EN',
    'LIFETIME' => 'LT'
]);

define('DEFAULT_EXPIRY_DAYS', [
    'TRIAL' => 30,
    'BASIC' => 365,
    'PRO' => 365,
    'ENTERPRISE' => 730,
    'LIFETIME' => null
]);
