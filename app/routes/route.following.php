<?php
$route = function ($handler) {
    if (version_compare(CHV\getSetting('chevereto_version_installed'), '3.7.0', '<') or !CHV\getSetting('enable_followers')) {
        return $handler->issue404();
    }
    $logged_user = CHV\Login::getUser();
    if (!$logged_user) {
        G\redirect('login');
    }
    if ($handler->isRequestLevel(2)) {
        return $handler->issue404();
    }
    $tabs = CHV\Listing::getTabs([
        'listing'	=> 'images',
        'exclude_criterias'	=> ['most-oldest'],
        'params_hidden' 	=> ['follow_user_id' => CHV\encodeID($logged_user['id'])],
    ]);
    $where = 'WHERE follow_user_id=:user_id';
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
    $list->setRequester(CHV\Login::getUser());
    $list->setWhere($where);
    $list->bind(":user_id", $logged_user['id']);
    $list->exec();
    $handler::setVar('pre_doctitle', _s('Following'));
    $handler::setVar('tabs', $tabs);
    $handler::setVar('list', $list);
    if (isset($logged_user['is_content_manager']) && $logged_user['is_content_manager']) {
        $handler::setVar('user_items_editor', false);
    }
    $handler::setVar('share_links_array', CHV\render\get_share_links());
};
