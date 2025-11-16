<?php
/**
 * Application Configuration
 * 
 * This file contains application-wide settings.
 */

// Application name
define('APP_NAME', 'Blood Bank Portal');

// Application version
define('APP_VERSION', '1.0.0');

// Default timezone
date_default_timezone_set('Asia/Manila');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for development, 0 for production

// Session settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 if using HTTPS

// Path settings
define('BASE_PATH', dirname(__DIR__));
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PAGES_PATH', BASE_PATH . '/pages');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URL settings
define('BASE_URL', '/blood_bank_portal/'); // Update this to match your server configuration

// Organization settings
define('ORGANIZATIONS', [
    'Red Cross' => [
        'name' => 'Red Cross',
        'logo' => 'assets/images/red-cross-logo.png',
        'address' => '123 Main Street, City Center',
        'phone' => '(123) 456-7890',
        'hours' => '8:00 AM - 5:00 PM (Mon-Sat)',
        'theme' => 'red-cross-theme'
    ],
    'Negros First' => [
        'name' => 'Negros First Blood Bank',
        'logo' => 'assets/images/negros-first-logo.png',
        'address' => '456 Health Avenue, Bacolod City',
        'phone' => '(123) 456-7891',
        'hours' => '9:00 AM - 6:00 PM (Mon-Fri)',
        'theme' => 'negros-first-theme'
    ]
]);

// Blood type settings
define('BLOOD_TYPES', [
    'A+' => [
        'name' => 'A+',
        'can_donate_to' => ['A+', 'AB+'],
        'can_receive_from' => ['A+', 'A-', 'O+', 'O-']
    ],
    'A-' => [
        'name' => 'A-',
        'can_donate_to' => ['A+', 'A-', 'AB+', 'AB-'],
        'can_receive_from' => ['A-', 'O-']
    ],
    'B+' => [
        'name' => 'B+',
        'can_donate_to' => ['B+', 'AB+'],
        'can_receive_from' => ['B+', 'B-', 'O+', 'O-']
    ],
    'B-' => [
        'name' => 'B-',
        'can_donate_to' => ['B+', 'B-', 'AB+', 'AB-'],
        'can_receive_from' => ['B-', 'O-']
    ],
    'AB+' => [
        'name' => 'AB+',
        'can_donate_to' => ['AB+'],
        'can_receive_from' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']
    ],
    'AB-' => [
        'name' => 'AB-',
        'can_donate_to' => ['AB+', 'AB-'],
        'can_receive_from' => ['A-', 'B-', 'AB-', 'O-']
    ],
    'O+' => [
        'name' => 'O+',
        'can_donate_to' => ['A+', 'B+', 'AB+', 'O+'],
        'can_receive_from' => ['O+', 'O-']
    ],
    'O-' => [
        'name' => 'O-',
        'can_donate_to' => ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'],
        'can_receive_from' => ['O-']
    ]
]);

