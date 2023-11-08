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

class Lock
{
    private $name;

    public function __construct($name)
    {
        $this->name = $name;
    }

    public function create()
    {
        $lock = DB::get('locks', ['name' => $this->name]);
        $lock = $lock[0] ?? false;
        if ($lock !== false) {
            $diff = G\datetime_diff($lock['expires_gmt'] ?? G\datetimegmt());
            if ($diff > 0) {
                return false;
            }
            $this->destroy();
        }
        $datetime = G\datetimegmt();
        $insert = DB::insert('locks', [
            'name' => $this->name,
            'date_gmt' => $datetime,
            'expires_gmt' => G\datetime_add($datetime, 'PT15S'),
        ]);

        return $insert !== false;
    }

    public function destroy()
    {
        if (DB::delete('locks', ['name' => $this->name]) === false) {
            throw new LockException('Unable to destroy lock ' . $this->name);
        }

        return true;
    }
}
class LockException extends Exception
{
}
