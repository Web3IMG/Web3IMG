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
    if (!$handler::getCond('explore_enabled')) {
        return $handler->issue404();
    }
    $category = null;
    $categories = $handler::getVar('categories');
    $category_url_key = $handler->request[0];

    if (!$category_url_key) {
        G\redirect('explore');
    }
    if ($category_url_key) {
        foreach ($categories as $v) {
            if ($v['url_key'] == $category_url_key) {
                $category = $v;
                break;
            }
        }
        if (!$category) {
            return $handler->issue404();
        }
        $handler::setVar('pre_doctitle', $category['name']);
    }
    $tabs = CHV\Listing::getTabs([
        'listing'	=> 'images',
        'basename'	=> G\get_route_name() . '/' . $category['url_key'],
        'params_hidden' => ['category_id' => $category['id'], 'hide_banned' => 1],
    ]);
    $list_params = CHV\Listing::getParams(); // Use CHV magic params
    $handler::setVar('list_params', $list_params);
    $list = new CHV\Listing;
    $list->setType('images');
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
    $list->setCategory($category['id']);
    $list->setRequester(CHV\Login::getUser());
    $list->exec();
    $meta_description = $category['description'] ?: null;
    $handler::setVar('meta_description', htmlspecialchars($meta_description));
    $handler::setVar('category', $category);
    $handler::setVar('tabs', $tabs);
    $handler::setVar('list', $list);
    $handler->template = 'explore';
    $handler::setVar('share_links_array', CHV\render\get_share_links());
};
