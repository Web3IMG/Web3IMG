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
use Intervention\Image\ImageManagerStatic;

/**
 * This could be used to extend the image types allowed... Maybe .tiff support and so on.
 */
class ImageConvert
{
    public function __construct($source, $to, $destination, $quality=90)
    {
        if(!in_array($to, ['jpg', 'gif', 'png'])) {
            return $source;
        }
        $image = ImageManagerStatic::make($source);
        $image->encode($to, $quality)->save($destination);
        $this->out = $destination;
    }
}
