<?php
if (!defined('pp_allowed_access')) {
    die('Direct access not allowed');
}

$plugin_meta = [
    'Plugin Name'       => 'TopupBay',
    'Description'       => 'A transaction management module that provides API endpoints and admin interface for managing TopupBay transactions. Includes secure API key authentication and webhook support.',
    'Version'           => '1.0.0',
    'Author'            => 'PipraPay',
    'Author URI'        => 'https://piprapay.com/',
    'License'           => 'GPL-2.0+',
    'License URI'       => 'http://www.gnu.org/licenses/gpl-2.0.txt',
    'Requires at least' => '1.0.0',
    'Plugin URI'        => '',
    'Text Domain'       => '',
    'Domain Path'       => '',
    'Requires PHP'      => ''
];

$funcFile = __DIR__ . '/functions.php';
if (file_exists($funcFile)) {
    require_once $funcFile;
}

// Load the admin UI rendering function
function topupbay_admin_page() {
    // POST handler in functions.php should have already run and exited if it was a POST request
    // If we reach here, it means it's a GET request or POST was not handled
    
    $viewFile = __DIR__ . '/views/admin-ui.php';
    if (file_exists($viewFile)) {
        include $viewFile;
    } else {
        echo "<div class='alert alert-warning'>Admin UI not found.</div>";
    }
}

