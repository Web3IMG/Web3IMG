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
    if ($handler->isRequestLevel(4)) {
        return $handler->issue404();
    }
    if (is_null($handler->request[0])) {
        return $handler->issue404();
    }
    if (isset($handler->request[1]) && !in_array($handler->request[1], ['embeds', 'sub', 'info'])) {
        return $handler->issue404();
    }
    $logged_user = CHV\Login::getUser();
    CHV\User::statusRedirect($logged_user['status'] ?? null);
    $id = CHV\getIdFromURLComponent($handler->request[0]);
    if (!isset($_SESSION['album_view_stock'])) {
        $_SESSION['album_view_stock'] = [];
    }
    $album = CHV\Album::getSingle($id, !in_array($id, $_SESSION['album_view_stock']), true, $logged_user);
    if ($album && G\starts_with($album['url'], G\get_current_url()) == false) {
        $redirect = '';
        if ($_SERVER['QUERY_STRING']) {
            $redirect = rtrim($album['url'], '/') . '/?' . $_SERVER['QUERY_STRING'];
        } else {
            $redirect = $album['url'];
        }
        G\redirect($redirect);
    }
    $handler::setVar('canonical', isset($_GET['page']) ? null : $album['url']);
    $banned = isset($album['user']['status']) && $album['user']['status'] === 'banned';
    if (!$handler::getCond('content_manager') && ($album == false || $banned)) {
        return $handler->issue404();
    }
    $is_owner = $album['user']['id'] && $album['user']['id'] == ($logged_user['id'] ?? 0);
    if (CHV\getSetting('website_privacy_mode') == 'private') {
        if ($handler::getCond('forced_private_mode')) {
            $album['privacy'] = CHV\getSetting('website_content_privacy_mode');
        }
        if (!CHV\Login::getUser() && $album['privacy'] != 'private_but_link') {
            G\redirect('login');
        }
    }
    if (!($handler::getCond('content_manager') || $is_owner) && $album['privacy'] == 'password' && isset($album['password'])) {
        $is_error = false;
        $error_message = null;
        $failed_access_requests = CHV\Requestlog::getCounts('content-password', 'fail');
        if (CHV\is_max_invalid_request($failed_access_requests['day'])) {
            G\set_status_header(403);
            $handler->template = 'request-denied';
            return;
        }
        $captcha_needed = $handler::getCond('captcha_needed');
        if ($captcha_needed && $_POST['content-password']) {
            $captcha = CHV\recaptcha_check();
            if (!$captcha->is_valid) {
                $is_error = true;
                $error_message = _s('%s says you are a robot', 'reCAPTCHA');
            }
        }
        if (!$is_error) {
            if (isset($_POST['content-password']) && CHV\Album::checkPassword($album['password'], $_POST['content-password'])) {
                CHV\Album::storeUserPasswordHash($album['id'], $_POST['content-password']);
            } else {
                if (!CHV\Album::checkSessionPassword($album)) {
                    $is_error = true;
                    if ($_POST['content-password']) {
                        CHV\Requestlog::insert(['type' => 'content-password', 'user_id' => ($logged_user ? $logged_user['id'] : null), 'content_id' => $album['id'], 'result' => 'fail']);
                        $error_message = _s('Invalid password');
                    }
                }
            }
        }
        $handler::setCond('error', $is_error);
        $handler::setVar('error', $error_message);
        if ($is_error) {
            if (CHV\getSettings()['recaptcha'] && CHV\must_use_recaptcha($failed_access_requests['day'] + 1)) {
                $captcha_needed = true;
            }
            if ($captcha_needed) {
                $handler::setCond('captcha_show', true);
                $handler::setVar(...CHV\Render\get_recaptcha_component());
            }
            $handler::setCond('captcha_needed', $captcha_needed);
            $handler->template = 'password-gate';
            $handler::setVar('pre_doctitle', _s('Password required'));
            return;
        } else {
            $redirect_password = $_SESSION['redirect_password_to'] ?? null;
            if(isset($redirect_password)) {
                unset($_SESSION['redirect_password_to']);
                G\redirect($redirect_password);
            }
        }
    }
    if ($album['user']['is_private'] && !$handler::getCond('content_manager') && $album["user"]["id"] !== $logged_user['id']) {
        unset($album['user']);
        $album['user'] = CHV\User::getPrivate();
    }
    if (!$handler::getCond('content_manager') && in_array($album['privacy'], array('private', 'custom')) and !$is_owner) {
        return $handler->issue404();
    }
    $safe_html_album = G\safe_html($album);
    $safe_html_album['description'] = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $safe_html_album['description']));
    $list_params = CHV\Listing::getParams(); // Use CHV magic params
    $handler::setVar('list_params', $list_params);
    $type = 'images';
    $where = 'WHERE image_album_id=:image_album_id';
    $output_tpl = 'album/image';
    if (isset($handler->request[1]) && $handler->request[1] == 'sub') {
        $type = 'albums';
        $where = 'WHERE album_parent_id=:image_album_id';
        $output_tpl = 'user/album';
    }
    $list = new CHV\Listing;
    $list->setType($type); // images | users | albums
    if(isset($list_params['reverse'])) {
        $list->setReverse($list_params['reverse']);
    }
    if(isset($list_params['seek'])) {
        $list->setSeek($list_params['seek']);
    }
    $list->setOffset($list_params['offset']);
    $list->setLimit($list_params['limit']); // how many results?
    $list->setItemsPerPage($list_params['items_per_page']); // must
    $list->setSortType($list_params['sort'][0]); // date | size | views
    $list->setSortOrder($list_params['sort'][1]); // asc | desc
    $list->setOwner($album["user"]["id"]);
    $list->setRequester(CHV\Login::getUser());
    $list->setWhere($where);
    $list->setPrivacy($album["privacy"]);
    $list->bind(":image_album_id", $album["id"]);
    $list->output_tpl = $output_tpl;
    if ($is_owner or $handler::getCond('content_manager')) {
        $list->setTools(true);
    }
    $list->exec();
    $tabs = CHV\Listing::getTabs([
        'listing'    => 'images',
        'basename'    => G\get_route_name() . '/' . $album['id_encoded'],
        'params_hidden' => ['list' => 'images', 'from' => 'album', 'albumid' => $album['id_encoded']],
        'tools_available' => $album['user']['id'] ? [] : ['album' => false]
    ]);
    if (CHV\isShowEmbedContent()) {
        $tabs[] = [
            'icon' => 'fas fa-code',
            'list'        => false,
            'tools'        => false,
            'label'        => _s('Embed codes'),
            'url'       => $album['url'] . '/embeds',
            'id'        => 'tab-embeds',
        ];
    }
    $tabsSubAlbum = CHV\Listing::getTabs([
        'listing'    => 'albums',
        'basename'    => G\get_route_name() . '/' . $album['id_encoded'] . '/sub',
        'params_hidden' => ['list' => 'albums', 'from' => 'album', 'albumid' => $album['id_encoded']],
        'tools_available' => $album['user']['id'] ? [] : ['album' => false]
    ]);
    foreach ($tabsSubAlbum as $array) {
        if ($array['label'] == 'AZ') {
            $array['label'] = _s('Sub albums');
            $array['id'] = 'tab-sub';
            $array['url'] = $album['url'] . '/sub';
            $tabs[] = $array;
            break;
        }
    }
    if (CHV\Login::isAdmin()) {
        $tabs[] = [
            'icon' => 'fas fa-info',
            'list'        => false,
            'tools'        => false,
            'label'        => _s('Info'),
            'id'        => 'tab-info',
            'url'       => $album['url'] . '/info'
        ];
    }
    $handler::setVar('current_tab', 0);
    foreach ($tabs as $k => &$v) {
        if (isset($handler->request[1])) {
            $v['current'] = $v['id'] == ('tab-' . $handler->request[1]);
        }
        if (isset($v['current']) && $v['current'] === true) {
            $handler::setVar('current_tab', $v['id']);
        }
        if (!isset($v['params'])) {
            continue;
        }
        $class_tabs[$k]['disabled'] = $album['image_count'] == 0 ? !$v['current'] : false;
    }
    $handler::setCond('owner', $is_owner);
    $handler::setVars([
        'pre_doctitle'        => strip_tags($album['name']),
        'album'                => $album,
        'album_safe_html'    => $safe_html_album,
        'tabs'                => $tabs,
        'list'                => $list,
        'owner'                => $album['user']
    ]);
    if ($album['description']) {
        $meta_description = $album['description'];
    } else {
        $meta_description = _s('%a album hosted in %w', ['%a' => $album['name'], '%w' => CHV\getSetting('website_name')]);
    }
    $handler::setVar('meta_description', htmlspecialchars($meta_description));
    if ($handler::getCond('content_manager') or $is_owner) {
        $handler::setVar('user_items_editor', [
            "user_albums"    => CHV\User::getAlbums($album["user"]["id"]),
            "type"            => "images"
        ]);
    }
    $share_element = [
        "HTML" => '<a href="__url__" title="__title__">__title__ (' . $album['image_count'] . ' ' . _n('image', 'images', $album['user']['image_count']) . ')</a>'
    ];
    $share_links_array = CHV\render\get_share_links($share_element);
    $handler::setVar('share_links_array', $share_links_array);
    $handler::setVar('privacy', $album['privacy']);
    $_SESSION['album_view_stock'][] = $id;
};
