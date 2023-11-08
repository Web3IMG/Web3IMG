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

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.0 403 Forbidden');
    die("403 Forbidden\n");
}
$opts = getopt('C:') ?? null;
if(empty($opts)) {
    echo "Missing -C command\n";
    die(255);
} else {
    $access = $opts['C'];
    $options = ['cron', 'importing', 'install', 'langs', 'update', 'setting-update', 'htaccess-checksum', 'htaccess-enforce'];
    if(!in_array($access, $options)) {
        echo "Invalid command\n";
        die(255);
    }
}
define('access', $access);

/*** Load the G app ***/
if (!include_once('app/loader.php')) {
    die("Can't find app/loader.php");
}
