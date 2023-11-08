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
    if ($handler->isRequestLevel(3)) {
        return $handler->issue404();
    } // Allow only 2 levels

    if (is_null($handler->request[0])) {
        return $handler->issue404();
    }

    $logged_user = CHV\Login::getUser();

    // User status override redirect
    CHV\User::statusRedirect($logged_user['status'] ?? null);
    $id = CHV\getIdFromURLComponent($handler->request[0]);

    if ($id == false) {
        return $handler->issue404();
    }
    // Trail this view
    $_SESSION['last_viewed_image'] = CHV\encodeId($id);

    // Session stock viewed images
    if (!isset($_SESSION['image_view_stock'])) {
        $_SESSION['image_view_stock'] = [];
    }

    $image = CHV\Image::getSingle($id, !in_array($id, $_SESSION['image_view_stock']), true, $logged_user);

    if($image === false) {
        return $handler->issue404();
    }

    if (!$image['is_approved'] && !($logged_user['is_manager'] || $logged_user['is_admin'])) {
        G\set_status_header(403);
        $handler->template = 'request-denied';
        return;
    }


    if ($image['url_viewer'] != G\get_current_url()) {
        G\redirect($image['url_viewer']);
    }

    $handler::setVar('canonical', $image['url_viewer']);

    // No image or belongs to a banned user if exists?
    if (!$image || (!$handler::getCond('content_manager') && ($image['user']['status'] ?? null) == 'banned')) {
        return $handler->issue404();
    }

    // Test local images
    if ($image['file_resource']['type'] == 'path') {
        // Update is_animated flag
        if (!$image['is_animated'] && isset($image['file_resource']['chain']['image']) && G\is_animated_image($image['file_resource']['chain']['image'])) {
            CHV\Image::update($id, ['is_animated' => 1]);
            $image['is_animated'] = 1;
        }
    }

    /*
        Note: Remote image testing was removed because of the HUGE number of websites running external containers unaccesible via HTTP.
        Remote image test works only if the website can fetch the image URI headers.
        Check Chevereto < 3.8.4 for the old remote image tester code
    */
    $is_owner = isset($image['user']['id']) ? ($image['user']['id'] == ($logged_user['id'] ?? null)) : false;
    if (CHV\getSetting('website_privacy_mode') == 'private') {
        if ($handler::getCond('forced_private_mode')) {
            $image['album']['privacy'] = CHV\getSetting('website_content_privacy_mode');
        }
        if (!CHV\Login::getUser() && ($image['album']['privacy'] ?? null) != 'private_but_link') {
            G\redirect('login');
        }
    }
    if (!($handler::getCond('content_manager') || $is_owner) && ($image['album']['privacy'] ?? null) == 'password' && !CHV\Album::checkSessionPassword($image['album'])) {
        $_SESSION['redirect_password_to'] = $image['url_viewer'];
        G\redirect($image['album']['url']);
    }
    if (isset($image['user']['is_private']) && $image['user']['is_private'] === true && !$handler::getCond('content_manager') && $image['user']['id'] !== $logged_user['id']) {
        unset($image['user']);
        $image['user'] = CHV\User::getPrivate();
    }
    if (!$handler::getCond('content_manager') and in_array($image['album']['privacy'] ?? null, array('private', 'custom')) and !$is_owner) {
        return $handler->issue404();
    }
    if (isset($image['user']['id'])) {
        $image['user']['albums'] = CHV\User::getAlbums($image["user"]["id"]);
    }
    $is_album_cover = false;
    if (isset($image['album']['id'])) {
        $album = CHV\Album::getSingle($image['album']['id']);
        $is_album_cover = $album['cover_id'] == $image['id'];
        $get_album_slice = CHV\Image::getAlbumSlice($image['id'], $image['album']['id'], 2);
        $image_album_slice = array_merge($image['album'], $get_album_slice);
    }
    $handler::setCond('album_cover', $is_album_cover);
    $image_safe_html = G\safe_html($image);
    $image['alt'] = $image_safe_html['description'] ?: ($image_safe_html['title'] ?: $image_safe_html['name']);
    $pre_doctitle = strip_tags($image['title']) ?: ($image_safe_html['name'] . '.' . $image['extension']) . ' hosted at ' . CHV\getSetting('website_name');
    $tabs = [
        [
            'icon' => 'fas fa-image',
            'label' => _s('About'),
            'id' => 'tab-about',
            'current' => true,
        ],
    ];
    if (CHV\isShowEmbedContent()) {
        $tabs[] = [
            'icon' => 'fas fa-code',
            'label' => _s('Embed codes'),
            'id' => 'tab-embeds',
        ];
    }
    if ($handler::getCond('content_manager')) {
        if ($handler::getCond('admin')) {
            $tabs[] = [
                'icon' => 'fas fa-info',
                'label' => _s('Info'),
                'id' => 'tab-info',
            ];
        }
        $banned_ip = CHV\Ip_ban::getSingle(['ip' => $image['uploader_ip']]);
        $image_admin_list_values = [
            [
                'label' => _s('Image ID'),
                'content' => $image['id'] . ' (' . $image['id_encoded'] . ')',
            ],
            [
                'label' => _s('Uploader IP'),
                'content' => sprintf(str_replace('%IP', '%1$s', '<a href="' . CHV\getSetting('ip_whois_url') . '" target="_blank">%IP</a> · <a href="' . G\get_base_url('search/images/?q=ip:%IP') . '">' . _s('search content') . '</a>  ·  ' . (!$banned_ip ? ('<a data-modal="form" data-args="%IP" data-target="modal-add-ip_ban" data-options=\'{"forced": true}\' data-content="ban_ip">' . _s('Ban IP') . '</a>') : null) . '<span class="' . ($banned_ip ? null : 'soft-hidden') . '" data-content="banned_ip">' . _s('IP already banned') . '</span>'), $image['uploader_ip']),
            ],
            [
                'label' => _s('Upload date'),
                'content' => $image['date'],
            ],
            [
                'label' => '',
                'content' => $image['date_gmt'] . ' (GMT)',
            ],
        ];
        $handler::setVar('content_ip', $image['uploader_ip']);
        $handler::setVar('image_admin_list_values', $image_admin_list_values);
        $handler::setCond('banned_ip', (bool) $banned_ip);
    }
    $firstTab = 'tab-embeds'; // tab-embeds, tab-info, tab-about
    if(!CHV\isShowEmbedContent()) {
        $firstTab = 'tab-info';
    }
    $currentTab = [];
    $handler::setVar('current_tab', G\str_replace_first('tab-', '', $firstTab));
    foreach($tabs as $k => &$v) {
        if($v['id'] !== $firstTab) {
            $v['current'] = false;
            continue;
        }
        $currentKey = $k;
        $v['current'] = true;
        $currentTab = $v;
        $handler::setVar('current_tab', G\str_replace_first('tab-', null, $v['id']));
    }
    unset($tabs[$currentKey]);
    array_unshift($tabs, $currentTab);
    $handler::setCond('owner', $is_owner);
    $handler::setVar('pre_doctitle', $pre_doctitle);
    $handler::setVar('image', $image);
    $handler::setVar('image_safe_html', $image_safe_html);
    $handler::setVar('image_album_slice', G\safe_html($image_album_slice ?? []));

    $handler::setVar('tabs', $tabs);
    $handler::setVar('owner', $image['user'] ?? []);
    if (isset($image['description'])) {
        $meta_description = $image['description'];
    } else {
        $image_tr = [
            '%i' => $image[is_null($image['title']) ? 'filename' : 'title'],
            '%a' => $image['album']['name'] ?? '',
            '%w' => CHV\getSetting('website_name'),
            '%c' => $image['category']['name'] ?? '',
        ];
        if (isset($image['album']['id']) || (!($image['user']['is_private'] ?? false) && isset($image['album']['name']))) {
            $meta_description = _s('Image %i in %a album', $image_tr);
        } elseif (isset($image['category']['id'])) {
            $meta_description = _s('Image %i in %c category', $image_tr);
        } else {
            $meta_description = _s('Image %i hosted in %w', $image_tr);
        }
    }
    $handler::setVar('meta_description', htmlspecialchars($meta_description));

    if ($handler::getCond('content_manager') or $is_owner) {
        $handler::setVar('user_items_editor', [
            'user_albums' => $image['user']['albums'] ?? null,
            'type' => 'image',
            'album' => $image['album'] ?? null,
            'category_id' => $image['category_id'] ?? null,
        ]);
    }
    $handler::setVar('share_links_array', CHV\render\get_share_links());
    $handler::setVar('privacy', $image['album']['privacy'] ?? '');
    $embed = [];
    $embed['direct-links'] = [
        'label' => _s('Direct links'),
        'entries' => [
            [
                'label' => _s('Image link'),
                'value' => $image['url_short'],
            ],
            [
                'label' => _s('Image URL'),
                'value' => $image['url'],
            ],
            [
                'label' => _s('Thumbnail URL'),
                'value' => $image['thumb']['url'],
            ],
        ],
    ];
    if (isset($image['medium'])) {
        $embed['direct-links']['entries'][] = [
            'label' => _s('Medium URL'),
            'value' => $image['medium']['url'],
        ];
    }
    $image_full = [
        'html' => '<img src="' . $image['url'] . '" alt="' . $image['filename'] . '" border="0" />',
        'markdown' => '![' . $image['filename'] . '](' . $image['url'] . ')',
    ];
    $image_full['bbcode'] = G\html_to_bbcode($image_full['html']);
    $embed['full-image'] = [
        'label' => _s('Full image'),
        'entries' => [
            [
                'label' => 'HTML',
                'value' => htmlentities($image_full['html']),
            ],
            [
                'label' => 'BBCode',
                'value' => $image_full['bbcode'],
            ],
            [
                'label' => 'Markdown',
                'value' => $image_full['markdown'],
            ],
        ],
    ];
    $embed_full_linked['html'] = '<a href="' . $image['url_short'] . '">' . $image_full['html'] . '</a>';
    $embed_full_linked['bbcode'] = G\html_to_bbcode($embed_full_linked['html']);
    $embed_full_linked['markdown'] = '[![' . $image['filename'] . '](' . $image['url'] . ')](' . $image['url_short'] . ')';
    $embed['full-linked'] = [
        'label' => _s('Full image (linked)'),
        'entries' => [
            [
                'label' => 'HTML',
                'value' => htmlentities($embed_full_linked['html']),
            ],
            [
                'label' => 'BBCode',
                'value' => $embed_full_linked['bbcode'],
            ],
            [
                'label' => 'Markdown',
                'value' => $embed_full_linked['markdown'],
            ],
        ],
    ];
    if (isset($image['medium'])) {
        $embed_medium_linked = array(
            'html' => '<a href="' . $image['url_short'] . '"><img src="' . $image['medium']['url'] . '" alt="' . $image['filename'] . '" border="0" /></a>',
        );
        $embed_medium_linked['bbcode'] = G\html_to_bbcode($embed_medium_linked['html']);
        $embed_medium_linked['markdown'] = '[![' . $image['medium']['filename'] . '](' . $image['medium']['url'] . ')](' . $image['url_short'] . ')';
        $embed['medium-linked'] = [
            'label' => _s('Medium image (linked)'),
            'entries' => [
                [
                    'label' => 'HTML',
                    'value' => htmlentities($embed_medium_linked['html']),
                ],
                [
                    'label' => 'BBCode',
                    'value' => $embed_medium_linked['bbcode'],
                ],
                [
                    'label' => 'Markdown',
                    'value' => $embed_medium_linked['markdown'],
                ],
            ],
        ];
    }
    $embed_thumb_linked = [
        'html' => '<a href="' . $image['url_short'] . '"><img src="' . $image['thumb']['url'] . '" alt="' . $image['filename'] . '" border="0" /></a>',
    ];
    $embed_thumb_linked['bbcode'] = G\html_to_bbcode($embed_thumb_linked['html']);
    $embed_thumb_linked['markdown'] = '[![' . $image['thumb']['filename'] . '](' . $image['thumb']['url'] . ')](' . $image['url_short'] . ')';
    $embed['thumb-linked'] = [
        'label' => _s('Thumbnail image (linked)'),
        'entries' => [
            [
                'label' => 'HTML',
                'value' => htmlentities($embed_thumb_linked['html']),
            ],
            [
                'label' => 'BBCode',
                'value' => $embed_thumb_linked['bbcode'],
            ],
            [
                'label' => 'Markdown',
                'value' => $embed_thumb_linked['markdown'],
            ],
        ],
    ];
    $embed_id = 1;
    foreach ($embed as &$v) {
        foreach ($v['entries'] as &$entry) {
            $entry['id'] = 'embed-code-' . $embed_id;
            ++$embed_id;
        }
    }
    $handler::setVar('embed', $embed);
    $_SESSION['image_view_stock'][] = $id;
};
