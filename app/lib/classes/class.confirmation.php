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

class Confirmation
{
    public static function get($values, $sort=array(), $limit=1)
    {
        return DB::get('confirmations', $values, 'AND', $sort, $limit);
    }
    
    public static function insert($values)
    {
        if (!is_array($values)) {
            throw new ConfirmationException('Expecting array values, '.gettype($values).' given in ' . __METHOD__, 100);
        }
        
        if (!isset($values['status'])) {
            $values['status'] = 'active';
        }
        
        $values['date'] = G\datetime();
        $values['date_gmt'] = G\datetimegmt();
        
        return DB::insert('confirmations', $values);
    }
    
    public static function update($id, $values)
    {
        return DB::update('confirmations', $values, ['id' => $id]);
    }
    
    public static function delete($values, $clause='AND')
    {
        return DB::delete('confirmations', $values, $clause);
    }
}

class ConfirmationException extends Exception
{
}
