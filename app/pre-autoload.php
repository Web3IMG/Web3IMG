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

/**
 * Ref: app/vendor/google/apiclient-services/autoload.php
 * 
 * Google now detects for a file to determine version to use. It breaks
 * old installations. Need to hack this to fool their detector.
 */
namespace {
    class Google_Client {}
}