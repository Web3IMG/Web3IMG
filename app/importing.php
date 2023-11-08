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

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
$threadID = getenv('THREAD_ID') ?: 0;
$loop = 1;
do {
    Import::refresh();
    $jobs = Import::autoJobs();
    if ($jobs === []) {
        echo "~They took our jobs!~\n";
        echo "[OK] No jobs left.\n";
        die(0);
    }
    $id = $jobs[0]['import_id'];
    $import = new Import();
    $import->id = $id;
    $import->thread = (int) $threadID;
    $import->get();
    if ($import->isLocked()) {
        $import->edit(['status' => 'paused']);
        echo "> Job locked for id #$id\n";
    } else {
        echo "* Processing job id #$id\n";
        $import->process();
    }
    $loop++;
} while (isSafeToExecute());
echo "--\n[OK] Automatic importing looped $loop times ~ /dashboard/bulk for stats\n";
die(0);
