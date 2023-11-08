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

use function G\forward_slash;
use function G\rrmdir;

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
echo "* Checksum Apache HTTP Web Server .htaccess files\n";
$apacheDir = G_APP_PATH . 'apache/';
$checksumFile = $apacheDir . 'checksums.php';
rrmdir($apacheDir);
mkdir($apacheDir);
$files = glob(G_ROOT_PATH  . "{*/,*/*/,*/*/*/}.htaccess", GLOB_BRACE);
$checksums = [];
foreach($files as $file) {
    $relativeFile = str_replace(G_ROOT_PATH, '', forward_slash($file));
    $md5File = md5_file($file);
    file_put_contents($apacheDir . $md5File, file_get_contents($file));
    $checksums[$relativeFile] = $md5File;
    echo '  - ' . $relativeFile . ' > ' . $md5File . "\n";
}
file_put_contents(
    $checksumFile,
    '<?php return '
    . var_export($checksums, true)
    . ';'
);
echo '✅ [DONE] Checksums stored at ' . $checksumFile . "\n";
die(0);