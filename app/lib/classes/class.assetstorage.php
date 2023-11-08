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

use BadMethodCallException;
use G;

class AssetStorage
{
    protected static $storage;

    protected static $isLocalLegacy;

    public function __construct(array $storage)
    {
        self::$storage = $storage;
        self::$isLocalLegacy = Storage::getApiType($storage['api_id']) == 'local' && G_ROOT_PATH === $storage['bucket'];
    }

    public static function getStorage()
    {
        if (is_null(self::$storage)) {
            throw new BadMethodCallException('Missing AssetStorage instance creation');
        }

        return self::$storage;
    }

    public static function isLocalLegacy() {
        return self::$isLocalLegacy;
    }
}