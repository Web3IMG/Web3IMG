<?php

/* --------------------------------------------------------------------

  G\ library
  https://g.chevereto.com

  @author	Rodolfo Berrios A. <http://rodolfoberrios.com/>

  Copyright (c) Rodolfo Berrios <inbox@rodolfoberrios.com> All rights reserved.

  Licensed under the MIT license
  http://opensource.org/licenses/MIT

  --------------------------------------------------------------------- */

namespace G;

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}

define('G_VERSION', '1.1.0');
@ini_set('log_errors', true);
error_reporting(E_ALL ^ E_NOTICE);
@ini_set('session.cookie_httponly', 1);
setlocale(LC_ALL, 'en_US.UTF8');
@ini_set('default_charset', 'utf-8');
define('G_ROOT_PATH', rtrim(str_replace('\\', '/', dirname(dirname(__DIR__))), '/') . '/');
define('G_ROOT_LIB_PATH', G_ROOT_PATH . 'lib/');
define('G_PATH', G_ROOT_LIB_PATH . 'G/');
define('G_PATH_CLASSES', G_PATH . 'classes/');
define('G_FILE_FUNCTIONS', G_PATH . 'functions.php');
define('G_FILE_FUNCTIONS_RENDER', G_PATH . 'functions.render.php');
define('G_APP_PATH', G_ROOT_PATH . 'app/');
define('G_APP_PATH_LIB', G_APP_PATH . 'lib/');
define('G_APP_PATH_ROUTES', G_APP_PATH . 'routes/');
define('G_APP_PATH_ROUTES_OVERRIDES', G_APP_PATH_ROUTES . 'overrides/');
define('G_APP_PATH_CLASSES', G_APP_PATH_LIB . 'classes/');
define('G_APP_FILE_FUNCTIONS', G_APP_PATH_LIB . 'functions.php');
define('G_APP_FILE_FUNCTIONS_RENDER', G_APP_PATH_LIB . 'functions.render.php');
define('G_APP_SETTINGS_FILE_ERROR', '<br />There are errors in the <strong>%%FILE%%</strong> file. Change the encoding to "UTF-8 without BOM" using Notepad++ or any similar code editor and remove any character before <span style="color: red;">&lt;?php</span>');
(file_exists(G_APP_PATH . 'settings.php')) ? require_once(G_APP_PATH . 'settings.php') : die("G\: Can't find app/settings.php");
if (headers_sent()) {
    die(str_replace('%%FILE%%', 'app/settings.php', G_APP_SETTINGS_FILE_ERROR));
}
$tz = @date_default_timezone_get();
$dtz = @date_default_timezone_set($tz);
if (!$dtz && !@date_default_timezone_set('America/Santiago')) {
    die(
        strtr(
            'Invalid timezone identifier: %i. Configure php.ini with a valid timezone identifier %l',
            [
                '%i' => $tz,
                '%l' => 'http://php.net/manual/en/timezones.php'
            ]
        )
    );
}
$session_handler = getenv('CHEVERETO_SESSION_SAVE_HANDLER') ?: ($settings['session.handler'] ?? null);
if(isset($session_handler) && ini_set('session.save_handler', $session_handler) === false) {
    die(
        sprintf('Invalid session.save_handler provided %s', $session_handler)
    );
}
$session_save_path = getenv('CHEVERETO_SESSION_SAVE_PATH') ?: ($settings['session.save_path'] ?? null);
if(isset($session_save_path) && ini_set('session.save_path', $session_save_path)) {
    die(
        sprintf('Invalid save_path.handler provided %s', $session_save_path)
    );
}

if(PHP_SAPI !== 'cli' && ini_get('session.save_handler') === 'files') {
    $session_save_path = session_save_path();
    if($session_save_path === '') {
        $session_save_path = '/tmp';
    }
    $session_save_path = realpath($session_save_path);
    if ($session_save_path !== false) { // realpath on this needs pre-webroot directories access
        foreach (['write'] as $k) {
            $fn = 'is_' . $k . 'able';
            if (!$fn($session_save_path)) {
                $session_errors[] = $k;
            }
        }
        if (isset($session_errors)) {
            die(
                strtr(
                    "G\: Sessions are not working on this server due to missing %s permission on session save path (%f session.save_path at %p).",
                    [
                        '%s' => implode('/', $session_errors),
                        '%f' => isset($settings['session.save_path']) ? 'app/settings.php' : 'php.ini',
                        '%p' => $session_save_path
                    ]
                )
            );
        }
    }
}
$_SESSION['G'] = true;
if (!isset($_SESSION['G']) && $_SESSION['G'] !== true) {
    die("G\: Sessions are not working properly (was unable to set a session key).");
}
define('G_APP_TIME_EXECUTION_START', microtime(true));
(file_exists(__DIR__ . '/functions.php')) ? require_once(__DIR__ . '/functions.php') : die("G\: Can't find <strong>" . __DIR__ . '/functions.php' . '</strong>. Make sure that this file exists.');
if (file_exists(__DIR__ . '/functions.render.php')) {
    require_once __DIR__ . '/functions.render.php';
}
define(
    'G_ROOT_PATH_RELATIVE',
    get_app_setting('hostname_path')
    ?? rtrim(dirname($_SERVER['SCRIPT_NAME']), '\/') . '/'
);
if (isset($settings) && array_key_exists('error_reporting', $settings) && $settings['error_reporting'] === false) {
    error_reporting(0);
}
if (isset($settings['environment'])) {
    define('G_APP_ENV', $settings['environment']);
}
$_SERVER['SCRIPT_FILENAME'] = forward_slash($_SERVER['SCRIPT_FILENAME']);
$_SERVER['SCRIPT_NAME'] = forward_slash($_SERVER['SCRIPT_NAME']);
if (file_exists(G_APP_PATH . 'app.php')) {
    require_once G_APP_PATH . 'app.php';
}
foreach (['host', 'port', 'name', 'user', 'pass', 'driver', 'pdo_attrs'] as $k) {
    define(
        'G_APP_DB_' . strtoupper($k),
        isset($settings['db_' . $k])
            ? (is_array($settings['db_' . $k])
                ? serialize($settings['db_' . $k])
                : $settings['db_' . $k])
        : null);
}
$error_log = get_app_setting('error_log');
if(isset($error_log) && $error_log != ini_get('error_log')) {
    ini_set('error_log', get_app_setting('error_log'));
}
(file_exists(G_APP_FILE_FUNCTIONS)) ? require_once(G_APP_FILE_FUNCTIONS) : die("G\: Can't find <strong>" . G_APP_FILE_FUNCTIONS . '</strong>. Make sure that this file exists.');
if (file_exists(G_APP_FILE_FUNCTIONS_RENDER)) {
    require_once G_APP_FILE_FUNCTIONS_RENDER;
}
set_exception_handler('G\exception_to_error');
set_error_handler('G\errorsAsExceptions', E_ALL ^ E_NOTICE);
