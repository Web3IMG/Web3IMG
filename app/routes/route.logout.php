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

$route = function ($handler) {
    if (!$handler::checkAuthToken($_REQUEST['auth_token'] ?? null)) {
        $handler->template = 'request-denied';

        return;
    }
    if (CHV\Login::isLoggedUser()) {
        CHV\Login::logout();
        session_start();
        $access_token = $handler::getAuthToken();
        $handler::setVar('auth_token', $access_token);
    }
};
