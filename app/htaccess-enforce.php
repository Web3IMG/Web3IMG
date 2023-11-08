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

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
echo "* Enforce .htaccess files\n";
$apacheDir = G_APP_PATH . 'apache/';
$checksumFile = $apacheDir . 'checksums.php';
$checksums = include $checksumFile;
$changed = false;
foreach($checksums as $file => $md5) {
    $absoluteFile = G_ROOT_PATH . $file;
    $md5File = file_exists($absoluteFile)
        ? md5_file($absoluteFile)
        : null;
    if($md5File != $md5) {
        if(file_exists($absoluteFile) && !is_writable($absoluteFile)) {
            echo "Unable to write $absoluteFile file\n";
            die(255);
        }
        file_put_contents($absoluteFile, file_get_contents($apacheDir . $md5));
        $changed = true;
        echo '  - Checksum enforced for ' . $file  . "\n";
    }
}
$changedMessage = !$changed ? ' (everything OK)' : '';
echo "✅ [DONE] Enforce completed$changedMessage\n";
