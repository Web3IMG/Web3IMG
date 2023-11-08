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
    if ($handler->isRequestLevel($handler::getCond('mapped_route') ? 4 : 5)) {
        return $handler->issue404();
    }
    $request_handle = $handler::getCond('mapped_route') ? $handler->request_array : $handler->request;
    if (CHV\getSetting('website_mode') == 'personal' and CHV\getSetting('website_mode_personal_routing') == '/' and in_array($request_handle[0], ['albums', 'search', 'following', 'followers'])) {
        $request_handle = [1 => $request_handle[0]];
    }
    $username = $request_handle[0] ?? null;
    if ($handler::getCond('mapped_route') and $handler::$mapped_args) {
        $mapped_args = $handler::$mapped_args;
    }
    if (isset($mapped_args['id'])) {
        $id = $handler::$mapped_args['id'];
    }
    if (!isset($username) and is_null($id)) {
        return $handler->issue404();
    }
    if (CHV\getSetting('user_routing') and $handler->request_array[0] == 'user') {
        G\redirect(preg_replace('#/user/#', '/', G\get_current_url(), 1));
    }
    $logged_user = CHV\Login::getUser();
    CHV\User::statusRedirect($logged_user['status'] ?? null);
    $userhandle = !isset($id) ? 'username' : 'id';
    $user = CHV\User::getSingle($$userhandle, $userhandle);
    $is_owner = false;
    if(isset($user['id'], $logged_user['id'])) {
        $is_owner = $user['id'] == $logged_user['id'];
    }
    if (!$user or $user['status'] !== 'valid' and (!$logged_user or !$handler::getCond('content_manager'))) {
        return $handler->issue404();
    }
    if (CHV\getSetting('website_mode') == 'personal' and $user['id'] == CHV\getSetting('website_mode_personal_uid') and $handler->request_array[0] == 'user') {
        G\redirect(CHV\getSetting('website_mode_personal_routing'));
    }
    if (!($is_owner || $handler::getCond('content_manager')) && $user['is_private']) {
        return $handler->issue404();
    }
    $user_routes = [];
    $user_views = [
        'images'	=> [
            'title' 		=> _s("%s's Images"),
            'title_short'	=> _s("Images"),
            'doctitle'		=> '',
        ],
        'albums'	=> [
            'title'			=> _s("%s's Albums"),
            'title_short'	=> _s("Albums"),
            'doctitle'		=> '',
        ],
        'search'	=> [
            'title'			=> _s('Results for'),
            'title_short'	=> '',
            'doctitle'		=> '',
        ],
    ];
    foreach ($user_views as $k => $v) { // Need to use $k => $v to fetch array key easily
        array_push($user_routes, $k == 'images' ? $username : $k);
    }
    if (CHV\getSetting('enable_likes')) {
        $user_views['liked'] = [
            'title'			=> _s("Liked by %s"),
            'title_short'	=> _s("Liked"),
            'doctitle'		=> '',
        ];
        array_push($user_routes, 'liked');
    }
    if (CHV\getSetting('enable_followers')) {
        $user_views['following'] = [
            'title'			=> _s('Following'),
            'title_short'	=> '',
            'doctitle'		=> '',
        ];
        $user_views['followers'] = [
            'title'			=> _s('Followers'),
            'title_short'	=> '',
            'doctitle'		=> '',
        ];
        array_push($user_routes, 'following', 'followers');
    }
    foreach ($user_views as $k => $v) {
        $user_views[$k]['current'] = false;
    }
    if (isset($request_handle[1])) {
        if ($request_handle[1] == 'search') {
            if (!$_REQUEST['q']) {
                G\redirect($user['url']);
            }
            $user['search'] = [
                'type'	=> empty($_REQUEST['list']) ? 'images' : $_REQUEST['list'],
                'q'		=> $_REQUEST['q'],
                'd'		=> strlen($_REQUEST['q']) >= 25 ? (substr($_REQUEST['q'], 0, 22) . '...') : $_REQUEST['q']
            ];
        }
        if ($request_handle[1] !== $_SERVER['QUERY_STRING']) {
            if (!in_array($request_handle[1], $user_routes)) {
                return $handler->issue404();
            }
        }
        if ($request_handle[1] == 'search') {
            if (!$_SERVER['QUERY_STRING']) {
                return $handler->issue404();
            }
            if (!empty($_REQUEST['list']) and !in_array($_REQUEST['list'], ['images', 'albums', 'users'])) {
                return $handler->issue404();
            }
        }
        if (array_key_exists($request_handle[1], $user_views)) {
            $user_views[$request_handle[1]]['current'] = true;
        }
    } else {
        $user_views['images']['current'] = true;
    }
    $user['followed'] = false;
    $show_follow_button = false;
    if(isset($logged_user['id'])) {
        $user['followed'] = $user['id'] == $logged_user['id'] ?? null ? false : CHV\Follow::doesFollow($logged_user['id'], $user['id']);
        $show_follow_button = $user['id'] !== $logged_user['id'] && !$logged_user['is_private'];
    }
    $handler::setCond('show_follow_button', $show_follow_button);
    $pre_doctitle = $user['name'];
    if (CHV\getSetting('website_mode') == 'community' or $user['id'] !== CHV\getSetting('website_mode_personal_uid')) {
        $pre_doctitle .= ' ('.$user['username'].')';
    }
    $handler::setVar('pre_doctitle', $pre_doctitle);

    $base_user_url = $user['url'];
    foreach ($user_views as $k => $v) {
        $handler::setCond('user_' . $k, $v['current']);
        if ($v['current']) {
            $current_view = $k;
            if ($current_view !== 'images') {
                $base_user_url .= '/' . $k;
            }
        }
    }
    $safe_html_user = G\safe_html($user);
    switch ($current_view) {
        case 'images':
        case 'liked':
            $type = "images";
            $tools = $is_owner || $handler::getCond('content_manager');
            if ($current_view == 'liked') {
                $tools_available = $handler::getCond('content_manager') ? ['delete', 'category', 'flag'] : ['embed'];
            }
        break;
        case 'following':
        case 'followers':
            $type = 'users';
            $tools = false;
            $params_hidden = [$current_view . '_user_id' => $user['id_encoded']];
            $params_remove_keys = ['list'];
        break;
        case 'albums':
            $type = "albums";
            $tools = true;
        break;
        case 'search':
            $type = $user['search']['type'];
            $tabs = [
                [
                    'type'		=> 'images',
                    'label'		=> _n('Image', 'Images', 2),
                    'id'		=> 'list-user-images',
                    'current'	=> (isset($_REQUEST['list']) && $_REQUEST['list'] == 'images') || !isset($_REQUEST['list']),
                ],
                [
                    'type'		=> 'albums',
                    'label'		=> _n('Album', 'Albums', 2),
                    'id'		=> 'list-user-albums',
                    'current'	=> isset($_REQUEST['list']) && $_REQUEST['list'] == 'albums',
                ]
            ];
            foreach ($tabs as $k => $v) {
                $params = [
                    'list'	=> $v['type'],
                    'q'		=> $safe_html_user['search']['q'],
                    'sort'	=> 'date_desc',
                    'page'	=> '1',
                ];
                $tabs[$k]['params'] = http_build_query($params);
                $tabs[$k]['url'] = $base_user_url . '/?' . $tabs[$k]['params'];
            }
        break;
    }
    if ($user_views['albums']['current']) {
        $params_hidden['list'] = 'albums';
    }
    $params_hidden[$current_view == 'liked' ? 'like_user_id' : 'userid'] = $user['id_encoded'];
    $params_hidden['from'] = 'user';
    if (!isset($tabs)) {
        $tabs = CHV\Listing::getTabs([
            'listing'	=> $type,
            'basename'	=> $base_user_url,
            'tools'		=> $tools,
            'tools_available'	=> $tools_available ?? null,
            'params_hidden'		=> $params_hidden,
            'params_remove_keys'=> $params_remove_keys ?? null,
        ]);
    }
    foreach ($tabs as $k => &$v) {
        if ($params_hidden && !array_key_exists('params_hidden', $tabs)) {
            $tabs[$k]['params_hidden'] = http_build_query($params_hidden);
        }
        $v['disabled'] = $user[($user_views['images']['current'] ? 'image' : 'album') . '_count'] == 0 ? !$v['current'] : false;
    }
    if ($user["image_count"] > 0 or $user["album_count"] > 0 or in_array($current_view, ['liked', 'following', 'followers'])) {
        $list_params = CHV\Listing::getParams(); // Use CHV magic params
        $handler::setVar('list_params', $list_params);

        if ($list_params['sort'][0] == 'likes' and !CHV\getSetting('enable_likes')) {
            $handler->issue404();
        }
        $tpl = $type;
        switch ($current_view) {
            case 'liked':
                $where = 'WHERE like_user_id=:user_id';
                $tpl = 'liked';
            break;
            case 'following':
                $where = 'WHERE follow_user_id=:user_id';
            break;
            case 'followers':
                $where = 'WHERE follow_followed_user_id=:user_id';
            break;
            default:
                $where = $type == 'images'
                    ? 'WHERE image_user_id=:user_id'
                    : 'WHERE album_user_id=:user_id AND album_parent_id IS NULL';
            break;
        }
        $output_tpl = 'user/' . $tpl;
        if ($user_views['search']['current']) {
            $type = $user["search"]["type"];
            $where = $user["search"]["type"] == "images" ? "WHERE image_user_id=:user_id AND MATCH(image_name, image_title, image_description, image_original_filename) AGAINST(:q)" : "WHERE album_user_id=:user_id AND MATCH(album_name, album_description) AGAINST(:q)";
        }
        $show_user_items_editor = CHV\Login::getUser() ? true : false;

        if ($type == 'albums') {
            $show_user_items_editor = false;
        }
        try {
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
            $list->setSortType($list_params['sort'][0]); // date | size | views | likes
            $list->setSortOrder($list_params['sort'][1]); // asc | desc
            $list->setWhere($where);
            $list->setOwner($user["id"]);
            $list->setRequester(CHV\Login::getUser());
            if ($is_owner or $handler::getCond('content_manager')) {
                if ($type == 'users') {
                    $list->setTools(false);
                    $show_user_items_editor = false;
                } else {
                    if ($current_view == 'liked') {
                        $list->setTools($user['id'] == $logged_user['id'] ? ['embed'] : []);
                    } else {
                        $list->setTools(true);
                    }
                }
            }
            $list->bind(":user_id", $user["id"]);
            if ($user_views['search']['current'] and !empty($user['search']['q'])) {
                $list->bind(':q', $q ?: $user['search']['q']);
            }
            $list->output_tpl = $output_tpl;
            $list->exec();
        } catch (Exception $e) {
        } // Silence to avoid wrong input queries
    }
    $title = sprintf($user_views[$current_view]['title'], $user['firstname_html']);
    $title_short = sprintf($user_views[$current_view]['title_short'], $user['firstname_html']);
    $handler::setCond('owner', $is_owner);
    $handler::setCond('show_user_items_editor', $show_user_items_editor ?? null);
    $handler::setVar('user', $user);
    $handler::setVar('safe_html_user', $safe_html_user);
    $handler::setVar('title', $title);
    $handler::setVar('title_short', $title_short);
    $handler::setVar('tabs', $tabs);
    $handler::setVar('list', $list ?? null);
    if ($user_views['albums']['current']) {
        $meta_description = _s('%n (%u) albums on %w');
    } else {
        if ($user['bio']) {
            $meta_description = $safe_html_user['bio'];
        } else {
            $meta_description = _s('%n (%u) on %w');
        }
    }
    $handler::setVar('meta_description', strtr($meta_description, ['%n' => $user['name'], '%u' => $user['username'], '%w' => CHV\getSetting('website_name')]));
    if ($handler::getCond('content_manager') or $is_owner) {
        $handler::setVar('user_items_editor', [
            "user_albums"	=> CHV\User::getAlbums($user),
            "type"			=> $user_views['albums']['current'] ? "albums": "images"
        ]);
    }
    if (CHV\getSetting('user_subdomain_wildcard')) {
        $handler::setVar('canonical', CHV\get_current_url_wildcard($user['username']));
    }
    $handler::setVar('share_links_array', CHV\render\get_share_links());
};