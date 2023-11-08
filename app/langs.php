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

use function G\unlinkIfExists;

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
echo "* Cache languages\n";
$languages = array_keys(get_available_languages());
foreach($languages as $lang) {
    $filename = $lang . '.po';
    $language_file = CHV_APP_PATH_CONTENT_LANGUAGES . $filename;
    $language_override_file = dirname($language_file) . '/overrides/' . $filename;
    $language_handling = [
        'base' => [
          'file'      => $language_file,
          'cache_path'  => dirname($language_file) . '/cache/',
          'table'      => [],
        ],
        'override' => [
          'file'      => $language_override_file,
          'cache_path'  => dirname($language_file) . '/cache/overrides/',
          'table'     => [],
        ]
    ];
    foreach ($language_handling as $k => $v) {
        $cache_path = $v['cache_path'];
        $cache_file = basename($v['file']) . '.cache.php';
        if (!file_exists($v['file'])) {
            continue;
        }
        $cache = $cache_path . $cache_file;
        unlinkIfExists($cache);
        new G\Gettext([
            'file'         => $v['file'],
            'cache_filepath'  => $cache,
            'cache_header'    => $k == 'base',
        ]);
    }
    echo "  - $lang\n";
}
echo "✅ [DONE] Languages cached\n";
die(0);
