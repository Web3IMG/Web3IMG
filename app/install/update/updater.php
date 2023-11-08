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
use Exception;
use LogicException;
use Throwable;

use function G\unlinkIfExists;

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}

function checkLicense(string $license) {
    $json_array = json_decode(G\fetch_url(Settings::getChevereto()['api']['license']['check'], false, [
        CURLOPT_REFERER => G\get_base_url(),
        CURLOPT_POST => 1,
        CURLOPT_POSTFIELDS => ['license' => $license],
    ]), true);
    if($json_array['status_code'] == 200) {
        if($json_array['data']['version'] != 3) {
            $json_array = [
                'status_code' => 400,
                'error' => [
                    'message' => _s('V%required% license key required (provided V%version% license key)', [
                        '%version%' => $json_array['data']['version'],
                        '%required%' => '3'
                    ]),
                    'code' => 101,
                ]
            ];            
        }
    }

    return $json_array;
}

function ask() {
    $json_array = json_decode(G\fetch_url(Settings::getChevereto()['api']['get']['info'], false, [
        CURLOPT_REFERER => G\get_base_url()
    ]), true);
    $updateNeeded = version_compare($json_array['software']['current_version'], Settings::get('chevereto_version_installed'), '>');
    $message = sprintf("Latest: %s", $json_array['software']['current_version'] ?? 'n/a') . ' - ' . sprintf('Installed: %s', Settings::get('chevereto_version_installed'));
    $json_array['success'] = [
        'message' => $message,
        'update_needed' => $updateNeeded,
    ];

    return $json_array;
}

function download(string $dir, array $params = []) {
    $version = $params['version'] ?? 'latest';
    $zip_local_filename = 'chevereto_' . $version . '_' . G\random_string(24) . '.zip';
    if (empty($params['license'])) {
        throw new Exception(_s('Invalid license info'), 110);
    }
    $url = Settings::getChevereto()['api']['download'] . '/' . $version;
    G\logger("Fetching $url\n");
    $download = G\fetch_url($url . '/?license=' . $params['license'], false, [
        CURLOPT_REFERER => G\get_base_url(),
        'progress' => true,
    ]);
    $json_decode = json_decode($download);
    if (json_last_error() == JSON_ERROR_NONE) {
        $json_array = [
            'status_code' => 400,
            'error' => [
                'message' => json_decode($download, true)['error']['message'] ?? 'error downloading',
                'code' => 400,
            ]
        ];
    } else {
        if (file_put_contents($dir . $zip_local_filename, $download) === false) {
            throw new Exception(_s("Can't save file"));
        }
        $json_array = [
            'success' => [
                'message'   => 'Download completed',
                'code'      => 200
            ],
            'download' => [
                'filename' => $zip_local_filename
            ]
        ];
    }

    return $json_array;
}

function extract(string $dir, array $params) {
    $zip_file = $dir . $params['file'];
    if (!is_readable($zip_file)) {
        throw new Exception('Missing '.$zip_file.' file', 400);
    }
    $zip = new \ZipArchive;
    if ($zip->open($zip_file) === true) {
        try {
            $stock_maintenance = getSetting('maintenance');
            DB::update('settings', ['value' => '1'], ['name' => 'maintenance']);
        } catch (Exception $e) {
        }

        $zip->extractTo($dir);
        $zip->close();
        unlinkIfExists($zip_file);
    } else {
        throw new Exception(_s("Can't extract %s", G\absolute_to_relative($zip_file)), 401);
    }
    $source = $dir . 'chevereto/';
    $dest = G_ROOT_PATH;
    foreach ($iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST) as $item) {
        $target = $dest . $iterator->getSubPathName();
        try {
            $php_user = 'php:' . posix_getpwuid(posix_geteuid())['name'];
        } catch(Throwable $e) {
            $php_user = 'php:unknown';
        }
        if ($item->isDir()) {
            if (!file_exists($target)) {
                try {
                    mkdir($target, 0755, true);
                } catch(Throwable $e) {
                    throw new Exception(_s("Can't create %s directory - %e", [
                        '%s' => $target,
                        '%e' => $php_user . '>' . $e->getMessage()
                    ]), 402);
                }
            }
        } else {
            $itemFilepath = $item->getRealPath();
            try {
                copy($itemFilepath, $target);
            } catch (Throwable $e) {
                throw new Exception(_s("Can't update %s file - %e", [
                    '%s' => $target,
                    '%e' => $php_user . '>copy>' . $itemFilepath . $e->getMessage()
                ]), 403);
            }
            unlinkIfExists($itemFilepath);
        }
    }
    $tmp = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($tmp as $fileinfo) {
        $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
        $todo($fileinfo->getRealPath());
    }
    try {
        DB::update('settings', ['value' => $stock_maintenance], ['name' => 'maintenance']);
    } catch (Exception $e) {
    }
    $json_array['success'] = ['message' => 'OK', 'code' => 200];

    return $json_array;
}

