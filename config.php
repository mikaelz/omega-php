<?php
/**
 * App dashboard page
 * https://go.tradegecko.com/oauth/applications/
 *
 * Docs
 * http://developer.tradegecko.com/
 */

if (in_array($_SERVER['HTTP_HOST'], array('localhost'))) {
    define('REDIRECT_URI', 'http://localhost/omega-php/');
} else {
    define('REDIRECT_URI', 'https://www.example.com/omega-php/');
}

define('TG_API_URL', 'https://api.tradegecko.com/');
define('TG_CLIENT_ID', 'CLIEND_ID');
define('TG_SECRET', 'SECRET');
define('TG_PRIVILIGED_CODE', 'PRIVILIGED_CODE');

$GLOBALS['sender'] = array(
    'name' => 'VENDOR_NAME',
    'street' => '',
    'city' => '',
    'zip' => '',
    'country' => '',
    'phone' => '',
    'ref_number' => '',
);
