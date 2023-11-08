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

namespace CHV;

use G;
use Throwable;

use function CHV\Render\chevereto_die;
use function G\is_writable;

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}

/**
 * SYSTEM INTEGRITY CHECK
 * Welcome to the jungle of non-standard PHP setups
 * ----------------------------------------------------------------------------------------------------------------------------------------
 */

function check_system_integrity()
{
    $install_errors = [];
    $missing_tpl = '%n (<a href="http://php.net/manual/en/%t.%u.php" target="_blank">%f</a>) %t is disabled in this server. This %t must be enabled in your PHP configuration (php.ini) and/or you must add this missing %t.';
    if (version_compare(PHP_VERSION, '5.6.0', '<')) {
        $install_errors[] = 'This server is currently running PHP version '.PHP_VERSION.' and Chevereto needs at least PHP 5.6.0 to run. You need to update PHP in this server.';
    }
    if (ini_get('allow_url_fopen') !== 1 && !function_exists('curl_init')) {
        $install_errors[] = "cURL isn't installed and allow_url_fopen is disabled. Chevereto needs one of these to perform HTTP requests to remote servers.";
    }
    if (preg_match('/apache/i', $_SERVER['SERVER_SOFTWARE'] ?? '') && function_exists('apache_get_modules') && !in_array('mod_rewrite', apache_get_modules())) {
        $install_errors[] = 'Apache <a href="http://httpd.apache.org/docs/2.1/rewrite/rewrite_intro.html" target="_blank">mod_rewrite</a> is not enabled in this server. This must be enabled to run Chevereto.';
    }
    $extensionsRequired = [
        'exif'		=> [
            '%label'=> 'Exif',
            '%name' => 'Exchangeable image information',
            '%slug' => 'book.exif',
            '%desc' => 'Exif is required to handle image metadata'
        ],
        'pdo'		=> [
            '%label'=> 'PDO',
            '%name' => 'PHP Data Objects',
            '%slug' => 'book.pdo',
            '%desc' => 'PDO is needed to perform database operations'
        ],
        'pdo_mysql' => [
            '%label'=> 'PDO_MYSQL',
            '%name' => 'PDO MySQL Functions',
            '%slug' => 'ref.pdo-mysql',
            '%desc' => 'PDO_MYSQL is needed to work with a MySQL database',
        ],
        'mbstring' => [
            '%label'=> 'mbstring',
            '%name' => 'Multibyte string',
            '%slug' => 'book.mbstring',
            '%desc' => 'Mbstring is needed to handle multibyte strings',
        ],
        'fileinfo' => [
            '%label'=> 'fileinfo',
            '%name' => 'Fileinfo',
            '%slug' => 'book.fileinfo',
            '%desc' => 'Fileinfo is required for file handling',
        ],
        'zip' => [
            '%label'=> 'Zip',
            '%name' => 'Zip',
            '%slug' => 'book.zip',
            '%desc' => 'Zip is needed to update process',
        ]
    ];
    $php_image = [
        'imagick' => [
            '%label'=> 'imagick',
            '%name' => 'Imagick',
            '%slug' => 'book.imagick',
            '%desc' => 'Imagick is needed for image processing',
        ],
        'gd' => [
            '%label'=> 'gd',
            '%name' => 'gd',
            '%slug' => 'book.gd',
            '%desc' => 'GD is needed for image processing',
        ]
        ];
    $mustHaveFormats = ['PNG', 'GIF', 'JPG', 'BMP', 'WEBP'];
    $image_formats_available = G\get_app_setting('image_formats_available');
    if(is_array($image_formats_available)) {
        $mustHaveFormats = $image_formats_available;
    }
    $image_lib = [
        'gd' => extension_loaded('gd') && function_exists('gd_info'),
        'imagick' => extension_loaded('imagick'),
    ];
    if(!$image_lib['gd'] && !$image_lib['imagick']) {
        $install_errors[] = 'No image handling library in this server. Enable either Imagick extension or GD extension to perform image processing.';
    }
    $image_library = G\get_app_setting('image_library');
    if(isset($image_library) && !($image_lib[$image_library] ?? false)) {
        $install_errors[] = 'Configured image_library ' . $image_library . ' is not present in this system.';
    }
    $image_library = $image_library
        ?? ($image_lib['imagick'] ? 'imagick' : 'gd');
    $extensionsRequired[$image_library] = $php_image[$image_library];
    foreach ($extensionsRequired as $k => $v) {
        if (!extension_loaded($k)) {
            $install_errors[] = strtr('%name (<a href="http://www.php.net/manual/%slug.php">%label</a>) is not loaded in this server. %desc.', $v);
        }
    }
    $failed_formats = [];
    if($image_library === 'imagick') {
        $imageFormats = \Imagick::queryFormats();
        foreach($mustHaveFormats as $format) {
            if(!in_array($format, $imageFormats)) {
                $failed_formats[] = $format;
            }
        }
    } elseif($image_library === 'gd') {
        $imageTypes = imagetypes();
        foreach($mustHaveFormats as $format) {
            if(!($imageTypes & constant("IMG_$format"))) {
                $failed_formats[] = $format;
            }
        }
    }
    $failed_formats = array_map('strtolower', $failed_formats);
    define('IMAGE_FORMATS_FAILING', $failed_formats);
    $disabled_classes = explode(',', preg_replace('/\s+/', '', @ini_get('disable_classes')));
    if (!empty($disabled_classes)) {
        foreach (['DirectoryIterator', 'RegexIterator', 'Pdo', 'Exception'] as $k) {
            if (in_array($k, $disabled_classes)) {
                $install_errors[] = strtr(str_replace('%t', 'class', $missing_tpl), ['%n' => $k, '%f' => $k, '%u' => str_replace('_', '-', strtolower($k))]);
            }
        }
    }
    foreach ([
        'utf8_encode' => 'UTF-8 encode',
        'utf8_decode' => 'UTF-8 decode'
    ] as $k => $v) {
        if (!function_exists($k)) {
            $install_errors[] = strtr(str_replace('%t', 'function', $missing_tpl), ['%n' => $v, '%f' => $k, '%u' => str_replace('_', '-', $k)]);
        }
    }
    $writing_paths = [CHV_PATH_IMAGES, CHV_PATH_CONTENT, CHV_APP_PATH_CONTENT, CHV_APP_PATH_INSTALL . 'update/temp/'];
    foreach ($writing_paths as $v) {
        if (!file_exists($v)) { // Exists?
            try {
                mkdir($v);
            } catch (Throwable $e) {
                $install_errors[] = "<code>".G\absolute_to_relative($v)."</code> doesn't exists. Make sure to upload it.";
            }
        } else {
            if(!is_writable($v)) {
                $install_errors[] = 'No write permission for PHP user '.get_current_user().' in <code>'.G\absolute_to_relative($v).'</code> directory. Chevereto needs to be able to write in this directory.';
            }
        }
    }
    $system_template = CHV_APP_PATH_CONTENT_SYSTEM . 'template.php';
    if (!file_exists($system_template)) {
        $install_errors[] = "<code>".G\absolute_to_relative($system_template)."</code> doesn't exists. Make sure to upload this.";
    }
    if (is_array($install_errors) && count($install_errors) > 0) {
        if (access !== 'web') {
            G\debug($install_errors);
            die(255);
        }
        chevereto_die($install_errors);
    }
}
