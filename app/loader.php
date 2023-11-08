<?php

/* --------------------------------------------------------------------

  Chevereto
  https://chevereto.com/

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>
            <inbox@rodolfoberrios.com>

  Copyright (C) Rodolfo Berrios A. All rights reserved.

  BY USING THIS SOFTWARE YOU DECLARE TO ACCEPT THE CHEVERETO EULA
  https://chevereto.com/license

  --------------------------------------------------------------------- */

// This file is used to load G and your G APP
// If you need to hook elements to this loader you can add them in loader-hook.php

namespace CHV;

use G;
use Intervention\Image\ImageManagerStatic;
use LogicException;

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
if (!is_readable(dirname(__FILE__) . '/settings.php')) {
    if (!@fopen(dirname(__FILE__) . '/settings.php', 'w')) {
        die("Chevereto can't create the app/settings.php file. You must manually create this file.");
    }
}
(file_exists(dirname(dirname(__FILE__)) . '/lib/G/G.php')) ? require_once(dirname(dirname(__FILE__)) . '/lib/G/G.php') : die("Can't find lib/G/G.php");
$handler = __DIR__ . '/' .  access . '.php';
if(!file_exists($handler)) {
    throw new LogicException("Missing handler for " . access);
}
$min_memory = '256M';
$memory_limit = ini_get('memory_limit');
$memory_limit_bytes = isset($memory_limit) ? G\get_ini_bytes($memory_limit) : 0;
if ($memory_limit_bytes < G\get_ini_bytes($min_memory)) {
    ini_set('memory_limit', $min_memory);
}
ini_set('gd.jpeg_ignore_warning', 1);
if (array_key_exists('crypt', $_SESSION) == false) {
    $cipher = 'AES-128-CBC';
    $ivlen = openssl_cipher_iv_length($cipher);
    $iv = openssl_random_pseudo_bytes($ivlen);
    $_SESSION['crypt'] = [
        'cipher' => $cipher,
        'ivlen' => $ivlen,
        'iv' => $iv,
    ];
}
if (G\settings_has_db_info()) {
    DB::getInstance();
}
Settings::getInstance();
if (Settings::get('cdn')) {
    define('CHV_ROOT_CDN_URL', Settings::get('cdn_url'));
}
define('G_HTTP_HOST',
    $_SERVER['HTTP_HOST']
    ?? G\get_app_setting('hostname')
    ?? 'no-hostname'
);
$isHttps = strtolower($_SERVER['HTTPS'] ?? '') == 'on' || (bool) (G\get_app_setting('https'));
$isHttpsForward = isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https';
$isCFVisitorHttps = preg_match('#https#i', $_SERVER['HTTP_CF_VISITOR'] ?? '');
switch (Settings::get('website_https')) {
    default:
    case 'auto':
        $http_protocol = 'http' . (($isHttps || $isHttpsForward || $isCFVisitorHttps) ? 's' : '');
        break;
    case 'forced':
        $http_protocol = 'https';
        break;
    case 'disabled':
        $http_protocol = 'http';
        break;
}
define('G_HTTP_PROTOCOL', $http_protocol);
define('G_ROOT_URL', G_HTTP_PROTOCOL . '://' . G_HTTP_HOST . G_ROOT_PATH_RELATIVE); // http(s)://www.mysite.com/chevereto/
define('G_ROOT_LIB_URL', G\absolute_to_url(G_ROOT_LIB_PATH)); // not used
define('G_APP_LIB_URL', G\absolute_to_url(G_APP_PATH_LIB));
define('CHV_FOLDER_IMAGES', !is_null(Settings::get('chevereto_version_installed')) ? Settings::get('upload_image_path') : 'images');
define('CHV_APP_PATH_INSTALL', G_APP_PATH . 'install/');
define('CHV_APP_PATH_CONTENT', G_APP_PATH . 'content/');
define('CHV_APP_PATH_LIB_VENDOR', G_APP_PATH . 'vendor/');
require_once 'pre-autoload.php';
require_once CHV_APP_PATH_LIB_VENDOR . 'autoload.php';
define('CHV_APP_PATH_CONTENT_SYSTEM', CHV_APP_PATH_CONTENT . 'system/');
define('CHV_APP_PATH_CONTENT_LANGUAGES', CHV_APP_PATH_CONTENT . 'languages/');
define('CHV_APP_PATH_CONTENT_LOCKS', CHV_APP_PATH_CONTENT . 'locks/');
define('CHV_PATH_IMAGES', G_ROOT_PATH . CHV_FOLDER_IMAGES . '/');
define('CHV_PATH_CONTENT', G_ROOT_PATH . 'content/');
define('CHV_PATH_RELATIVE_CONTENT_IMAGES_SYSTEM', 'content/images/system/');
define('CHV_PATH_RELATIVE_CONTENT_IMAGES_USERS', 'content/images/users/');
define('CHV_PATH_CONTENT_IMAGES_SYSTEM', CHV_PATH_CONTENT . 'images/system/');
define('CHV_PATH_CONTENT_IMAGES_USERS', CHV_PATH_CONTENT . 'images/users/');
define('CHV_PATH_CONTENT_PAGES', CHV_PATH_CONTENT . 'pages/');
define('CHV_PATH_PEAFOWL', G_ROOT_LIB_PATH . 'Peafowl/');
$hostnameSetting = G\get_app_setting('hostname') ?: Settings::get('hostname') ?: G\get_domain(G_HTTP_HOST);
$isIP = filter_var(G_HTTP_HOST, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) || filter_var(G_HTTP_HOST, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
$isWildcardEnabled = Settings::get('lang_subdomain_wildcard') || Settings::get('user_subdomain_wildcard');
if ($isIP == false && $isWildcardEnabled && ($hostnameSetting != G_HTTP_HOST)) {
    $hostExplode = explode('.', $hostnameSetting);
    if ($hostWildcard = $hostExplode[2] ? $hostExplode[0] : null) {
        define('CHV_HOST_WILDCARD', $hostWildcard);
    }
    array_shift($hostExplode);
    $app_g_http_host = implode('.', $hostExplode);
    $cookieDomain = '.' . (defined('CHV_HOST_WILDCARD') ? $app_g_http_host : G_HTTP_HOST);
    $cookieWildcard = false;
    if (ini_get('session.cookie_domain') != $cookieDomain) {
        ini_set('session.cookie_domain', $cookieDomain);
        if (ini_get('session.cookie_domain') == $cookieDomain) {
            $cookieWildcard = true;
        }
    }
    $cookie_params = session_get_cookie_params();
    if (defined('CHV_HOST_WILDCARD') && $cookieWildcard) {
        define('APP_G_HTTP_HOST', $app_g_http_host);
        foreach (['G_ROOT_URL', 'G_ROOT_LIB_URL', 'G_APP_LIB_URL'] as $v) {
            define('APP_' . $v, G\str_replace_first(G_HTTP_HOST, APP_G_HTTP_HOST, constant($v)));
        }
        if (getSetting('lang_subdomain_wildcard') && array_key_exists(CHV_HOST_WILDCARD, get_enabled_languages())) {
            $hostWildcardType = 'lang';
            L10n::setCookieLang(CHV_HOST_WILDCARD);
        } elseif (getSetting('user_subdomain_wildcard')) {
            if ($user = User::getSingle(CHV_HOST_WILDCARD, 'username', false)) {
                define('CHV_HOST_WILDCARD_USER_ID', $user['user_id']);
                $hostWildcardType = 'user';
            }
        }
    }
    if ($hostWildcardType) {
        define('CHV_HOST_WILDCARD_TYPE', $hostWildcardType);
    }
}
define('CHV_ROOT_URL', defined('APP_G_ROOT_URL') ? APP_G_ROOT_URL : G_ROOT_URL);
define('CHV_HTTP_HOST', defined('APP_G_HTTP_HOST') ? APP_G_HTTP_HOST : G_HTTP_HOST);
define('CHV_ROOT_URL_STATIC', defined('CHV_ROOT_CDN_URL') ? CHV_ROOT_CDN_URL : (defined('APP_G_ROOT_URL') ? APP_G_ROOT_URL : G_ROOT_URL));
define('G_APP_PATH_THEMES', G_APP_PATH . 'themes/');
if (!file_exists(G_APP_PATH_THEMES)) {
    die("G\: Theme path doesn't exists!");
}
if (isset($settings['theme']) and file_exists(G_APP_PATH_THEMES . $settings['theme'])) {
    define('G_APP_PATH_THEME', G_APP_PATH_THEMES . $settings['theme'] . '/');
    define('BASE_URL_THEME', G\absolute_to_url(G_APP_PATH_THEME));
}
define('CHV_MAX_INVALID_REQUESTS_PER_DAY', 25);
if (isset($_REQUEST['session_id'])) {
    session_id($_REQUEST['session_id']);
}
if (!session_start()) {
    die("G\: Sessions are not working on this server (session_start).");
}
if (!defined('G_APP_PATH_THEME')) {
    $theme_path = G_APP_PATH_THEMES;
    if (Settings::get('chevereto_version_installed')) {
        $theme_path .= Settings::get('theme') . '/';
    }
    if (is_dir($theme_path)) {
        define('G_APP_PATH_THEME', $theme_path);
        define('BASE_URL_THEME', G\absolute_to_url(G_APP_PATH_THEME, CHV_ROOT_URL_STATIC));
    } else {
        die(sprintf("Theme path %s doesn't exists.", G\absolute_to_relative($theme_path)));
    }
}
define('CHV_URL_PEAFOWL', G\absolute_to_url(CHV_PATH_PEAFOWL, CHV_ROOT_URL_STATIC));
(file_exists(G_APP_PATH_LIB . 'integrity-check.php')) ? require_once G_APP_PATH_LIB . 'integrity-check.php' : die("Can't find app/lib/integrity-check.php");
check_system_integrity();
if (Settings::get('chevereto_version_installed')) {
    if (Settings::get('error_reporting') === false) {
        error_reporting(0);
    }
    if (G\is_valid_timezone(Settings::get('default_timezone'))) {
        date_default_timezone_set(Settings::get('default_timezone'));
    }
    if (access === 'web') {
        $upload_max_filesize_mb_db = Settings::get('upload_max_filesize_mb');
        $upload_max_filesize_mb_bytes = G\get_bytes($upload_max_filesize_mb_db  . 'MB');
        $ini_upload_max_filesize = G\get_ini_bytes(ini_get('upload_max_filesize'));
        $ini_post_max_size = ini_get('post_max_size') == 0 ? $ini_upload_max_filesize : G\get_ini_bytes(ini_get('post_max_size'));
        Settings::setValue('true_upload_max_filesize', min($ini_upload_max_filesize, $ini_post_max_size));
        if (Settings::get('true_upload_max_filesize') < $upload_max_filesize_mb_bytes) {
            Settings::update([
                'upload_max_filesize_mb' => G\bytes_to_mb(Settings::get('true_upload_max_filesize')),
            ]);
        }
    }
}
(file_exists(G_APP_PATH_LIB . 'l10n.php')) ? require_once(G_APP_PATH_LIB . 'l10n.php') : die("Can't find app/lib/l10n.php");
ImageManagerStatic::configure([
    'driver' => G\get_app_setting('image_library')
        ?? (extension_loaded('imagick') ? 'imagick' : 'gd')
]);
new AssetStorage(
    Storage::getAnon(
        G\get_app_setting('asset_storage_type') ?? 'local',
        G\get_app_setting('asset_storage_name') ?? 'assets',
        G\get_app_setting('asset_storage_url') ?? CHV_ROOT_URL_STATIC,
        G\get_app_setting('asset_storage_bucket') ?? G_ROOT_PATH,
        G\get_app_setting('asset_storage_key') ?? null,
        G\get_app_setting('asset_storage_secret') ?? null,
        G\get_app_setting('asset_storage_region') ?? null,
        G\get_app_setting('asset_storage_server') ?? null,
        G\get_app_setting('asset_storage_service') ?? null,
        G\get_app_setting('asset_storage_account_id') ?? null,
        G\get_app_setting('asset_storage_account_name') ?? null,
    )
);
$homepage_cover_image = getSetting('homepage_cover_image');
$homepage_cover_image_default = 'default/home_cover.jpg';
$homeCovers = [];
if (isset($homepage_cover_image)) {
    foreach (explode(',', $homepage_cover_image) as $vv) {
        $homeCovers[] = [
            'basename' => $vv,
            'url' => get_system_image_url($vv),
        ];
    }
}
Settings::setValue('homepage_cover_images', $homeCovers);
shuffle($homeCovers);
Settings::setValue('homepage_cover_images_shuffled', $homeCovers ?? []);

if(IMAGE_FORMATS_FAILING !== []) {
    $formats = explode(',', Settings::get('upload_enabled_image_formats'));
    $formatsDiff = array_diff($formats, IMAGE_FORMATS_FAILING);
    if($formatsDiff !== $formats) {
        Settings::update(['upload_enabled_image_formats' => implode(',', $formatsDiff)]);
    }
}

require_once __DIR__ . '/' .  access . '.php';
