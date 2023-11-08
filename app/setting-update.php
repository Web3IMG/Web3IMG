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
$opts = getopt('C:v:k:') ?? [];
$missing = [];
$set = [];
foreach(['k', 'v'] as $opt) {
    if(!isset($opts[$opt])) {
        $missing[] = $opt;
    }
}
if($missing !== []) {
    echo "Missing -" . implode(' -', $missing) . "\n";
    die(255);
}
try {
    CHV\Settings::update([$opts['k'] => $opts['v']]);
    die(0);
} catch(Throwable $e) {
    echo $e->getMessage();
    G\exception_to_error($e);
    die(255);
}