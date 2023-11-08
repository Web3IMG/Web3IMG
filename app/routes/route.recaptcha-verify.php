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
    try {
        header('Last-Modified: '.gmdate('D, d M Y H:i:s').'GMT');
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Content-type: application/json; charset=UTF-8');
        $endpoint = 'https://www.recaptcha.net/recaptcha/api/siteverify';
        $params = [
            'secret'	=> CHV\getSetting('recaptcha_private_key'),
            'response'	=> $_GET['token'],
            'remoteip'	=> G\get_client_ip()
        ];
        $endpoint .= '?' . http_build_query($params);
        $fetch = G\fetch_url($endpoint);
        $json = json_decode($fetch);
        $_SESSION['isHuman'] = $json->success;
        $_SESSION['isBot'] = !$json->success;
        die($fetch);
    } catch (Exception $e) {
    }
};
