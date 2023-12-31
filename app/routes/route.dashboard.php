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

use Intervention\Image\ImageManagerStatic;
use TijsVerkoyen\Akismet\Akismet;

use function CHV\getSetting;
use function G\is_url_web;
use function G\unlinkIfExists;

$route = function ($handler) {
    if ($_POST !== [] and !$handler::checkAuthToken($_REQUEST['auth_token'] ?? null)) {
        $handler->template = 'request-denied';
        return;
    }

    $doing = $handler->request[0] ?? 'stats';
    $logged_user = CHV\Login::getUser();

    if (!$logged_user) {
        G\redirect(G\get_base_url('login'));
    }

    // Hack the user settings route
    if ($doing == 'user' && $handler::getCond('content_manager')) {
        $route = $handler->getRouteFn('settings');
        $handler::setCond('dashboard_user', true);
        return $route($handler);
    }

    if (!$logged_user['is_admin']) {
        return $handler->issue404();
    }

    $route_prefix = 'dashboard';
    $routes = [
        'stats'        => _s('Stats'),
        'images'    => _s('Images'),
        'albums'    => _s('Albums'),
        'users'        => _s('Users'),
        'settings'    => _s('Settings'),
        'bulk'    => _s('Bulk importer'),
    ];
    $icons = [
        'stats'        => 'fas fa-chart-bar',
        'images'    => 'fas fa-image',
        'albums'    => 'fas fa-images',
        'users'        => 'fas fa-users',
        'settings'    => 'fas fa-cog',
        'bulk'    => 'fas fa-layer-group',
    ];
    $default_route = 'stats';

    if (!is_null($doing) and !array_key_exists($doing, $routes)) {
        return $handler->issue404();
    }

    if ($doing == '') {
        $doing = $default_route;
    }

    // Populate the routes
    foreach ($routes as $route => $label) {
        $aux = str_replace('_', '-', $route);
        $handler::setCond($route_prefix . '_' . $aux, $doing == $aux);
        if ($handler::getCond($route_prefix . '_' . $aux)) {
            $handler::setVar($route_prefix, $aux);
        }
        $route_menu[$route] = array(
            'icon' => $icons[$route],
            'label' => $label,
            'url'    => G\get_base_url($route_prefix . ($route == $default_route ? '' : '/' . $route)),
            'current' => $handler::getCond($route_prefix . '_' . $aux)
        );
    }

    $handler::setVar('documentationBaseUrl', 'https://v3-docs.chevereto.com/');
    $handler::setVar($route_prefix . '_menu', $route_menu);
    $handler::setVar('tabs', $route_menu);

    // conds
    $is_error = false;
    $is_changed = false;

    // vars
    $input_errors = [];
    $error_message = null;

    if ($doing == '') {
        $doing = 'stats';
    }

    switch ($doing) {

        case 'stats':
            if (version_compare(CHV\getSetting('chevereto_version_installed'), '3.7.0', '<')) {
                $totals = [];
            } else {
                $totals = CHV\Stat::getTotals();
                // Note: if totals = empty row -> RECREATE
            }

            $totals_display = [];
            foreach (['images', 'users', 'albums'] as $v) {
                $totals_display[$v] = G\abbreviate_number($totals[$v]);
            }
            $format_disk_usage = explode(' ', G\format_bytes($totals['disk_used']));
            $totals_display['disk'] = ['used' => $format_disk_usage[0], 'unit' => $format_disk_usage[1]];

            if (empty($totals_display['disk']['used'])) {
                $totals_display['disk'] = [
                    'used' => 0,
                    'unit' => 'KB'
                ];
            }

            $db = CHV\DB::getInstance();

            $chevereto_news = unserialize(CHV\Settings::get('chevereto_news'));
            if(!is_array($chevereto_news) || $chevereto_news === []) {
                $chevereto_news = CHV\updateCheveretoNews();
            }
            $handler::setVar('chevereto_news', $chevereto_news);

            $chv_version = [
                'files'    => G\get_app_version(),
                'db'    => CHV\getSetting('chevereto_version_installed')
            ];

            $linksButtons = '';
            foreach([
                [
                    'label' =>  _s("Releases"),
                    'icon' => 'fas fa-rocket',
                    'href' => 'https://releases.chevereto.com'
                ],
                [
                    'label' => _s('Documentation'),
                    'icon' => 'fas fa-book',
                    'href' => 'https://v3-docs.chevereto.com'
                ],
                [
                    'label' => _s('Support'),
                    'icon' => 'fas fa-medkit',
                    'href' => 'https://chevereto.com/support'
                ],
                [
                    'label' => _s('Community'),
                    'icon' => 'fas fa-users',
                    'href' => 'https://chevereto.com/community'
                ],
                [
                    'label' => _s("License"),
                    'icon' => 'fas fa-key',
                    'href' => 'https://chevereto.com/panel/license'
                ],
            ] as $link) {
                $linksButtons .= strtr('<a href="%href%" target="_blank" class="default margin-right-5 margin-top-5 btn"><span class="btn-icon fa-btn-icon %icon%"></span> %label%</a>', [
                    '%href%' => $link['href'],
                    '%icon%' => $link['icon'],
                    '%label%' => $link['label'],
                ]);
            }

            $install_update_button = '';
            $version_check = '';
            if(version_compare($chv_version['files'], $chv_version['db'], '>')) {
                $install_update_button = $chv_version['db'] . ' DB <span class="fas fa-database"></span> <a href="' . G\get_base_url('install') . '">' . _s('install update') . '</a>';
            }
            if(!G\get_app_setting('disable_update_http')) {
                $version_check .= '<a data-action="check-for-updates" class="margin-right-5 margin-top-5 btn blue"><span class="fas fa-check-circle"></span> ' . _s("check for updates") . '</a>';
            }

            $cronRemark = '';
            $cron_last_ran = CHV\Settings::get('cron_last_ran');
            if (G\datetime_diff($cron_last_ran, null, 'm') > 5) {
                $cronRemark .= ' — <span class="color-red"><span class="fas fa-exclamation-triangle"></span> ' . _s('not running') . '</span>';
            }

            $system_values = [
                'chv_version'    => [
                    'label'        => '<div class="text-align-center"><a href="https://chevereto.com" target="_blank"><img src="' . G\absolute_to_url(CHV_APP_PATH_CONTENT_SYSTEM . 'chevereto-blue.svg') . '" alt="" width="50%"></a></div>',
                    'content'    => '<div class="phone-text-align-center">'
                        . '<h3 class="margin-bottom-10"><a target="_blank" href="https://releases.chevereto.com/3.X/3.20/' . $chv_version['files'] . '">'
                        . $chv_version['files']
                        . '<span class="btn-icon fas fas fa-code-branch"></span></a> ('.G_APP_VERSION_AKA.') </h3>'
                        . $install_update_button
                        . '<div class="margin-bottom-10">' . $version_check . $linksButtons . '</div>
                        </div>'
                ],
                'status' => [
                    'label' => _s('Status'),
                    'content' => '<i class="fas fa-skull-crossbones"></i> <a href="https://v3-docs.chevereto.com/get-started/status.html" target="_blank">V3.20 LTS</a> support has <a href="https://blog.chevereto.com/2022/09/05/end-of-support-for-v3/" target="_blank">ended</a> on <b>2022-11</b>. We recommend checking <a href="https://v4-docs.chevereto.com/" target="_blank">Chevereto V4</a> to keep your systems updated.',
                ],
                'cli' => [
                    'label' => 'CLI',
                    'content' => G_ROOT_PATH . 'cli.php',
                ],
                'cron' => [
                    'label' => _s('Cron last ran'),
                    'content' => $cron_last_ran . ' UTC' . $cronRemark,
                ],
                'php_version' => [
                    'label'        => _s('PHP version'),
                    'content'    => '<span class="fab fa-php"></span> ' . PHP_VERSION . ' ' . php_ini_loaded_file()
                ],
                'server' => [
                    'label'        => _s('Server'),
                    'content'    => (getenv('SERVER_SOFTWARE') ?: '🐍') . ' ~ ' . gethostname() . ' ' . PHP_OS . '/' . PHP_SAPI . (getenv('CHEVERETO_SERVICING') == 'docker' ? ' <span class="fab fa-docker"></span> Docker' : '')
                ],
                'mysql_version' => [
                    'label'        => _s('MySQL version'),
                    'content'    => $db->getAttr(PDO::ATTR_SERVER_VERSION)
                ],
                'mysql_server_info' => [
                    'label'        => _s('MySQL server info'),
                    'content'    => $db->getAttr(PDO::ATTR_SERVER_INFO)
                ],
                'graphics' => [
                    'label'        => _s('Graphics Library'),
                ],
                'file_uploads' => [
                    'label'        => _s('File uploads'),
                    'content'    => ini_get('file_uploads') == 1 ? _s('Enabled') : _s('Disabled')
                ],
                'max_upload_size' => [
                    'label'        => _s('Max. upload file size'),
                    'content'    => G\format_bytes(G\get_ini_bytes(ini_get('upload_max_filesize')))
                ],
                'max_post_size' => [
                    'label'        => _s('Max. post size'),
                    'content'    => ini_get('post_max_size') == 0 ? _s('Unlimited') : G\format_bytes(G\get_ini_bytes(ini_get('post_max_size')))
                ],
                'max_execution_time' => [
                    'label'        => _s('Max. execution time'),
                    'content'    => strtr(_n('%d second', '%d seconds', ini_get('max_execution_time')), ['%d' => ini_get('max_execution_time')])
                ],
                'memory_limit' => [
                    'label'        => _s('Memory limit'),
                    'content'    => G\format_bytes(G\get_ini_bytes(ini_get('memory_limit')))
                ],
                'rebuild_stats' => [
                    'label' => _s('Stats'),
                    'content' => '<a data-action="dashboardTool" data-tool="rebuildStats"><span class="fas fa-sync-alt"></span> '. _s('Rebuild stats') .'</a>'
                ],
                'connecting_ip' => [
                    'label'        => _s('Connecting IP'),
                    'content'    => G\get_client_ip() . ' <a data-modal="simple" data-target="modal-connecting-ip">' . _s('Not your IP?') . '</a>'
                ],
            ];
            if(ImageManagerStatic::getManager()->config['driver'] === 'imagick') {
                $system_values['graphics']['content'] = Imagick::getVersion()['versionString'];
            } else {
                $system_values['graphics']['content'] = 'GD Version ' . gd_info()['GD Version']
                    . ' JPEG:' . gd_info()['JPEG Support']
                    . ' GIF:' . gd_info()['GIF Read Support'] . '/' . gd_info()['GIF Create Support']
                    . ' PNG:' . gd_info()['PNG Support']
                    . ' WEBP:' . (gd_info()['WebP Support'] ?? 0)
                    . ' WBMP:' . gd_info()['WBMP Support']
                    . ' XBM:' . gd_info()['XBM Support'];
            }
            $handler::setVar('system_values', $system_values);
            $handler::setVar('totals', $totals);
            $handler::setVar('totals_display', $totals_display);

            break;

        case 'settings':

            $max_request_level = ($handler->request[1] ?? null) == 'pages' ? (in_array($handler->request[2] ?? null, ['edit', 'delete']) ? 6 : 5) : 4;

            if ($handler->isRequestLevel($max_request_level)) {
                return $handler->issue404();
            }

            $handler::setCond('show_submit', true);

            $settings_sections = [
                'website'                => _s('Website'),
                'content'                => _s('Content'),
                'pages'                    => _s('Pages'),
                'listings'                => _s('Listings'),
                'image-upload'            => _s('Image upload'),
                'categories'            => _s('Categories'),
                'users'                    => _s('Users'),
                'consent-screen'        => _s('Consent screen'),
                'flood-protection'        => _s('Flood protection'),
                'theme'                    => _s('Theme'),
                'homepage'                => _s('Homepage'),
                'banners'                => _s('Banners'),
                'system'                => _s('System'),
                'routing'                => _s('Routing'),
                'languages'                => _s('Languages'),
                'external-storage'        => _s('External storage'),
                'email'                    => _s('Email'),
                'social-networks'        => _s('Social networks'),
                'external-services'        => _s('External services'),
                'ip-bans'                => _s('IP bans'),
                'api'                    => 'API',
                'additional-settings'    => _s('Additional settings'),
                'tools'                    => _s('Tools'),
            ];

            $settings_sections_icons = [
                'website'             => 'fas fa-globe',
                'content'             => 'fas fa-cubes',
                'pages'               => 'fas fa-file',
                'listings'            => 'fas fa-th-list',
                'image-upload'        => 'fas fa-cloud-upload-alt',
                'categories'          => 'fas fa-columns',
                'users'               => 'fas fa-users-cog',
                'consent-screen'      => 'fas fa-desktop',
                'flood-protection'    => 'fas fa-faucet',
                'theme'               => 'fas fa-paint-brush',
                'homepage'            => 'fas fa-home',
                'banners'             => 'fas fa-scroll',
                'system'              => 'fas fa-server',
                'routing'             => 'fas fa-route',
                'languages'           => 'fas fa-language',
                'external-storage'    => 'fas fa-hdd',
                'email'               => 'fas fa-at',
                'social-networks'     => 'fas fa-people-arrows',
                'external-services'   => 'fas fa-concierge-bell',
                'ip-bans'             => 'fas fa-ban',
                'api'                 => 'fas fa-project-diagram',
                'additional-settings' => 'fas fa-plus',
                'tools'               => 'fas fa-tools',
            ];



            foreach ($settings_sections as $k => $v) {
                $current = ($handler->request[1] ?? null) ? ($handler->request[1] == $k) : ($k == 'website');
                $settings_sections[$k] = [
                    'icon' => $settings_sections_icons[$k],
                    'key'        => $k,
                    'label'        => $v,
                    'url'        => G\get_base_url($route_prefix . '/settings/' . $k),
                    'current'    => $current
                ];
                if ($current) {
                    $handler::setVar('settings', $settings_sections[$k]);
                    if (in_array($k, ['categories', 'ip-bans'])) {
                        $handler::setCond('show_submit', false);
                    }
                }
            }

            // Reject non-existing settings sections
            if (!empty($handler->request[1]) && !array_key_exists($handler->request[1], $settings_sections)) {
                return $handler->issue404();
            }

            $handler::setVar('settings_menu', $settings_sections);
            //$handler::setVar('tabs', $settings_sections);
            if(isset($handler->request[1])) {
                switch ($handler->request[1]) {
                    case 'homepage':
                        if (($_GET['action'] ?? '') == 'delete-cover' && isset($_GET['cover'])) {
                            $cover_index = $_GET['cover'] - 1;
                            $homecovers = CHV\getSetting('homepage_cover_images');
                            $cover_target = $homecovers[$cover_index];
                            if (!G\is_integer($_GET['cover'], ['min' => 0]) || !isset($cover_target)) {
                                $is_error = true;
                                $error_message = _s('Request denied');
                            }
                            if (is_array($homecovers) && count($homecovers) == 1) {
                                $is_error = true;
                                $input_errors[sprintf('homepage_cover_image_%s', $cover_index)] = _s("Can't delete all homepage cover images");
                            }
                            if (!$is_error) {
                                // Try to delete the image (disk)
                                if (!G\starts_with('default/', $cover_target['basename'])) {
                                    $cover_file = CHV_PATH_CONTENT_IMAGES_SYSTEM . $cover_target['basename'];
                                    unlinkIfExists($cover_file);
                                }
                                unset($homecovers[$cover_index]);
                                $homecovers = array_values($homecovers);
                                $homecovers_db = [];
                                foreach ($homecovers as $v) {
                                    $homecovers_db[] = $v['basename'];
                                }
                                CHV\Settings::update(['homepage_cover_image' => implode(',', $homecovers_db)]);
                                $_SESSION['is_changed'] = true;
                                G\redirect('dashboard/settings/homepage');
                            }
                        }
                        if (isset($_SESSION['is_changed'])) {
                            $is_changed = true;
                            $changed_message = _s('Homepage cover image deleted');
                            unset($_SESSION['is_changed']);
                        }
                        break;

                    case 'tools':
                        $handler::setCond('show_submit', false);
                        break;

                    case 'external-storage':
                        $disk_used_all = CHV\Stat::getTotals()['disk_used'];
                        $disk_used_external = CHV\DB::queryFetchSingle('SELECT SUM(storage_space_used) space_used FROM ' . CHV\DB::getTable('storages') . ';')['space_used'];
                        $storage_usage = [
                            'local'        => [
                                'label' => _s('Local'),
                                'bytes'    => $disk_used_all - $disk_used_external
                            ],
                            'external'    => [
                                'label' => _s('External'),
                                'bytes' => $disk_used_external
                            ]
                        ];
                        $storage_usage['all'] = [
                            'label' => _s('All'),
                            'bytes' => $storage_usage['local']['bytes'] + $storage_usage['external']['bytes']
                        ];
                        foreach ($storage_usage as $k => &$v) {
                            if (empty($v['bytes'])) {
                                $v['bytes'] = 0;
                            }
                            $v['link'] = '<a href="' . G\get_base_url('search/images/?q=storage:' . $k) . '" target="_blank">' . _s('search content') . '</a>';
                            $v['formatted_size'] = G\format_bytes($v['bytes'], 2);
                        }

                        $handler::setVar('storage_usage', $storage_usage);
                        break;

                    case 'pages':

                        // Check the sub-request
                        if (isset($handler->request[2])) {
                            switch ($handler->request[2]) {
                                case 'add':
                                    $settings_pages['title'] = _s('Add page');
                                    $settings_pages['doing'] = 'add';
                                    break;
                                case 'edit':
                                case 'delete':
                                    if (!filter_var($handler->request[3], FILTER_VALIDATE_INT)) {
                                        return $handler->issue404();
                                    }
                                    $page = CHV\Page::getSingle($handler->request[3], 'id');
                                    if ($page) {
                                        // Workaround for default pages
                                        if (G\starts_with('default/', $page['file_path'])) {
                                            $page['file_path'] = null;
                                        }
                                    } else {
                                        return $handler->issue404();
                                    }
                                    $handler::setvar('page', $page);
                                    if ($handler->request[2] == 'edit') {
                                        $settings_pages['title'] = _s('Edit page ID %s', $page['id']);
                                        $settings_pages['doing'] = 'edit';
                                        if (isset($_SESSION['dashboard_page_added'])) {
                                            if (isset($_SESSION['dashboard_page_added']['id']) && $_SESSION['dashboard_page_added']['id'] == $page['id']) {
                                                $is_changed = true;
                                                $changed_message = _s('The page has been added successfully.');
                                            }
                                            unset($_SESSION['dashboard_page_added']);
                                        }
                                    }
                                    if ($handler->request[2] == 'delete') {
                                        if (!$handler::checkAuthToken($_REQUEST['auth_token'] ?? null)) {
                                            $handler->template = 'request-denied';
                                            return;
                                        }
                                        CHV\Page::delete($page);
                                        $_SESSION['dashboard_page_deleted'] = [
                                            'id' => $page['id']
                                        ];
                                        G\redirect('dashboard/settings/pages');
                                    }
                                    break;
                                default:
                                    return $handler->issue404();
                                    break;
                            }
                        } else {
                            $pages = CHV\Page::getAll([], ['field' => 'sort_display', 'order' => 'asc']);
                            $handler::setVar('pages', $pages ?: []);
                            $settings_pages['doing'] = 'listing';
                            if (isset($_SESSION['dashboard_page_deleted'])) {
                                $is_changed = true;
                                $changed_message = _s('The page has been deleted.');
                                unset($_SESSION['dashboard_page_deleted']);
                            }
                            $handler::setCond('show_submit', false);
                        }

                        $handler::setvar('settings_pages', $settings_pages);

                        break;

                    case 'banners':
                        $stock_banners = [
                            'home' => [
                                'label'     => _s('Homepage'),
                                'placements' => [
                                    'banner_home_before_title' => [
                                        'label' => _s('Before main title (%s)', _s('homepage'))
                                    ],
                                    'banner_home_after_cta' => [
                                        'label' => _s('After call to action (%s)', _s('homepage'))
                                    ],
                                    'banner_home_after_cover' => [
                                        'label' => _s('After cover (%s)', _s('homepage'))
                                    ],
                                    'banner_home_after_listing' => [
                                        'label' => _s('After listing (%s)', _s('homepage'))
                                    ]
                                ]
                            ],
                            'listing' => [
                                'label'     => _s('Listings'),
                                'placements' => [
                                    'banner_listing_before_pagination' => [
                                        'label'    => _s('Before pagination'),
                                    ],
                                    'banner_listing_after_pagination' => [
                                        'label'    => _s('After pagination'),
                                    ]
                                ]
                            ],
                            'content' => [
                                'label'     => _s('Content (image and album)'),
                                'placements' => [
                                    'banner_content_tab-about_column' => [
                                        'label' => _s('Tab about column')
                                    ],
                                    'banner_content_before_comments' => [
                                        'label' => _s('Before comments')
                                    ]
                                ]
                            ],
                            'image' => [
                                'label'     => _s('Image page'),
                                'placements' => [
                                    'banner_image_image-viewer_top' => [
                                        'label' => _s('Inside viewer top (image page)'),
                                    ],
                                    'banner_image_image-viewer_foot' => [
                                        'label' => _s('Inside viewer foot (image page)'),
                                    ],
                                    'banner_image_after_image-viewer' => [
                                        'label' => _s('After image viewer (image page)')
                                    ],
                                    'banner_image_before_header' => [
                                        'label' => _s('Before header (image page)')
                                    ],
                                    'banner_image_after_header' => [
                                        'label' => _s('After header (image page)')
                                    ],
                                    'banner_image_footer' => [
                                        'label' => _s('Footer (image page)')
                                    ]
                                ]
                            ],
                            'album' => [
                                'label'     => _s('Album page'),
                                'placements' => [
                                    'banner_album_before_header' => [
                                        'label' => _s('Before header (album page)')
                                    ],
                                    'banner_album_after_header' => [
                                        'label' => _s('After header (album page)')
                                    ]
                                ]
                            ],
                            'user' => [
                                'label'     => _s('User profile page'),
                                'placements' => [
                                    'banner_user_after_top' => [
                                        'label' => _s('After top (user profile)')
                                    ],
                                    'banner_user_before_listing' => [
                                        'label' => _s('Before listing (user profile)')
                                    ]
                                ]
                            ],
                            'explore' => [
                                'label'     => _s('Explore page'),
                                'placements' => [
                                    'banner_explore_after_top' => [
                                        'label' => _s('After top (explore page)')
                                    ]
                                ]
                            ]
                        ];
                        $banners = [];
                        foreach ($stock_banners as $k => $stock_group) {
                            $banners[$k] = $stock_group;
                            $group_nsfw = [
                                'label'    => $stock_group['label'] . ' [' . _s('NSFW') . ']',
                                'placements' => []
                            ];
                            foreach ($stock_group['placements'] as $id => $placement) {
                                $group_nsfw['placements'][$id . '_nsfw'] = $placement;
                            }
                            $banners[$k . '_nsfw'] = $group_nsfw;
                        }
                        $handler::setVar('banners', $banners);
                        break;
                }
            }

            if ($_POST !== []) {
                if (!headers_sent()) {
                    header('X-XSS-Protection: 0');
                }

                /*** Do some cleaning... ***/

                // Remove bad formatting and duplicates
                if (isset($_POST['theme_home_uids'])) {
                    $_POST['theme_home_uids'] = implode(',', array_keys(array_flip(explode(',', trim(preg_replace(['/\s+/', '/,+/'], ['', ','], $_POST['theme_home_uids']), ',')))));
                }

                // Personal mode stuff
                if (isset($_POST['website_mode']) && $_POST['website_mode'] == 'personal') {
                    $_POST['website_mode_personal_routing'] = G\get_regex_match(CHV\getSetting('routing_regex'), $_POST['website_mode_personal_routing'], '#', 1);

                    if (!G\check_value($_POST['website_mode_personal_routing'])) {
                        $_POST['website_mode_personal_routing'] = '/';
                    }
                }

                if (!empty($_POST['homepage_cta_fn_extra'])) {
                    $_POST['homepage_cta_fn_extra'] = trim($_POST['homepage_cta_fn_extra']);
                }

                // Columns number
                foreach (['phone', 'phablet', 'laptop', 'desktop'] as $k) {
                    if (isset($_POST['listing_columns_' . $k])) {
                        $key = 'listing_columns_' . $k;
                        $val = $_POST[$key];
                        $_POST[$key] = (filter_var($val, FILTER_VALIDATE_INT) and $val > 0) ? $val : CHV\get_chv_default_setting($key);
                    }
                }

                // HEX color
                if (!empty($_POST['theme_main_color'])) {
                    $_POST['theme_main_color'] = '#' . ltrim($_POST['theme_main_color'], '#');
                }

                // Pages related cleaning
                if (($handler->request[1] ?? null) == 'pages') {
                    $page_file_path_clean = trim(G\sanitize_relative_path($_POST['page_file_path']), '/');

                    $_POST['page_file_path'] = str_replace('default/', null, $page_file_path_clean);
                    $_POST['page_file_path_absolute'] = CHV\Page::getPath($_POST['page_file_path']);

                    // Invalid page sort display
                    if (!filter_var($_POST['page_sort_display'], FILTER_VALIDATE_INT)) {
                        $_POST['page_sort_display'] = null;
                    }

                    // Do some fixing..
                    if (isset($_POST['page_type']) && $_POST['page_type'] == 'internal') {
                        if (!$_POST['page_is_active']) {
                            $_POST['page_is_link_visible'] = false;
                        }
                    } else {
                        $_POST['page_is_link_visible'] = $_POST['page_is_active'];
                    }
                    $handler::updateVar('safe_post', [
                        'page_is_active'            => $_POST['page_is_active'],
                        'page_is_link_visible'        => $_POST['page_is_link_visible'],
                        'page_file_path_absolute'    => $_POST['page_file_path_absolute'],
                    ]);
                }

                // Validations
                $validations = [
                    'website_name'    =>
                    [
                        'validate'    => isset($_POST['website_name']),
                        'error_msg'    => _s('Invalid website name')
                    ],
                    'default_language'    =>
                    [
                        'validate'    => isset($_POST['default_language']) && CHV\get_available_languages()[$_POST['default_language']] !== null,
                        'error_msg'    => _s('Invalid language')
                    ],
                    'default_timezone'    =>
                    [
                        'validate'    => isset($_POST['default_timezone']) && in_array($_POST['default_timezone'], timezone_identifiers_list()),
                        'error_msg'    => _s('Invalid timezone')
                    ],
                    'listing_items_per_page' =>
                    [
                        'validate'    => isset($_POST['listing_items_per_page']) && G\is_integer($_POST['listing_items_per_page'], ['min' => 1]),
                        'error_msg'    => _s('Invalid value: %s', $_POST['listing_items_per_page'] ?? '')
                    ],
                    'explore_albums_min_image_count' =>
                    [
                        'validate'    => isset($_POST['explore_albums_min_image_count']) && G\is_integer($_POST['explore_albums_min_image_count'], ['min' => 0]),
                        'error_msg'    => _s('Invalid value: %s', $_POST['explore_albums_min_image_count'] ?? '')
                    ],
                    'upload_threads' =>
                    [
                        'validate'    => isset($_POST['upload_threads']) && G\is_integer($_POST['upload_threads'], ['min' => 1, 'max' => 5]),
                        'error_msg'    => _s('Invalid value: %s', $_POST['upload_threads'] ?? '')
                    ],
                    'upload_storage_mode'    =>
                    [
                        'validate'    => isset($_POST['upload_storage_mode']) && in_array($_POST['upload_storage_mode'], ['datefolder', 'direct']),
                        'error_msg'    => _s('Invalid upload storage mode')
                    ],
                    'upload_filenaming'    =>
                    [
                        'validate'    => isset($_POST['upload_filenaming']) && in_array($_POST['upload_filenaming'], ['original', 'random', 'mixed', 'id']),
                        'error_msg'    => _s('Invalid upload filenaming')
                    ],
                    'upload_thumb_width' =>
                    [
                        'validate'    => isset($_POST['upload_thumb_width']) && G\is_integer($_POST['upload_thumb_width'], ['min' => 16]),
                        'error_msg'    => _s('Invalid thumb width')
                    ],
                    'upload_thumb_height' =>
                    [
                        'validate'    => isset($_POST['upload_thumb_height']) &&G\is_integer($_POST['upload_thumb_height'], ['min' => 16]),
                        'error_msg'    => _s('Invalid thumb height')
                    ],
                    'upload_medium_size' =>
                    [
                        'validate'    => isset($_POST['upload_medium_size']) && G\is_integer($_POST['upload_medium_size'], ['min' => 16]),
                        'error_msg'    => _s('Invalid medium size')
                    ],
                    'watermark_percentage' =>
                    [
                        'validate'     => isset($_POST['watermark_percentage']) &&G\is_integer($_POST['watermark_percentage'], ['min' => 1, 'max' => 100]),
                        'error_msg'    => _s('Invalid watermark percentage')
                    ],
                    'watermark_opacity' =>
                    [
                        'validate'     => isset($_POST['watermark_opacity']) && G\is_integer($_POST['watermark_opacity'], ['min' => 0, 'max' => 100]),
                        'error_msg'    => _s('Invalid watermark opacity')
                    ],
                    'theme'    =>
                    [
                        'validate'    => isset($_POST['theme']) && file_exists(G_APP_PATH_THEMES . $_POST['theme']),
                        'error_msg'    => _s('Invalid theme')
                    ],
                    'theme_logo_height' =>
                    [
                        'validate'    => !empty($_POST['theme_logo_height']) ? (G\is_integer($_POST['theme_logo_height'], ['min' => 0])) : true,
                        'error_msg'    => _s('Invalid value')
                    ],
                    'theme_tone' =>
                    [
                        'validate'    => isset($_POST['theme_tone']) && in_array($_POST['theme_tone'], ['light', 'dark']),
                        'error_msg'    => _s('Invalid theme tone')
                    ],
                    'theme_main_color' =>
                    [
                        'validate'    => isset($_POST['theme_main_color']) && G\check_value($_POST['theme_main_color']) ? G\is_valid_hex_color($_POST['theme_main_color']) : true,
                        'error_msg'    => _s('Invalid theme main color')
                    ],
                    'theme_top_bar_button_color' =>
                    [
                        'validate'    => isset($_POST['theme_top_bar_button_color']) && in_array($_POST['theme_top_bar_button_color'], CHV\getSetting('available_button_colors')),
                        'error_msg'    => _s('Invalid theme top bar button color')
                    ],
                    'theme_image_listing_sizing' =>
                    [
                        'validate'    => isset($_POST['theme_image_listing_sizing']) && in_array($_POST['theme_image_listing_sizing'], ['fluid', 'fixed']),
                        'error_msg'    => _s('Invalid theme image listing size')
                    ],
                    'theme_home_uids' =>
                    [
                        'validate'    => !empty($_POST['theme_home_uids']) ? preg_match('/^[0-9]+(\,[0-9]+)*$/', $_POST['theme_home_uids']) : true,
                        'error_msg'    => _s('Invalid user id')
                    ],
                    'email_mode'        =>
                    [
                        'validate'    => isset($_POST['email_mode']) && isset($_POST['email_mode']) && in_array($_POST['email_mode'], ['smtp', 'mail']),
                        'error_msg'    => _s('Invalid email mode')
                    ],
                    'email_smtp_server_port' =>
                    [
                        'validate'    => isset($_POST['email_smtp_server_port']) && in_array($_POST['email_smtp_server_port'], [25, 80, 465, 587]),
                        'error_msg'    => _s('Invalid SMTP port')
                    ],
                    'email_smtp_server_security'    =>
                    [
                        'validate'    => isset($_POST['email_smtp_server_security']) && in_array($_POST['email_smtp_server_security'], ['tls', 'ssl', 'unsecured']),
                        'error_msg'    => _s('Invalid SMTP security')
                    ],
                    'website_mode' =>
                    [
                        'validate'    => isset($_POST['website_mode']) && in_array($_POST['website_mode'], ['community', 'personal']),
                        'error_msg'    => _s('Invalid website mode')
                    ],
                    'website_mode_personal_uid' =>
                    [
                        'validate'    => isset($_POST['website_mode'], $_POST['website_mode_personal_uid']) && $_POST['website_mode'] == 'personal' ? (G\is_integer($_POST['website_mode_personal_uid'], ['min' => 0])) : true,
                        'error_msg'    => _s('Invalid personal mode user ID')
                    ],
                    'website_mode_personal_routing' =>
                    [
                        'validate'    => isset($_POST['website_mode'], $_POST['website_mode_personal_routing']) && $_POST['website_mode'] == 'personal' ? !G\is_route_available($_POST['website_mode_personal_routing']) : true,
                        'error_msg'    => _s('Invalid or reserved route')
                    ],
                    'website_privacy_mode' =>
                    [
                        'validate'    => isset($_POST['website_privacy_mode']) && in_array($_POST['website_privacy_mode'], ['public', 'private']),
                        'error_msg'    => _s('Invalid website privacy mode')
                    ],
                    'website_content_privacy_mode'    =>
                    [
                        'validate'    => isset($_POST['website_content_privacy_mode']) && in_array($_POST['website_content_privacy_mode'], ['default', 'private', 'private_but_link']),
                        'error_msg'    => _s('Invalid website content privacy mode')
                    ],
                    'homepage_style' =>
                    [
                        'validate'    => isset($_POST['homepage_style']) && in_array($_POST['homepage_style'], ['landing', 'split', 'route_explore', 'route_upload']),
                        'error_msg'    => _s('Invalid homepage style')
                    ],
                    'homepage_cta_color' =>
                    [
                        'validate'    => isset($_POST['homepage_cta_color']) && in_array($_POST['homepage_cta_color'], CHV\getSetting('available_button_colors')),
                        'error_msg'    => _s('Invalid homepage call to action button color')
                    ],
                    'homepage_cta_fn' =>
                    [
                        'validate'    => (isset($_POST['homepage_style'], $_POST['homepage_cta_fn'])
                            ? ($_POST['homepage_style'] == 'route_explore' ? true : in_array($_POST['homepage_cta_fn'], ['cta-upload', 'cta-link']))
                            : false),
                        'error_msg'    => _s('Invalid homepage call to action functionality')
                    ],
                    // PAGES
                    'page_title' =>
                    [
                        'validate'    => !empty($_POST['page_title']),
                        'error_msg'    => _s('Invalid title')
                    ],
                    'page_is_active' =>
                    [
                        'validate'    => isset($_POST['page_is_active']) && in_array($_POST['page_is_active'], ['1', '0']),
                        'error_msg'    => _s('Invalid status')
                    ],
                    'page_type' =>
                    [
                        'validate'    => isset($_POST['page_type']) && in_array($_POST['page_type'], ['internal', 'link']),
                        'error_msg'    => _s('Invalid type')
                    ],
                    'page_is_link_visible' =>
                    [
                        'validate'    => isset($_POST['page_type'], $_POST['page_is_link_visible']) && $_POST['page_type'] == 'internal' ? in_array($_POST['page_is_link_visible'], ['1', '0']) : true,
                        'error_msg'    => _s('Invalid visibility')
                    ],
                    'page_internal' =>
                    [
                        'validate'    => isset($_POST['page_type'], $_POST['page_internal']) && ($_POST['page_type'] == 'internal' && $_POST['page_internal']) ? in_array($_POST['page_internal'], ['tos', 'privacy', 'contact']) : true,
                        'error_msg'    => _s('Invalid internal type')
                    ],
                    'page_attr_target' =>
                    [
                        'validate'    => isset($_POST['page_attr_target']) && in_array($_POST['page_attr_target'], ['_self', '_blank']),
                        'error_msg'    => _s('Invalid target attribute')
                    ],
                    'page_attr_rel' =>
                    [
                        'validate'    => !empty($_POST['page_attr_rel']) ? preg_match('/^[\w\s\-]+$/', $_POST['page_attr_rel']) : true,
                        'error_msg'    => _s('Invalid rel attribute')
                    ],
                    'page_icon' =>
                    [
                        'validate'    => !empty($_POST['page_icon']) ? preg_match('/^[\w\s\-]+$/', $_POST['page_icon']) : true,
                        'error_msg'    => _s('Invalid icon')
                    ],
                    'page_url_key' =>
                    [
                        'validate'    => isset($_POST['page_type'], $_POST['page_url_key']) && $_POST['page_type'] == 'internal' ? preg_match('/^[\w\-\_\/]+$/', $_POST['page_url_key']) : true,
                        'error_msg'    => _s('Invalid URL key')
                    ],
                    'page_file_path' =>
                    [
                        'validate'    => isset($_POST['page_type'], $_POST['page_file_path']) && $_POST['page_type'] == 'internal' ? preg_match('/^[\w\-\_\/]+\.' . (G\get_app_setting('disable_php_pages') ? 'html' : 'html|php') . '$/', $_POST['page_file_path']) : true,
                        'error_msg'    => _s('Invalid file path')
                    ],
                    'page_link_url' =>
                    [
                        'validate'    => isset($_POST['page_type'], $_POST['page_link_url']) && $_POST['page_type'] == 'link' ? is_url_web($_POST['page_link_url']) : true,
                        'error_msg'    => _s('Invalid link URL')
                    ],
                    'user_minimum_age' =>
                    [
                        'validate'    => isset($_POST['user_minimum_age']) && $_POST['user_minimum_age'] !== '' ? G\is_integer($_POST['user_minimum_age'], ['min' => 0]) : true,
                        'error_msg'    => _s('Invalid user minimum age')
                    ],
                    'route_image' =>
                    [
                        'validate'    => isset($_POST['route_image']) && preg_match('/^[\w\d\-\_]+$/', $_POST['route_image']),
                        'error_msg'    => _s('Only alphanumeric, hyphen and underscore characters are allowed')
                    ],
                    'route_album' =>
                    [
                        'validate'    => isset($_POST['route_album']) && preg_match('/^[\w\d\-\_]+$/', $_POST['route_album']),
                        'error_msg'    => _s('Only alphanumeric, hyphen and underscore characters are allowed')
                    ],
                    'image_load_max_filesize_mb' =>
                    [
                        'validate'    => isset($_POST['image_load_max_filesize_mb']) && $_POST['image_load_max_filesize_mb'] !== '' ? G\is_integer($_POST['image_load_max_filesize_mb'], ['min' => 0]) : true,
                        'error_msg'    => _s('Invalid value: %s', $_POST['image_load_max_filesize_mb'] ?? '')
                    ],
                    'upload_max_image_width' =>
                    [
                        'validate'    => isset($_POST['upload_max_image_width']) && G\is_integer($_POST['upload_max_image_width'], ['min' => 0]),
                        'error_msg'    => _s('Invalid value: %s', $_POST['upload_max_image_width'] ?? '')
                    ],
                    'upload_max_image_height' =>
                    [
                        'validate'    => isset($_POST['upload_max_image_height']) && G\is_integer($_POST['upload_max_image_height'], ['min' => 0]),
                        'error_msg'    => _s('Invalid value: %s', $_POST['upload_max_image_height'] ?? '')
                    ],
                    'auto_delete_guest_uploads' =>
                    [
                        'validate'    => isset($_POST['auto_delete_guest_uploads']) && $_POST['auto_delete_guest_uploads'] !== null && array_key_exists($_POST['auto_delete_guest_uploads'], CHV\Image::getAvailableExpirations()),
                        'error_msg'    => _s('Invalid value: %s', $_POST['auto_delete_guest_uploads'] ?? '')
                    ],
                    'sdk_pup_url' =>
                    [
                        'validate'    => isset($_POST['sdk_pup_url']) && $_POST['sdk_pup_url'] ? is_url_web($_POST['sdk_pup_url']) : true,
                        'error_msg'    => _s('Invalid URL')
                    ],
                ];

                if (isset($_POST['route_image'], $_POST['route_album']) && $_POST['route_image'] == $_POST['route_album']) {
                    $validations['route_image'] = [
                        'validate'    => false,
                        'error_msg'    => _s("Routes can't be the same")
                    ];
                    $validations['route_album'] = $validations['route_image'];
                }

                foreach (CHV\Login::getSocialServices(['flat' => true]) as $v) {
                    if(!isset($_POST[$v])) {
                        continue;
                    }
                    $validations[$v] = ['validate' => in_array($_POST[$v], [0, 1]) ? true : false];
                }

                if (isset($_POST['upload_image_path'])) {
                    $safe_upload_image_path = rtrim(G\sanitize_relative_path($_POST['upload_image_path']), '/');
                    $image_path = G_ROOT_PATH . $_POST['upload_image_path'];
                    if (!file_exists($image_path)) {
                        $validations['upload_image_path'] = [
                            'validate'    => false,
                            'error_msg' => _s('Invalid upload image path')
                        ];
                    }
                }

                if (isset($_POST['homepage_style']) && $_POST['homepage_style'] !== 'route_explore' and $_POST['homepage_cta_fn'] == 'cta-link' and !G\is_url($_POST['homepage_cta_fn_extra'])) {
                    if (!empty($_POST['homepage_cta_fn_extra'])) {
                        $_POST['homepage_cta_fn_extra'] = rtrim(G\sanitize_relative_path($_POST['homepage_cta_fn_extra']), '/');
                        $_POST['homepage_cta_fn_extra'] = G\get_regex_match(CHV\getSetting('routing_regex_path'), '#', $_POST['homepage_cta_fn_extra'], 1);
                    } else {
                        $validations['homepage_cta_fn_extra'] = [
                            'validate'    => false,
                            'error_msg' => _s('Invalid call to action URL')
                        ];
                    }
                }

                foreach (['upload_max_filesize_mb', 'upload_max_filesize_mb_guest', 'user_image_avatar_max_filesize_mb', 'user_image_background_max_filesize_mb'] as $k) {
                    unset($error_max_filesize);
                    if (isset($_POST[$k])) {
                        if (!is_numeric($_POST[$k]) or $_POST[$k] == 0) {
                            $error_max_filesize = _s('Invalid value');
                        } else {
                            if (G\get_bytes($_POST[$k] . 'MB') > CHV\Settings::get('true_upload_max_filesize')) {
                                $error_max_filesize = _s('Max. allowed %s', G\format_bytes(CHV\Settings::get('true_upload_max_filesize')));
                            }
                        }
                        $validations[$k] = ['validate' => isset($error_max_filesize) ? false : true, 'error_msg' => $error_max_filesize ?? ''];
                    }
                }

                $validate_routes = [];
                foreach (['image', 'album'] as $k) {
                    $route = 'route_' . $k;
                    if(!isset($_POST[$route])) {
                        continue;
                    }
                    if (file_exists(G_ROOT_PATH . $_POST[$route])) {
                        $validations[$route] = [
                            'validate'    => false,
                            'error_msg' => _s("Can't map %m to an existing folder (%f)", ['%m' => '/' . $k, '%f' => '/' . $_POST[$route]])
                        ];
                        continue;
                    }
                    if (isset($_POST[$route]) && $_POST[$route] !== $k && $validations[$route]['validate']) {
                        if (G\is_route_available($_POST[$route])) {
                            $validations[$route] = [
                                'validate'    => false,
                                'error_msg' => _s("Can't map %m to an existing route (%r)", ['%m' => '/' . $k, '%r' => '/' . $_POST[$route]])
                            ];
                        } else {
                            $user_exists = CHV\User::getSingle($_POST[$route], 'username', false);
                            if ($user_exists) {
                                $validations[$route] = [
                                    'validate'    => false,
                                    'error_msg' => _s("Can't map %m to %r (username collision)", ['%m' => '/' . $k, '%r' => '/' . $_POST[$route]])
                                ];
                            }
                        }
                    }
                }
                if (isset($_POST['image_format_enable']) && is_array($_POST['image_format_enable'])) {
                    $image_format_enable = [];
                    foreach ($_POST['image_format_enable'] as $v) {
                        if (in_array($v, CHV\Upload::getAvailableImageFormats())) {
                            $image_format_enable[] = $v;
                        }
                    }
                    $_POST['upload_enabled_image_formats'] = implode(',', $image_format_enable);
                }
                if (isset($_POST['languages_enable']) && is_array($_POST['languages_enable'])) {
                    if (!in_array($_POST['default_language'], $_POST['languages_enable'])) {
                        $_POST['languages_enable'][] = $_POST['default_language'];
                    }
                    $enabled_languages = [];
                    $disabled_languages = CHV\get_available_languages();
                    $_POST['languages_disable'] = [];
                    foreach ($_POST['languages_enable'] as $k) {
                        if (!array_key_exists($k, CHV\get_available_languages())) {
                            continue;
                        }
                        $enabled_languages[$k] = CHV\get_available_languages()[$k];
                        unset($disabled_languages[$k]);
                    }
                    CHV\l10n::setStatic('disabled_languages', $disabled_languages);
                    CHV\l10n::setStatic('enabled_languages', $enabled_languages);
                    unset($_POST['languages_enable']);
                    foreach ($disabled_languages as $k => $v) {
                        $_POST['languages_disable'][] = $k;
                    }
                    $_POST['languages_disable'] = implode(',', $_POST['languages_disable']);
                }
                if (isset($_POST['website_mode']) && $_POST['website_mode'] == 'personal' and isset($_POST['website_mode_personal_routing'])) {
                    if ($logged_user['id'] == $_POST['website_mode_personal_uid']) {
                        $new_user_url =  G\get_base_url($_POST['website_mode_personal_routing'] !== '/' ? $_POST['website_mode_personal_routing'] : null);
                        CHV\Login::setUser('url', G\get_base_url($_POST['website_mode_personal_routing'] !== '/' ? $_POST['website_mode_personal_routing'] : null));
                        CHV\Login::setUser('url_albums', CHV\User::getUrlAlbums(CHV\Login::getUser()['url']));
                    } elseif (!CHV\User::getSingle($_POST['website_mode_personal_uid'])) { // Is a valid user id anyway?
                        $validations['website_mode_personal_uid'] = [
                            'validate' => false,
                            'error_msg' => _s('Invalid personal mode user ID')
                        ];
                    }
                }
                $content_image_props = [];
                foreach (CHV\getSetting('homepage_cover_images') ?? [] as $k => $v) {
                    $content_image_props[] = sprintf('homepage_cover_image_%s', $k);
                }
                $content_image_props = array_merge($content_image_props, ['logo_vector', 'logo_image', 'favicon_image', 'watermark_image', 'consent_screen_cover_image', 'homepage_cover_image_add']);
                foreach ($content_image_props as $k) {
                    if (!empty($_FILES[$k]['tmp_name'])) {
                        try {
                            CHV\upload_to_content_images($_FILES[$k], $k);
                        } catch(Throwable $e) {
                            $validations[$k] = [
                                'validate' => false,
                                'error_msg' => $e->getMessage(),
                            ];
                        }
                    }
                }

                if (isset($_POST['moderatecontent']) && $_POST['moderatecontent'] == 1) {
                    $moderateContentKey = CHV\getSetting('moderatecontent_key');
                    if (isset($_POST['moderatecontent_key'])) {
                        $moderateContentKey = $_POST['moderatecontent_key'];
                    }
                    $sample = 'http://www.moderatecontent.com/img/sample_face_2.jpg';
                    $json = G\fetch_url('https://api.moderatecontent.com/moderate/?key='.$moderateContentKey.'&url=' . $sample);
                    $data = json_decode($json);
                    if (isset($data->error)) {
                        $validations['moderatecontent_key'] = [
                            'validate'    => false,
                            'error_msg' => $data->error
                        ];
                    }
                }

                // Validate SMTP credentials
                if (isset($_POST['email_mode']) && $_POST['email_mode'] == 'smtp') {
                    $email_smtp_validate = [
                        'email_smtp_server'             => _s('Invalid SMTP server'),
                        'email_smtp_server_username'    => _s('Invalid SMTP username'),
                        //'email_smtp_server_password'	=> _s('Invalid SMTP password')
                    ];
                    foreach ($email_smtp_validate as $k => $v) {
                        $validations[$k] = ['validate' => $_POST[$k] ? true : false, 'error_msg' => $v];
                    }

                    $email_validate = ['email_smtp_server', 'email_smtp_server_port', 'email_smtp_server_username', /*'email_smtp_server_password',*/ 'email_smtp_server_security'];
                    $email_error = false;
                    foreach ($email_validate as $k) {
                        if (!$validations[$k]['validate']) {
                            $email_error = true;
                        }
                    }

                    if (!$email_error) {
                        try {
                            $mail = new Mailer(true);
                            $mail->SMTPAuth = true;
                            $mail->SMTPSecure = $_POST['email_smtp_server_security'];
                            $mail->SMTPAutoTLS = in_array($_POST['email_smtp_server_security'], ['ssl', 'tls']);
                            $mail->Username = $_POST['email_smtp_server_username'];
                            $mail->Password = $_POST['email_smtp_server_password'];
                            $mail->Host = $_POST['email_smtp_server'];
                            $mail->Port = $_POST['email_smtp_server_port'];
                            if (CHV\getSetting('error_reporting') or G\get_app_setting('debug_level') !== 0) {
                                $mail->SMTPDebug = 2;
                                $GLOBALS['SMTPDebug'] = '';
                                $mail->Debugoutput = function ($str) {
                                    $GLOBALS['SMTPDebug'] .= "$str\n";
                                };
                                if (strlen($GLOBALS['SMTPDebug']) > 0) {
                                    $GLOBALS['SMTPDebug'] = "SMTP Debug>>\n" . $GLOBALS['SMTPDebug'];
                                }
                            }
                            $valid_mail_credentials = $mail->SmtpConnect();
                        } catch (Exception $e) {
                            $GLOBALS['SMTPDebug'] = "SMTP Exception>>\n" . ($mail->ErrorInfo ?: $e->getMessage());
                        }
                        if (!$valid_mail_credentials) {
                            foreach ($email_smtp_validate as $k => $v) {
                                $validations[$k]['validate'] = false;
                            }
                        }
                    }
                }

                // Validate social networks
                $social_validate = [
                    'facebook'    => ['facebook_app_id', 'facebook_app_secret'],
                    'twitter'    => ['twitter_api_key', 'twitter_api_secret'],
                    'google'    => ['google_client_id', 'google_client_secret'],
                ];
                foreach ($social_validate as $k => $v) {
                    if (isset($_POST[$k]) && $_POST[$k] == 1) {
                        foreach ($v as $vv) {
                            $validations[$vv] = ['validate' => $_POST[$vv] ? true : false];
                        }
                    }
                }

                // Validate Akismet
                if (isset($_POST['akismet']) && $_POST['akismet'] == 1) {
                    $akismet = new Akismet($_POST['akismet_api_key'], G\get_base_url());
                    $validations['akismet_api_key'] = [
                        'validate' => $akismet->verifyKey(),
                        'error_msg' => _s('Invalid key')
                    ];
                }

                // Validate CDN
                if (isset($_POST['cdn']) && $_POST['cdn'] == 1) {
                    $cdn_url = trim($_POST['cdn_url'], '/') . '/';
                    if (!G\is_url($cdn_url)) {
                        $cdn_url = 'http://' . $cdn_url;
                    }
                    if (!G\is_url($cdn_url) and !G\is_valid_url($cdn_url)) {
                        $validations['cdn_url'] = [
                            'validate' => false,
                            'error_msg' => _s('Invalid URL')
                        ];
                    } else {
                        $_POST['cdn_url'] = $cdn_url;
                        $handler::updateVar('safe_post', ['cdn_url' => $cdn_url]);
                    }
                }

                // Validate recaptcha
                if (isset($_POST['recaptcha']) && $_POST['recaptcha'] == 1) {
                    foreach (['recaptcha_public_key', 'recaptcha_private_key'] as $v) {
                        $validations[$v] = ['validate' => $_POST[$v] ? true : false];
                    }
                }

                // Run the thing
                foreach ($_POST + $_FILES as $k => $v) {
                    if (isset($validations[$k]) and !$validations[$k]['validate']) {
                        $input_errors[$k] = $validations[$k]['error_msg'] ?? _s('Invalid value');
                    }
                }

                // Test target page path and URL key
                if (isset($_POST[$route]) && $_POST[$route] == 'pages' && in_array($handler->request[2], ['edit', 'add']) && $_POST['page_type'] == 'internal') {
                    if ($page) {
                        $try_page_db = ($page['url_key'] !== $_POST['url_key']) or ($page['file_path'] !== $_POST['page_file_path']);
                    } else {
                        $try_page_db = true;
                    }
                    if ($try_page_db) {
                        $db = CHV\DB::getInstance();
                        $db->query('SELECT * FROM ' . CHV\DB::getTable('pages') . ' WHERE page_url_key = :page_url_key OR page_file_path = :page_file_path');
                        $db->bind(':page_url_key', $_POST['page_url_key']);
                        $db->bind(':page_file_path', $_POST['page_file_path']);
                        $page_fetch_db = $db->fetchAll();
                        if ($page_fetch_db) {
                            foreach ($page_fetch_db as $k => $v) {
                                foreach ([
                                    'page_url_key'        => _s('This URL key is already being used by another page (ID %s)'),
                                    'page_file_path'    => _s('This file path is already being used by another page (ID %s)')
                                ] as $kk => $vv) {
                                    if ($page and $page['id'] == $v['page_id']) {
                                        continue; // Skip on same thing
                                    }
                                    if (G\timing_safe_compare($v[$kk], $_POST[$kk])) {
                                        $input_errors[$kk] = sprintf($vv, $v['page_id']);
                                    }
                                }
                            }
                        }
                    }
                }
                if (is_array($input_errors) && count($input_errors) == 0) {
                    if (isset($handler->request[1]) && $handler->request[1] == 'pages') {
                        if (in_array($handler->request[2], ['edit', 'add']) and $_POST['page_type'] == 'internal') {
                            $page_write_code = (array_key_exists('page_code', $_POST)) ? (!empty($_POST['page_code']) ? html_entity_decode($_POST['page_code']) : null) : null;
                            try {
                                CHV\Page::writePage(['file_path' => $_POST['page_file_path'], 'code' => $page_write_code]);
                                if ($handler->request[2] == 'edit' and !is_null($page['file_path']) and !G\timing_safe_compare($page['file_path'], $_POST['page_file_path'])) {
                                    unlinkIfExists(CHV\Page::getPath($page['file_path']));
                                }
                                CHV\Page::update($page['id'], ['code' => $page_write_code]);
                            } catch (Exception $e) {
                                $input_errors['page_code'] = _s("Can't save page contents: %s.", $e->getMessage());
                            }
                        }
                        if (isset($_POST['page_internal']) && $_POST['page_internal'] == '') {
                            $_POST['page_internal'] = null;
                        }
                        $page_fields = CHV\Page::getFields();
                        $page_values = [];
                        foreach ($page_fields as $v) {
                            $_post = $_POST['page_' . $v];
                            if ($handler->request[2] == 'edit') {
                                if (G\timing_safe_compare($page[$v], $_post)) {
                                    continue;
                                } // Skip not updated values
                            }
                            $page_values[$v] = $_post;
                        }
                        if ($page_values) {
                            if ($handler->request[2] == 'add') {
                                $page_inserted = CHV\Page::insert($page_values);
                                $_SESSION['dashboard_page_added'] = ['id' => $page_inserted];
                                G\redirect(G\get_base_url('dashboard/settings/pages/edit/' . $page_inserted));
                            } else {
                                CHV\Page::update($page['id'], $page_values);

                                $is_changed = true;
                                $pages_sort_changed = false;
                                foreach (['sort_display', 'is_active', 'is_link_visible'] as $k) {
                                    if (isset($page[$k], $page_values[$k]) && $page[$k] !== $page_values[$k]) {
                                        $pages_sort_changed = true;
                                        break;
                                    }
                                }

                                // Update 'page' var
                                $page = array_merge($handler::getVar('page'), $page_values);
                                CHV\Page::fill($page);
                                $handler::updateVar('page', $page);

                                // Update pages_link_visible (menu)
                                $pages_link_visible = $handler::getVar('pages_link_visible');

                                $pages_link_visible[$page['id']] = $page; // Either update or append

                                if (!$page['is_active'] or !$page['is_link_visible']) {
                                    unset($pages_link_visible[$page['id']]);
                                } elseif ($pages_sort_changed) { // Need to update the sort display?
                                    uasort($pages_link_visible, function ($a, $b) {
                                        return $a['sort_display'] - $b['sort_display'];
                                    });
                                }
                                $handler::setVar('pages_link_visible', $pages_link_visible);
                            }
                        }
                    } else { // Settings

                        $update_settings = [];
                        foreach (CHV\getSettings() as $k => $v) {
                            if (isset($_POST[$k]) && $_POST[$k] != (is_bool(CHV\getSetting($k)) ? (CHV\getSetting($k) ? 1 : 0) : CHV\getSetting($k))) {
                                $update_settings[$k] = $_POST[$k];
                            }
                        }
                        if (!empty($update_settings)) {
                            $update = CHV\Settings::update($update_settings);
                            if ($update) {
                                $is_changed = true;
                                $reset_notices = false;
                                $settings_to_vars = [
                                    'website_doctitle' => 'doctitle',
                                    'website_description' => 'meta_description',
                                ];
                                foreach ($update_settings as $k => $v) {
                                    if ($k == 'maintenance') {
                                        $reset_notices = true;
                                    }
                                    if (array_key_exists($k, $settings_to_vars)) {
                                        $handler::setVar($settings_to_vars[$k], CHV\getSetting($k));
                                    }
                                }
                                if ($reset_notices) {
                                    $system_notices = CHV\getSystemNotices();
                                    $handler::setVar('system_notices', $system_notices);
                                }
                            }
                        }
                    }
                } else {
                    $is_error = true;
                }
            }

            break;

        case 'images':
        case 'albums':
        case 'users':
            $tabs = CHV\Listing::getTabs([
                'listing'    => $doing,
                'tools'        => true,
            ]);
            $type = $doing;
            $current = false;
            foreach ($tabs as $k => $v) {
                if ($v['current']) {
                    $current = $k;
                }
                $tabs[$k]['type'] = $type;
                $tabs[$k]['url'] = G\get_base_url('dashboard/' . $type . '/?' . $tabs[$k]['params']);
            }
            if (!$current) {
                $current = 0;
                $tabs[0]['current'] = true;
            }
            $list_params = CHV\Listing::getParams();
            $handler::setVar('list_params', $list_params);
            parse_str($tabs[$current]['params'], $tab_params);
            preg_match('/(.*)\_(asc|desc)/', !empty($_REQUEST['sort']) ? $_REQUEST['sort'] : $tab_params['sort'], $sort_matches);
            $list_params['sort'] = array_slice($sort_matches, 1);
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
            $list->setRequester($logged_user);
            $list->output_tpl = $type;
            $list->exec();

            break;
    }
    $pre_doctitle = [_s('Dashboard')];
    if ($doing != 'stats') {
        $pre_doctitle[] = $routes[$doing];
        if ($doing == 'settings') {
            reset($settings_sections);
            $firstKey = key($settings_sections);
            $dashSettingsProp = $handler::getVar('settings');
            if ($dashSettingsProp['key'] != $firstKey) {
                $pre_doctitle[] = $dashSettingsProp['label'];
            }
        }
    }
    $handler::setVar('pre_doctitle', implode(' - ', $pre_doctitle));
    $handler::setCond('error', $is_error);
    $handler::setCond('changed', $is_changed);
    $handler::setVar('error_message', $error_message);
    $handler::setVar('input_errors', $input_errors);
    $handler::setVar('changed_message', $changed_message ?? null);
    if (isset($tabs)) {
        $handler::setVar('sub_tabs', $tabs);
    }
    if (isset($list)) {
        $handler::setVar('list', $list);
    }
    $handler::setVar('share_links_array', CHV\render\get_share_links());
};
