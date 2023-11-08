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
if(G\get_app_setting('disable_update_cli')) {
    echo "CLI update is disabled.\n";
    die(255);
}
$update_script = CHV_APP_PATH_INSTALL . 'update/updater.php';
if (!file_exists($update_script)) {
    throw new Exception('Missing ' . $update_script, 100);
}
if (!require_once($update_script)) {
    throw new Exception("Can't include " . $update_script, 101);
}