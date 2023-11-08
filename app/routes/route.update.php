<?php
$route = function ($handler) {
    if (G\get_app_setting('disable_update_http') ||
        !CHV\Settings::get('chevereto_version_installed') ||
        !CHV\Login::isAdmin() ||
        !$handler::checkAuthToken($_REQUEST['auth_token'] ?? null)
    ) {
        G\set_status_header(403);
        $handler->template = 'request-denied';
        return;
    }
    $update_script = CHV_APP_PATH_INSTALL . 'update/updater.php';
    if (!file_exists($update_script)) {
        throw new Exception('Missing ' . G\absolute_to_relative($update_script), 100);
    }
    if (!@require_once($update_script)) {
        throw new Exception("Can't include " . G\absolute_to_relative($update_script), 101);
    }
};