try {
    $opts = getopt('C:l:') ?? null;
    if(!empty($_REQUEST)) {
        $params = $_REQUEST;
    } else if(!empty($opts)) {
        $params = [
            'action' => 'ask',
            'license' => $opts['l'],
        ];
    }
    if(is_null(getSetting('chevereto_version_installed'))) {
        $message = 'Chevereto is not installed';
        if (PHP_SAPI !== 'cli') {
            throw new LogicException($message);
        } else {
            echo "$message\n";
            die(255);
        }
    }
    if (PHP_SAPI !== 'cli' && !Login::isAdmin()) {
        G\set_status_header(403);
        throw new LogicException('Request denied. You must be an admin to be here.', 403);
    }
    G\logger("* Checking for updates\n");
    if (!class_exists('ZipArchive')) {
        throw new Exception("PHP ZipArchive class is not enabled in this server");
    }
    if (!is_writable(G_ROOT_PATH)) {
        throw new Exception(sprintf("Can't write into root %s path", G\absolute_to_relative(G_ROOT_PATH)));
    }
    $update_temp_dir = CHV_APP_PATH_INSTALL . 'update/temp/';
    if (!isset($params['action'])) {
        if (PHP_SAPI === 'cli') {
            G\logger("Missing action\n");
            die(255);
        } else {
            $doctitle = _s('Update in progress');
            $system_template = CHV_APP_PATH_CONTENT_SYSTEM . 'template.php';
            $update_template = dirname($update_temp_dir) . '/template/update.php';
            if (file_exists($update_template)) {
                ob_start();
                require_once($update_template);
                $html = ob_get_contents();
                ob_end_clean();
            } else {
                throw new Exception("Can't find " . G\absolute_to_relative($update_template));
            }
            require_once($system_template);
            die();
        }
    } else {
        if(PHP_SAPI !== 'cli') {
            try {
                set_time_limit(600);
            } catch(Throwable $e) {
                // Ignore        
            }
        }
        switch ($params['action']) {
            case 'check-license':
                $json_array = checkLicense($params['license']);
                break;
            case 'ask':
                $json_array = ask();
                if(PHP_SAPI == 'cli') {
                    if(!$json_array['success']['update_needed']) {
                        G\logger("> " . $json_array['success']['message'] . "\n");
                        G\logger("[OK] No update needed\n");
                        die();
                    } else {
                        G\logger("> Update needed\n");
                        G\logger("* Downloading latest release\n");
                        $json_array = download($update_temp_dir, $params);
                        if($json_array['status_code'] == 400) {
                            G\logger("[ERR] " . $json_array['error']['message'] . "\n");
                            die(255);
                        } else {
                            G\logger("> " . $json_array['download']['filename'] .  "\n");
                            G\logger("* Extracting downloaded file\n");
                            $params['file'] = $json_array['download']['filename'];
                            $json_array = extract($update_temp_dir, $params);
                            if($json_array['status_code'] == 400) {
                                G\logger("[ERR] " . $json_array['error']['message'] . "\n");
                                die(255);
                            }
                            G\logger("[OK] Chevereto files updated!\n");
                            G\logger("--\n");
                            G\logger("To proceed with DB update run:\n");
                            G\logger("> php cli.php -C install\n");
                            die(0);
                        }
                    }
                }
                break;
            case 'download':
                $json_array = download($update_temp_dir, $params);
                break;
            case 'extract':
                $json_array = extract($update_temp_dir, $params);
                break;
        }
        if (isset($json_array['success']) && !isset($json_array['status_code'])) {
            $json_array['status_code'] = 200;
        }
    }
} catch (Throwable $e) {
    G\exception_to_error($e, false);
    if (PHP_SAPI !== 'cli' && !isset($params['action'])) {
        Render\chevereto_die($e->getMessage(), "This installation can't use the automatic update functionality because this server is missing some crucial elements to allow Chevereto to perform the automatic update:", "Can't perform automatic update");
    }
    $json_array = G\json_error($e);
}
G\Render\json_output($json_array);
