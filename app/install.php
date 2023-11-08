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
$install_script = CHV_APP_PATH_INSTALL . 'installer.php';
if (!file_exists($install_script)) {
    throw new Exception('Missing ' . $install_script, 100);
}
if (!require_once($install_script)) {
    throw new Exception("Can't include " . $install_script, 101);
}