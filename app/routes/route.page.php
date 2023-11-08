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

$route = function($handler) {
	$request_url_key = implode('/', $handler->request);
	$page = CHV\Page::getSingle($request_url_key);
	if(!$page or !$page['is_active'] or $page['type'] !== 'internal') {
		return $handler->issue404();
	}
	if(!$page['file_path_absolute']) {
		return $handler->issue404();
	}
	if(!file_exists($page['file_path_absolute'])) {
		return $handler->issue404();
	}
	$pathinfo = pathinfo($page['file_path_absolute']);
	$page_extension = G\get_file_extension($page['file_path_absolute']);
	$handler->path_theme = G\add_ending_slash($pathinfo['dirname']);
	$handler->template = $pathinfo['filename'] . '.' . $page_extension;
	$page_metas = [
		'pre_doctitle'		=> $page['title'],
		'meta_description'	=> htmlspecialchars($page['description']),
		'meta_keywords'		=> htmlspecialchars($page['keywords'])
	];
	foreach($page_metas as $k => $v) {
		if($v == NULL) continue;
		$handler->setVar($k, $v);
	}
};