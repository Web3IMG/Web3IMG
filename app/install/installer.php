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

namespace CHV;

use G;
use Exception;
use LogicException;
use Throwable;

if (!defined('access') or !access) {
    die('This file cannot be directly accessed.');
}
if (!is_null(getSetting('chevereto_version_installed'))) {
    if (PHP_SAPI !== 'cli' && !Login::isAdmin()) {
        G\set_status_header(403);
        throw new LogicException('Request denied. You must be an admin to be here.', 403);
    }
}
if(PHP_SAPI !== 'cli') {
    try {
        set_time_limit(600);
    } catch(Throwable $e) {
        // Ignore
    }
}
if (function_exists('opcache_reset')) {
    try {
        opcache_reset();
    } catch(Throwable $e) {
        // Ignore, Zend OPcache API is restricted by "restrict_api" configuration directive
    }
}
$doctitles = [
    'connect' => 'Connect to the database',
    'ready' => 'Ready to install',
    'finished' => 'Installation complete',
    'settings' => 'Update settings.php',
    'already' => _s('Already installed'),
    'update' => 'Update needed',
    'updated' => 'Update complete',
    'update_failed' => 'Update failed',
];
$doing = 'connect';
$db_array = [
    'db_host' => true,
    'db_name' => true,
    'db_user' => true,
    'db_pass' => false,
    'db_table_prefix' => false,
];
$error = false;
$db_conn_error = "Can't connect to the target database. The server replied with this:<br>%s<br><br>Please fix your MySQL info.";
$settings_updates = [
    '3.0.0' => [
        'analytics_code' => null,
        'auto_language' => 1,
        'chevereto_version_installed' => G_APP_VERSION,
        // 'cloudflare' => null, // deprecated @3.14.2
        'comment_code' => null,
        'crypt_salt' => G\random_string(8),
        'default_language' => 'en',
        'default_timezone' => 'America/Santiago',
        'email_from_email' => 'from@chevereto.example',
        'email_from_name' => 'Chevereto',
        'email_incoming_email' => 'incoming@chevereto.example',
        'email_mode' => 'mail',
        'email_smtp_server' => null,
        'email_smtp_server_password' => null,
        'email_smtp_server_port' => null,
        'email_smtp_server_security' => null,
        'email_smtp_server_username' => null,
        'enable_uploads' => 1,
        'error_reporting' => 0,
        'facebook' => 0,
        'facebook_app_id' => null,
        'facebook_app_secret' => null,
        'flood_uploads_day' => '1000',
        'flood_uploads_hour' => '500',
        'flood_uploads_minute' => '50',
        'flood_uploads_month' => '10000',
        'flood_uploads_notify' => 0,
        'flood_uploads_protection' => 1,
        'flood_uploads_week' => '5000',
        'google' => 0,
        'google_client_id' => null,
        'google_client_secret' => null,
        'guest_uploads' => 1,
        'listing_items_per_page' => '24',
        'maintenance' => 0,
        'recaptcha' => 0,
        'recaptcha_private_key' => null,
        'recaptcha_public_key' => null,
        'recaptcha_threshold' => '5',
        'theme' => 'Peafowl',
        'twitter' => 0,
        'twitter_api_key' => null,
        'twitter_api_secret' => null,
        'upload_filenaming' => 'original',
        'upload_image_path' => 'images',
        'upload_max_filesize_mb' => min(25, G\bytes_to_mb(G\get_ini_bytes(ini_get('upload_max_filesize')))),
        'upload_medium_size' => '500', // upload_medium_width
        'upload_storage_mode' => 'datefolder',
        'upload_thumb_height' => '160',
        'upload_thumb_width' => '160',
        'website_description' => 'A free image hosting service powered by Chevereto',
        'website_doctitle' => 'Chevereto image hosting',
        'website_name' => 'Chevereto',
    ],
    '3.0.1' => null,
    '3.0.2' => null,
    '3.0.3' => null,
    '3.0.4' => null,
    '3.0.5' => null,

    '3.1.0' => [
        'website_explore_page' => 1,
        //'theme_peafowl_home_uid' => '1'
    ],
    '3.1.1' => null,
    '3.1.2' => null,

    '3.2.0' => [
        'twitter_account' => 'chevereto',
        //'theme_peafowl_download_button' => 1,
        'enable_signups' => 1,
    ],
    '3.2.1' => null,
    '3.2.2' => [
        'favicon_image' => 'default/favicon.png',
        'logo_image' => 'default/logo.png',
        'logo_vector' => 'default/logo.svg',
        'theme_custom_css_code' => null,
        'theme_custom_js_code' => null,
    ],
    '3.2.3' => [
        'website_keywords' => 'image sharing, image hosting, chevereto',
        'logo_vector_enable' => 1,
        'watermark_enable' => 0,
        'watermark_image' => 'default/watermark.png',
        'watermark_position' => 'center center',
        'watermark_margin' => '10',
        'watermark_opacity' => '50',
        //'banner_home_before_cover' => NULL, // Deprecate
        'banner_home_after_cover' => null,
        'banner_home_after_listing' => null,
        'banner_image_image-viewer_foot' => null,
        'banner_image_image-viewer_top' => null,
        'banner_image_after_image-viewer' => null,
        'banner_image_after_header' => null,
        'banner_image_before_header' => null,
        'banner_image_footer' => null,
        'banner_content_tab-about_column' => null,
        'banner_content_before_comments' => null,
        'banner_explore_after_top' => null,
        'banner_user_after_top' => null,
        'banner_user_before_listing' => null,
        'banner_album_before_header' => null,
        'banner_album_after_header' => null,
    ],
    '3.2.4' => null,
    '3.2.5' => [
        'api_v1_key' => G\random_string(32),
    ],
    '3.2.6' => null,

    '3.3.0' => [
        'listing_pagination_mode' => 'classic',
        'banner_listing_before_pagination' => null,
        'banner_listing_after_pagination' => null,
        'show_nsfw_in_listings' => 0,
        'show_banners_in_nsfw' => 0,
        //'theme_peafowl_nsfw_upload_checkbox' => 1,
        //'privacy_mode' => 'public',
        //'website_mode' => 'public',
        'website_privacy_mode' => 'public',
        'website_content_privacy_mode' => 'default',
    ],
    '3.3.1' => null,
    '3.3.2' => [
        'show_nsfw_in_random_mode' => 0,
    ],

    '3.4.0' => [
        //'theme_peafowl_tone' => 'light',
        //'theme_peafowl_image_listing_size' => 'fixed'
    ],
    '3.4.1' => null,
    '3.4.2' => null,
    '3.4.3' => [
        'cdn' => 0,
        'cdn_url' => null,
    ],
    '3.4.4' => [
        'website_search' => 1,
        'website_random' => 1,
        'theme_logo_height' => null,
        'theme_show_social_share' => 1,
        // 'theme_show_embed_content' => 1, // deprecated @3.14.2
        'theme_show_embed_uploader' => 1,
    ],
    '3.4.5' => [
        'user_routing' => 1,
        'require_user_email_confirmation' => 1,
        'require_user_email_social_signup' => 1,
    ],
    '3.4.6' => null,
    '3.5.0' => [
        //'active_storage' => NULL // deprecated
    ],
    '3.5.1' => null,
    '3.5.2' => null,
    '3.5.3' => null,
    '3.5.4' => null,
    '3.5.5' => [
        'last_used_storage' => null,
    ],
    '3.5.6' => null,
    '3.5.7' => [
        'vk' => 0,
        'vk_client_id' => null,
        'vk_client_secret' => null,
    ],
    '3.5.8' => null,
    '3.5.9' => null,
    '3.5.10' => null,
    '3.5.11' => null,
    '3.5.12' => [
        'theme_download_button' => 1,
        'theme_nsfw_upload_checkbox' => 1,
        'theme_tone' => 'light',
        'theme_image_listing_sizing' => 'fixed',
    ],
    '3.5.13' => null,
    '3.5.14' => null,
    '3.5.15' => [
        'listing_columns_phone' => '2',
        'listing_columns_phablet' => '3',
        'listing_columns_tablet' => '4',
        'listing_columns_laptop' => '5',
        'listing_columns_desktop' => '6',
        //'logged_user_logo_link'		=> 'homepage', // Removed in 3.7.0
        'homepage_style' => 'landing',
        'homepage_cover_image' => 'default/home_cover.jpg',
        'homepage_uids' => '1',
        'homepage_endless_mode' => 0,
    ],
    '3.5.16' => null,
    '3.5.17' => null,
    '3.5.18' => null,
    '3.5.19' => [
        'user_image_avatar_max_filesize_mb' => '1',
        'user_image_background_max_filesize_mb' => '2',
    ],
    '3.5.20' => [
        'theme_image_right_click' => 0,
    ],
    '3.5.21' => null,
    '3.6.0' => [
        // 'minify_enable' => 0, // Removed in 3.19
        'theme_show_exif_data' => 1,
        // 'theme_top_bar_color' => 'white', // Removed in 3.15.0
        'theme_main_color' => null,
        'theme_top_bar_button_color' => 'blue',
        'logo_image_homepage' => 'default/logo_homepage.png',
        'logo_vector_homepage' => 'default/logo_homepage.svg',
        'homepage_cta_color' => 'black',
        'homepage_cta_outline' => 0,
        'watermark_enable_guest' => 1,
        'watermark_enable_user' => 1,
        'watermark_enable_admin' => 1,
    ],
    '3.6.1' => [
        'homepage_title_html' => null,
        'homepage_paragraph_html' => null,
        'homepage_cta_html' => null,
        'homepage_cta_fn' => null,
        'homepage_cta_fn_extra' => null,
        'language_chooser_enable' => 1,
        'languages_disable' => null,
    ],
    '3.6.2' => [
        'website_mode' => 'community',
        'website_mode_personal_routing' => null, //'single_user_mode_routing'
        'website_mode_personal_uid' => null, //'single_user_mode_id'
    ],
    '3.6.3' => null,
    '3.6.4' => [
        'enable_cookie_law' => 0,
        'theme_nsfw_blur' => 0,
    ],
    '3.6.5' => [
        'watermark_target_min_width' => '100',
        'watermark_target_min_height' => '100',
        'watermark_percentage' => '4',
        'watermark_enable_file_gif' => 0,
    ],
    '3.6.6' => [
        'id_padding' => '0', // 0-> Update | 5000-> new install
    ],
    '3.6.7' => null,
    '3.6.8' => [
        'upload_image_exif' => 1,
        'upload_image_exif_user_setting' => 1,
        'enable_expirable_uploads' => 1,
        //'banner_home_before_cover_nsfw'			=> NULL, // Deprecate
        'banner_home_after_cover_nsfw' => null,
        'banner_home_after_listing_nsfw' => null,
        'banner_image_image-viewer_foot_nsfw' => null,
        'banner_image_image-viewer_top_nsfw' => null,
        'banner_image_after_image-viewer_nsfw' => null,
        'banner_image_after_header_nsfw' => null,
        'banner_image_before_header_nsfw' => null,
        'banner_image_footer_nsfw' => null,
        'banner_content_tab-about_column_nsfw' => null,
        'banner_content_before_comments_nsfw' => null,
        'banner_explore_after_top_nsfw' => null,
        'banner_user_after_top_nsfw' => null,
        'banner_user_before_listing_nsfw' => null,
        'banner_album_before_header_nsfw' => null,
        'banner_album_after_header_nsfw' => null,
        'banner_listing_before_pagination_nsfw' => null,
        'banner_listing_after_pagination_nsfw' => null,
    ],
    '3.6.9' => null,
    '3.7.0' => null,
    '3.7.1' => null,
    '3.7.2' => [
        'upload_medium_size' => '500',
        'upload_medium_fixed_dimension' => 'width',
    ],
    '3.7.3' => [
        'enable_followers' => 1,
        'enable_likes' => 1,
        'enable_consent_screen' => 0,
        'user_minimum_age' => null,
        'consent_screen_cover_image' => 'default/consent-screen_cover.jpg',
    ],
    '3.7.4' => null,
    '3.7.5' => [
        'enable_redirect_single_upload' => 0,
        'route_image' => 'image',
        'route_album' => 'album',
    ],
    '3.8.0' => [
        'enable_duplicate_uploads' => 0,
        'update_check_datetimegmt' => null,
        'update_check_notified_release' => G_APP_VERSION,
        'update_check_display_notification' => 1,
    ],
    '3.8.1' => null,
    '3.8.2' => null,
    '3.8.3' => null, // Chevereto Free hook
    '3.8.4' => [
        'banner_home_before_title' => null,
        'banner_home_after_cta' => null,
        'banner_home_before_title_nsfw' => null,
        'banner_home_after_cta_nsfw' => null,
        // 'upload_enabled_image_formats' => 'jpg,png,bmp,gif',
        'upload_threads' => '2',
        'enable_automatic_updates_check' => 1,
        'comments_api' => 'js',
        'disqus_shortname' => null,
        'disqus_public_key' => null,
        'disqus_secret_key' => null,
    ],
    '3.8.5' => null,
    '3.8.6' => null,
    '3.8.7' => null,
    '3.8.8' => null,
    '3.8.9' => [
        'image_load_max_filesize_mb' => '3',
    ],
    '3.8.10' => null,
    '3.8.11' => null,
    '3.8.12' => [
        'upload_max_image_width' => '0',
        'upload_max_image_height' => '0',
    ],
    '3.8.13' => null,
    '3.9.0' => [
        'auto_delete_guest_uploads' => null,
    ],
    '3.9.1' => null,
    '3.9.2' => null,
    '3.9.3' => null,
    '3.9.4' => null,
    '3.9.5' => null,
    '3.10.0' => null,
    '3.10.1' => null,
    '3.10.2' => [
        'enable_user_content_delete' => 1,
    ],
    '3.10.3' => [
        'enable_plugin_route' => 1,
        'sdk_pup_url' => null,
    ],
    '3.10.4' => null,
    '3.10.5' => null,
    '3.10.6' => [
        'website_explore_page_guest' => 1,
        'explore_albums_min_image_count' => '1',
        'upload_max_filesize_mb_guest' => '0.5',
        'notify_user_signups' => 0,
        'listing_viewer' => 1,
    ],
    '3.10.7' => null,
    '3.10.8' => null,
    '3.10.9' => null,
    '3.10.10' => null,
    '3.10.11' => null,
    '3.10.12' => null,
    '3.10.13' => null,
    '3.10.14' => null,
    '3.10.15' => null,
    '3.10.16' => null,
    '3.10.17' => null,
    '3.10.18' => null,
    '3.11.0' => null,
    '3.11.1' => [
        'seo_image_urls' => 1,
        'seo_album_urls' => 1,
    ],
    '3.12.0' => [
        'lang_subdomain_wildcard' => 0,
        'user_subdomain_wildcard' => 0,
        'website_https' => 'auto',
    ],
    '3.12.1' => null,
    '3.12.2' => null,
    '3.12.3' => null,
    '3.12.4' => [
        'upload_gui' => 'page',
        'recaptcha_version' => '2',
    ],
    '3.12.5' => null,
    '3.12.6' => null,
    '3.12.7' => null,
    '3.12.8' => [
        'force_recaptcha_contact_page' => 1,
    ],
    '3.12.9' => null,
    '3.12.10' => null,
    '3.13.0' => [
        'dump_update_query' => 0,
    ],
    '3.13.1' => null,
    '3.13.2' => null,
    '3.13.3' => null,
    '3.13.4' => [
        'enable_powered_by' => 1,
        'akismet' => 0,
        'akismet_api_key' => null,
        'stopforumspam' => 0,
    ],
    '3.13.5' => null,
    '3.14.0' => [
        'upload_enabled_image_formats' => 'jpg,png,bmp,gif,webp',
    ],
    '3.14.1' => null,
    '3.15.0' => [
        'hostname' => null,
        'theme_show_embed_content_for' => 'all', // none,users,all
    ],
    '3.15.1' => null,
    '3.15.2' => null,
    '3.16.0' => [
        'moderatecontent' => 0,
        'moderatecontent_key' => '',
        'moderatecontent_block_rating' => 'a', // ,a,t
        'moderatecontent_flag_nsfw' => 'a', // ,a,t
        'moderatecontent_auto_approve' => 0,
        'moderate_uploads' => '', // ,all,guest,
        'image_lock_nsfw_editing' => 0,
    ],
    '3.16.1' => null,
    '3.16.2' => null,
    '3.17.0' => null,
    '3.17.1' => null,
    '3.17.2' => null,
    '3.18.0' => null,
    '3.18.1' => null,
    '3.18.2' => null,
    '3.18.3' => null,
    '3.20.0' => null,
    '3.20.1' => null,
    '3.20.2' => null,
    '3.20.3' => null,
    '3.20.4' => null,
    '3.20.5' => null,
    '3.20.6' => null,
    '3.20.7' => null,
    '3.20.8' => [
        'enable_uploads_url' => 0,
    ],
    '3.20.9' => null,
    '3.20.10' => null,
    '3.20.11' => null,
    '3.20.12' => null,
    '3.20.13' => [
        'chevereto_news' => 'a:0:{}',
        'cron_last_ran' => '0000-00-00 00:00:00',
    ],
    '3.20.14' => null,
    '3.20.15' => null,
    '3.20.16' => null,
    '3.20.17' => null,
    '3.20.18' => null,
    '3.20.19' => null,
    '3.20.20' => null, // EOL
];
$cheveretoFreeMap = [
    '1.0.0' => '3.8.3',
    '1.0.1' => '3.8.3',
    '1.0.2' => '3.8.3',
    '1.0.3' => '3.8.4',
    '1.0.4' => '3.8.4',
    '1.0.5' => '3.8.8',
    '1.0.6' => '3.8.10',
    '1.0.7' => '3.8.11',
    '1.0.8' => '3.8.13',
    '1.0.9' => '3.9.5',
    '1.0.10' => '3.10.5',
    '1.0.11' => '3.10.5',
    '1.0.12' => '3.10.5',
    '1.0.13' => '3.10.5',
    '1.1.0' => '3.10.18',
    '1.1.1' => '3.10.18',
    '1.1.2' => '3.10.18',
    '1.1.3' => '3.10.18',
    '1.1.4' => '3.10.18',
    '1.2.0' => '3.15.2',
    '1.2.1' => '3.15.2',
    '1.2.2' => '3.15.2',
    '1.2.3' => '3.15.2',
    '1.3.0' => '3.16.2',
];
$settings_delete = [
    'banner_home_before_cover',
    'banner_home_before_cover_nsfw',
    'cloudflare', // 3.15.0
    'theme_show_embed_content', // 3.15.0
    'theme_top_bar_color', // 3.15.0
    'minify_enable' // 3.19.0
];
$settings_rename = [
    // 3.5.12
    'theme_peafowl_home_uid' => 'homepage_uids',
    'theme_peafowl_download_button' => 'theme_download_button',
    'theme_peafowl_nsfw_upload_checkbox' => 'theme_nsfw_upload_checkbox',
    'theme_peafowl_tone' => 'theme_tone',
    'theme_peafowl_image_listing_size' => 'theme_image_listing_sizing',
    // 3.5.15
    'theme_home_uids' => 'homepage_uids',
    'theme_home_endless_mode' => 'homepage_endless_mode',
    // 3.6.2
    'single_user_mode_routing' => 'website_mode_personal_routing',
    'single_user_mode_id' => 'website_mode_personal_uid',
    // 3.7.2
    'upload_medium_width' => 'upload_medium_size',
];
$settings_switch = [
    // '3.6.2' => [
    //     'website_mode' => 'website_privacy_mode',
    // ],
];
$chv_initial_settings = [];
foreach ($settings_updates as $k => $v) {
    if (is_null($v)) {
        continue;
    }
    $chv_initial_settings += $v;
}
try {
    $is_2X = DB::get('info', ['key' => 'version']) ? true : false;
} catch (Exception $e) {
    $is_2X = false;
}
$stats_query_legacy = 'TRUNCATE TABLE `%table_prefix%stats`;

INSERT INTO `%table_prefix%stats` (stat_id, stat_date_gmt, stat_type) VALUES ("1", NULL, "total") ON DUPLICATE KEY UPDATE stat_type=stat_type;

UPDATE `%table_prefix%stats` SET
stat_images = (SELECT IFNULL(COUNT(*),0) FROM `%table_prefix%images`),
stat_albums = (SELECT IFNULL(COUNT(*),0) FROM `%table_prefix%albums`),
stat_users = (SELECT IFNULL(COUNT(*),0) FROM `%table_prefix%users`),
stat_image_views = (SELECT IFNULL(SUM(image_views),0) FROM `%table_prefix%images`),
stat_disk_used = (SELECT IFNULL(SUM(image_size) + SUM(image_thumb_size) + SUM(image_medium_size),0) FROM `%table_prefix%images`)
WHERE stat_type = "total";

INSERT INTO `%table_prefix%stats` (stat_type, stat_date_gmt, stat_images, stat_image_views, stat_disk_used)
SELECT sb.stat_type, sb.stat_date_gmt, sb.stat_images, sb.stat_image_views, sb.stat_disk_used
FROM (SELECT "date" AS stat_type, DATE(image_date_gmt) AS stat_date_gmt, COUNT(*) AS stat_images, SUM(image_views) AS stat_image_views, SUM(image_size + image_thumb_size + image_medium_size) AS stat_disk_used FROM `%table_prefix%images` GROUP BY DATE(image_date_gmt)) AS sb
ON DUPLICATE KEY UPDATE stat_images = sb.stat_images;

INSERT INTO `%table_prefix%stats` (stat_type, stat_date_gmt, stat_users)
SELECT sb.stat_type, sb.stat_date_gmt, sb.stat_users
FROM (SELECT "date" AS stat_type, DATE(user_date_gmt) AS stat_date_gmt, COUNT(*) AS stat_users FROM `%table_prefix%users` GROUP BY DATE(user_date_gmt)) AS sb
ON DUPLICATE KEY UPDATE stat_users = sb.stat_users;

INSERT INTO `%table_prefix%stats` (stat_type, stat_date_gmt, stat_albums)
SELECT sb.stat_type, sb.stat_date_gmt, sb.stat_albums
FROM (SELECT "date" AS stat_type, DATE(album_date_gmt) AS stat_date_gmt, COUNT(*) AS stat_albums FROM `%table_prefix%albums` GROUP BY DATE(album_date_gmt)) AS sb
ON DUPLICATE KEY UPDATE stat_albums = sb.stat_albums;

UPDATE `%table_prefix%users` SET user_content_views = COALESCE((SELECT SUM(image_views) FROM `%table_prefix%images` WHERE image_user_id = user_id GROUP BY user_id), "0");';
$isMariaDB = false;
if (G\settings_has_db_info()) {
    $db = DB::getInstance();
    $sqlServerVersion = $db->getAttr(\PDO::ATTR_SERVER_VERSION);
    $innoDBrequiresVersion = '5.6'; // default:mysql
    if (stripos($sqlServerVersion, 'MariaDB') !== false) {
        $isMariaDB = true;
        $innoDBrequiresVersion = '10.0.5';
        $sqlServerVersion = explode('-', $sqlServerVersion)[1];
    }
    $doing = 'ready';
}
$fulltext_engine = 'InnoDB';
$installed_version = getSetting('chevereto_version_installed');
$maintenance = getSetting('maintenance');
if (isset($installed_version, $cheveretoFreeMap[$installed_version])) {
    $installed_version = $cheveretoFreeMap[$installed_version];
}
if (isset($installed_version)) {
    $doing = 'already';
}
$opts = getopt('C:') ?? null;
$safe_post = G\Handler::getVar('safe_post');
if(!empty($_POST)) {
    $params = $_POST;
    if(isset($_POST['debug'])) {
        $params['debug'] = true;
    }
} else if(!empty($opts)) {
    if($doing == 'already') {
        $opts = getopt('C:d::');
    } else {
        $opts = getopt('C:u:e:x:d::');
        $params = [];
        $missing = [];
        foreach([
            'username' => 'u',
            'email' => 'e',
            'password' => 'x',
        ] as $k => $o) {
            $params[$k] = $opts[$o] ?? null;
            if(!isset($params[$k])) {
                $missing[] = $k . " -$o";
            }
        }
        if($missing !== []) {
            G\logger('Missing ' . implode(', ', $missing) . "\n");
            die(255);
        }
    }
    $params['dump'] = isset($opts['d']);
}
if($isMariaDB) {
    $explodeMariaVersion = explode('.', $sqlServerVersion);
    $mariaVersion = $explodeMariaVersion[0] . '.' . $explodeMariaVersion[1];
    switch($mariaVersion) {
        case '10.0':
        case '10.1':
        case '10.2':
        case '10.3':
            $mysql_version = '5'; // 5.7
            break;
        case '10.4':
        case '10.5':
        case '10.6':
            default:
            $mysql_version = '8'; // 8.0
            break;
    }
} else {
    $mysql_version = version_compare($sqlServerVersion, '8', '>=')
    ? '8'
    : '5';
}
$dbSchemaVer = sprintf('mysql-%s', $mysql_version);
$paramsCheck = $params ?? [];
unset($paramsCheck['dump']);
if (isset($installed_version) && empty($paramsCheck)) {
    $db_settings_keys = [];
    try {
        $db_settings = DB::get('settings', 'all');
        foreach ($db_settings as $k => $v) {
            $db_settings_keys[] = $v['setting_name'];
        }
    } catch (Exception $e) {
    }
    if ((!empty($db_settings_keys) && count($chv_initial_settings) !== count($db_settings_keys)) || (!is_null($installed_version) and version_compare(G_APP_VERSION, $installed_version, '>'))) {

        if (!array_key_exists(G_APP_VERSION, $settings_updates)) {
            die('Fatal error: app/install is outdated. You need to re-upload app/install folder with the one from Chevereto ' . G_APP_VERSION);
        }
        $schema = [];
        $raw_schema = DB::queryFetchAll('SELECT * FROM information_schema.COLUMNS WHERE TABLE_SCHEMA="' .  G\get_app_setting('db_name') . '" AND TABLE_NAME LIKE "' . G\get_app_setting('db_table_prefix') . '%";');
        foreach ($raw_schema as $k => $v) {
            $TABLE = preg_replace('#' . G\get_app_setting('db_table_prefix') . '#i', '', strtolower($v['TABLE_NAME']), 1);
            $COLUMN = $v['COLUMN_NAME'];
            if (!array_key_exists($TABLE, $schema)) {
                $schema[$TABLE] = [];
            }
            $schema[$TABLE][$COLUMN] = $v;
        }
        $triggers_to_remove = [
            'album_insert',
            'album_delete',
            'follow_insert',
            'follow_delete',
            'image_insert',
            'image_update',
            'image_delete',
            'like_insert',
            'like_delete',
            'notification_insert',
            'notification_update',
            'notification_delete',
            'user_insert',
            'user_delete',
        ];
        $db_triggers = DB::queryFetchAll('SELECT TRIGGER_NAME FROM INFORMATION_SCHEMA.TRIGGERS');
        if ($db_triggers) {
            $drop_trigger_sql = null;
            foreach ($db_triggers as $k => $v) {
                $trigger = $v['TRIGGER_NAME'];
                if (in_array($v['TRIGGER_NAME'], $triggers_to_remove)) {
                    $drop_trigger = 'DROP TRIGGER IF EXISTS `' . $v['TRIGGER_NAME'] . '`;' . "\n";
                    $drop_trigger_sql .= $drop_trigger;
                }
            }
            if (!is_null($drop_trigger_sql)) {
                $drop_trigger_sql = rtrim($drop_trigger_sql, "\n");
                $remove_triggers = false;
                $remove_triggers = DB::queryExec($drop_trigger_sql);
                if (!$remove_triggers) {
                    Render\chevereto_die(null, 'To proceed you will need to run these queries in your database server: <br><br> <textarea class="resize-vertical highlight r5">' . $drop_trigger_sql . '</textarea>', "Can't remove table triggers");
                }
            }
        }
        $DB_indexes = [];
        $raw_indexes = DB::queryFetchAll('SELECT DISTINCT TABLE_NAME, INDEX_NAME, INDEX_TYPE FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = "' . G\get_app_setting('db_name') . '"');
        foreach ($raw_indexes as $k => $v) {
            $TABLE = preg_replace('#' . G\get_app_setting('db_table_prefix') . '#i', '', strtolower($v['TABLE_NAME']), 1);
            $INDEX_NAME = $v['INDEX_NAME'];
            if (!array_key_exists($TABLE, $DB_indexes)) {
                $DB_indexes[$TABLE] = [];
            }
            $DB_indexes[$TABLE][$INDEX_NAME] = $v;
        }
        $CHV_indexes = [];
        foreach (new \DirectoryIterator(CHV_APP_PATH_INSTALL . 'schemas/' . $dbSchemaVer) as $fileInfo) {
            if ($fileInfo->isDot() or $fileInfo->isDir() or !array_key_exists($fileInfo->getBasename('.sql'), $schema)) {
                continue;
            }
            $crate_table = file_get_contents(realpath($fileInfo->getPathname()));
            if (preg_match_all('/(?:(?:FULLTEXT|UNIQUE)\s*)?KEY `(\w+)` \(.*\)/', $crate_table, $matches)) {
                $CHV_indexes[$fileInfo->getBasename('.sql')] = array_combine($matches[1], $matches[0]);
            }
        }
        $engines = [];
        $raw_engines = DB::queryFetchAll('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = "' . G\get_app_setting('db_name') . '" AND TABLE_NAME LIKE "' . G\get_app_setting('db_table_prefix') . '%";');
        $update_table_storage = [];
        foreach ($raw_engines as $k => $v) {
            $TABLE = preg_replace('#' . G\get_app_setting('db_table_prefix') . '#i', '', strtolower($v['TABLE_NAME']), 1);
            $engines[$TABLE] = $v['ENGINE'];
            if($v['ENGINE'] !== 'InnoDB') {
                $update_table_storage[] = 'ALTER TABLE '.$v['TABLE_NAME'].' ENGINE = InnoDB;';
            }
        }
        if($update_table_storage !== []) {
            Render\chevereto_die(null, '<p>Database table storage engine needs to be updated to InnoDB. Run the following command(s) in your MySQL console:</p><textarea class="resize-vertical highlight r5">' . implode("\n", $update_table_storage) . '</textarea><p>Review <a href="https://dev.mysql.com/doc/refman/8.0/en/converting-tables-to-innodb.html" target="_blank">Converting Tables from MyISAM to InnoDB</a>.</p>', "Convert MyISAM tables to InnoDB");
        }
        $isUtf8mb4 = version_compare($installed_version, '3.12.10', '>');
        $update_table = [
            '3.1.0' => [
                'logins' => [
                    'login_resource_id' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                    'login_secret' => [
                        'op' => 'MODIFY',
                        'type' => $isUtf8mb4 ? 'mediumtext' : 'text', //3.13.0
                        'prop' => "DEFAULT NULL COMMENT 'The secret part'",
                    ],
                ],
                'users' => [
                    'user_name' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'settings' => [
                    'setting_value' => [
                        'op' => 'MODIFY',
                        'type' => $isUtf8mb4 ? 'mediumtext' : 'text', //3.13.0
                        'prop' => null,
                    ],
                    'setting_default' => [
                        'op' => 'MODIFY',
                        'type' => $isUtf8mb4 ? 'mediumtext' : 'text', //3.13.0
                        'prop' => null,
                    ],
                ],
            ],
            '3.3.0' => [
                'albums' => [
                    'album_privacy' => [
                        'op' => 'MODIFY',
                        'type' => "enum('public','password','private','private_but_link','custom')",
                        'prop' => "DEFAULT 'public'",
                    ],
                ],
            ],
            '3.4.0' => [
                'images' => [
                    'image_category_id' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'albums' => [
                    'album_description' => [
                        'op' => 'ADD',
                        'type' => 'text',
                        'prop' => null,
                    ],
                ],
                'categories' => [], // ADD TABLE
            ],
            '3.5.0' => [
                'images' => [
                    'image_original_exifdata' => [
                        'op' => 'MODIFY',
                        'type' => 'longtext',
                        'prop' => null,
                    ],
                    'image_storage' => [
                        'op' => 'CHANGE',
                        'to' => 'image_storage_mode',
                        'type' => "enum('datefolder','direct','old')",
                        'prop' => "NOT NULL DEFAULT 'datefolder'",
                    ],
                    'image_chain' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(128)',
                        'prop' => 'NOT NULL',
                        'tail' => 'UPDATE `%table_prefix%images` set `image_chain` = 7;',
                    ],
                ],
                'storages' => [], // ADD TABLE
                'storage_apis' => [], // ADD TABLE
            ],
            '3.5.3' => [
                'storages' => [
                    'storage_region' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
            ],
            '3.5.5' => [
                'queues' => [], // ADD TABLE
                'storages' => [
                    'storage_server' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                    'storage_capacity' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => 'DEFAULT NULL',
                    ],
                    'storage_space_used' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "DEFAULT '0'",
                        'tail' => 'UPDATE `%table_prefix%storages` SET storage_space_used = (SELECT SUM(image_size) as count from `%table_prefix%images` WHERE image_storage_id = `%table_prefix%storages`.storage_id);',
                    ],
                ],
                'images' => [
                    'image_thumb_size' => [
                        'op' => 'ADD',
                        'type' => 'int(11)',
                        'prop' => 'NOT NULL',
                    ],
                    'image_medium_size' => [
                        'op' => 'ADD',
                        'type' => 'int(11)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
            ],
            '3.5.7' => [
                // 'logins' => [
                //     'login_type' => [
                //         'op' => 'MODIFY',
                //         'type' => "enum('password','session','cookie','facebook','twitter','google','vk')",
                //         'prop' => 'NOT NULL',
                //     ],
                // ],
                'queues' => [
                    'queue_type' => [
                        'op' => 'MODIFY',
                        'type' => "enum('storage-delete')",
                        'prop' => 'NOT NULL',
                        'tail' => "UPDATE `%table_prefix%queues` SET queue_type='storage-delete';",
                    ],
                ],
                'storages' => [
                    'storage_server' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
            ],
            '3.5.8' => [
                'images' => [
                    'op' => 'ALTER',
                    'prop' => 'ENGINE=%table_engine%; CREATE FULLTEXT INDEX `searchindex` ON `%table_prefix%images`(image_name, image_description, image_original_filename)',
                ],
                'albums' => [
                    'op' => 'ALTER',
                    'prop' => 'ENGINE=%table_engine%; CREATE FULLTEXT INDEX `searchindex` ON `%table_prefix%albums`(album_name, album_description)',
                ],
                'users' => [
                    'op' => 'ALTER',
                    'prop' => 'ENGINE=%table_engine%; CREATE FULLTEXT INDEX `searchindex` ON `%table_prefix%users`(user_name, user_username)',
                ],
            ],
            '3.5.9' => [
                'images' => [
                    'image_title' => [
                        'op' => 'ADD',
                        'type' => 'varchar(100)', // 3.6.5
                        'prop' => 'DEFAULT NULL',
                        'tail' => 'DROP INDEX searchindex ON `%table_prefix%images`;
                        UPDATE `%table_prefix%images` SET `image_title` = SUBSTRING(`image_description`, 1, 100);
                        UPDATE `%table_prefix%images` SET image_description = NULL WHERE image_title = image_description;
                        CREATE FULLTEXT INDEX searchindex ON `%table_prefix%images`(image_name, image_title, image_description, image_original_filename);',
                    ],
                ],
                'albums' => [
                    'album_name' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(100)', // 3.6.5
                        'prop' => 'NOT NULL',
                    ],
                ],
            ],
            '3.5.11' => [
                'queues' => [
                    'queue_attempts' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT 0',
                    ],
                    'queue_status' => [
                        'op' => 'ADD',
                        'type' => "enum('pending','failed')",
                        'prop' => "NOT NULL DEFAULT 'pending'",
                    ],
                ],
            ],
            '3.5.12' => [
                'query' => 'UPDATE `%table_prefix%settings` SET `setting_value` = 0, `setting_default` = 0, `setting_typeset` = "bool" WHERE `setting_name` = "maintenance";',
            ],
            '3.5.14' => [
                'ip_bans' => [], // ADD TABLE
            ],
            '3.6.0' => [
                'users' => [
                    'user_newsletter_subscribe' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '1'",
                    ],
                    'user_show_nsfw_listings' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                    'user_bio' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
            ],
            '3.6.2' => [
                'storages' => [
                    'storage_key' => [
                        'op' => 'MODIFY',
                        'type' => $isUtf8mb4 ? 'mediumtext' : 'text', //3.13.0
                        'prop' => null,
                    ],
                    'storage_secret' => [
                        'op' => 'MODIFY',
                        'type' => $isUtf8mb4 ? 'mediumtext' : 'text', //3.13.0
                        'prop' => null,
                    ],
                ],
            ],
            '3.6.3' => [
                'storages' => [
                    'storage_account_id' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                    'storage_account_name' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'query' => "INSERT IGNORE INTO `%table_prefix%storage_apis` VALUES ('7', 'OpenStack', 'openstack');",
            ],
            '3.6.4' => [
                'query' => 'UPDATE `%table_prefix%settings` SET `setting_value`="mail" WHERE setting_name = "email_mode" AND `setting_value`="phpmail";
                UPDATE `%table_prefix%settings` SET `setting_default`="mail" WHERE setting_name = "email_mode";',
            ],
            '3.6.5' => [
                'images' => [
                    'image_title' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(100)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'albums' => [
                    'album_name' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(100)',
                        'prop' => 'NOT NULL',
                    ],
                ],
            ],
            '3.6.6' => [
                //'id_reservations' => [], // ADD TABLE // NOPE!
            ],
            '3.6.7' => [
                'pages' => [], // ADD TABLE
            ],
            '3.6.8' => [
                'users' => [
                    'user_image_keep_exif' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '1'",
                    ],
                    'user_image_expiration' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'images' => [
                    'image_expiration_date_gmt' => [
                        'op' => 'ADD',
                        'type' => 'datetime',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
            ],
            '3.7.0' => [
                'deletions' => [], // ADD TABLE
                'follows' => [], // ADD TABLE
                'likes' => [], // ADD TABLE
                'notifications' => [], // ADD TABLE
                'stats' => [], // ADD TABLE
                'albums' => [
                    'album_creation_ip' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'NOT NULL',
                    ],
                    'album_likes' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'images' => [
                    'image_likes' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'users' => [
                    'user_registration_ip' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'NOT NULL',
                    ],
                    'user_likes' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0' COMMENT 'Likes made to content owned by this user'",
                    ],
                    'user_liked' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0' COMMENT 'Likes made by this user'",
                    ],
                    'user_following' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                    'user_followers' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                    'user_content_views' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                    'user_notifications_unread' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'query' => $stats_query_legacy,
            ],
            '3.7.5' => [
                'users' => [
                    'user_is_private' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'storages' => [
                    'storage_service' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
            ],
            '3.8.0' => [
                'images' => [
                    'image_is_animated' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'albums' => [
                    'album_password' => [
                        'op' => 'ADD',
                        'type' => 'text',
                        'prop' => null,
                    ],
                ],
                'requests' => [
                    'request_type' => [
                        'op' => 'MODIFY',
                        'type' => "enum('upload','signup','account-edit','account-password-forgot','account-password-reset','account-resend-activation','account-email-needed','account-change-email','account-activate','login','content-password')",
                        'prop' => 'NOT NULL',
                    ],
                    'request_content_id' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
            ],
            '3.9.0' => [
                'albums' => [
                    'album_views' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'likes' => [
                    'like_content_type' => [
                        'op' => 'MODIFY',
                        'type' => "enum('image','album')",
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'notifications' => [
                    'notification_content_type' => [
                        'op' => 'MODIFY',
                        'type' => "enum('user','image','album')",
                        'prop' => 'NOT NULL',
                    ],
                ],
                'stats' => [
                    'stat_album_views' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                    'stat_album_likes' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                    'stat_likes' => [
                        'op' => 'CHANGE',
                        'to' => 'stat_image_likes',
                        'type' => 'bigint(32)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
            ],
            '3.10.13' => [
                'images' => [
                    'image_source_md5' => [
                        'op' => 'ADD',
                        'type' => 'varchar(32)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
            ],
            '3.11.0' => [
                'query' => "INSERT INTO `%table_prefix%storage_apis` VALUES ('8', 'Local', 'local') ON DUPLICATE KEY UPDATE storage_api_type = 'local';",
            ],
            '3.12.0' => [
                'imports' => [], // ADD TABLE
                'importing' => [], // ADD TABLE
                'images' => [
                    'image_storage_mode' => [
                        'op' => 'MODIFY',
                        'type' => "enum('datefolder','direct','old','path')",
                        'prop' => "NOT NULL DEFAULT 'datefolder'",
                    ],
                    'image_path' => [
                        'op' => 'ADD',
                        'type' => 'varchar(4096)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'albums' => [
                    'album_user_id' => [
                        'op' => 'MODIFY',
                        'type' => 'bigint(32)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'users' => [
                    'user_is_manager' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'query' => 'UPDATE `%table_prefix%pages` SET page_icon = "fas fa-landmark" WHERE page_url_key = "tos" AND page_icon IS NULL OR page_icon = "";
UPDATE `%table_prefix%pages` SET page_icon = "fas fa-lock" WHERE page_url_key = "privacy" AND page_icon IS NULL OR page_icon = "";
UPDATE `%table_prefix%pages` SET page_icon = "fas fa-at" WHERE page_url_key = "contact" AND page_icon IS NULL OR page_icon = "";
INSERT INTO `%table_prefix%storage_apis` VALUES ("3", "Microsoft Azure", "azure") ON DUPLICATE KEY UPDATE storage_api_name = "Microsoft Azure";
INSERT INTO `%table_prefix%storage_apis` VALUES ("9", "S3 compatible", "s3compatible") ON DUPLICATE KEY UPDATE storage_api_type = "s3compatible";
INSERT INTO `%table_prefix%storage_apis` VALUES ("10", "Alibaba Cloud OSS", "oss") ON DUPLICATE KEY UPDATE storage_api_type = "oss";
INSERT INTO `%table_prefix%storage_apis` VALUES ("11", "Backblaze B2", "b2") ON DUPLICATE KEY UPDATE storage_api_type = "b2";',
            ],
            '3.12.4' => [
                'pages' => [
                    'page_internal' => [
                        'op' => 'ADD',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'query' => 'UPDATE `%table_prefix%pages` SET page_internal = "tos" WHERE page_url_key = "tos";
UPDATE `%table_prefix%pages` SET page_internal = "privacy" WHERE page_url_key = "privacy";
UPDATE `%table_prefix%pages` SET page_internal = "contact" WHERE page_url_key = "contact";',
            ],
            // 191 because old MyIsam tables. InnoDB 255
            '3.13.0' => [
                'settings' => [
                    'setting_name' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'CHARACTER SET utf8 COLLATE utf8_bin NOT NULL',
                    ],
                ],
                'deletions' => [
                    'deleted_content_ip' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'NOT NULL',
                    ],
                ],
                'ip_bans' => [
                    'ip_ban_ip' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'NOT NULL',
                    ],
                    'ip_ban_message' => [
                        'op' => 'MODIFY',
                        'type' => 'text',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'pages' => [
                    'page_internal' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'users' => [
                    'user_username' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'NOT NULL',
                    ],
                    'user_email' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                    'user_image_expiration' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                    'user_registration_ip' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'NOT NULL',
                    ],
                ],
                /*
                    * Note: The change from utf8 to utf8mb4 causes that TEXT changes to MEDIUMTEXT to ensure that the
                    * converted data will fit in the resulting column. Same goes for MEDIUMTEXT -> LONGTEXT
                    *
                    * https://dev.mysql.com/doc/refman/5.7/en/alter-table.html
                    * Look for "For a column that has a data type of VARCHAR or one"
                    */
                'query' => ($DB_indexes['albums']['album_creation_ip'] ? 'DROP INDEX `album_creation_ip` ON `%table_prefix%albums`;
ALTER TABLE `%table_prefix%albums` ADD INDEX `album_creation_ip` (`album_creation_ip`(255));
' : null) . ($DB_indexes['images']['image_name'] ? 'DROP INDEX `image_name` ON `%table_prefix%images`;
ALTER TABLE `%table_prefix%images` ADD INDEX `image_name` (`image_name`(255));
' : null) . ($DB_indexes['images']['image_extension'] ? 'DROP INDEX `image_extension` ON `%table_prefix%images`;
ALTER TABLE `%table_prefix%images` ADD INDEX `image_extension` (`image_extension`(255));
' : null) . ($DB_indexes['images']['image_uploader_ip'] ? 'DROP INDEX `image_uploader_ip` ON `%table_prefix%images`;
ALTER TABLE `%table_prefix%images` ADD INDEX `image_uploader_ip` (`image_uploader_ip`(255));
' : null) . ($DB_indexes['images']['image_uploader_ip'] ? 'DROP INDEX `image_path` ON `%table_prefix%images`;
ALTER TABLE `%table_prefix%images` ADD INDEX `image_path` (`image_path`(255));
' : null) . (!$isUtf8mb4 ? 'ALTER TABLE `%table_prefix%images` ENGINE=%table_engine%;
ALTER TABLE `%table_prefix%albums` ENGINE=%table_engine%;
ALTER TABLE `%table_prefix%users` ENGINE=%table_engine%;
ALTER TABLE `%table_prefix%albums` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%categories` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%deletions` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%images` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%ip_bans` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%pages` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%settings` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%storages` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
ALTER TABLE `%table_prefix%users` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;' : null),
            ],
            '3.13.4' => [
                'query' => 'ALTER TABLE `%table_prefix%logins` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;',
            ],
            '3.14.0' => [
                'deletions' => [
                    'deleted_content_original_filename' => [
                        'op' => 'MODIFY',
                        'type' => 'varchar(255)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'logins' => [
                    'login_type' => [
                        'op' => 'MODIFY',
                        'type' => "enum('password','session','cookie','facebook','twitter','google','vk','cookie_facebook','cookie_twitter','cookie_google','cookie_vk')",
                        'prop' => 'NOT NULL',
                    ],
                ],
            ],
            '3.15.0' => [
                'users' => [
                    'user_is_dark_mode' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ],
                ],
                'imports' => [
                    'import_continuous' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ]
                ],
                'query' => "INSERT INTO `%table_prefix%imports` (`import_path`, `import_options`, `import_status`, `import_users`, `import_images`, `import_albums`, `import_time_created`, `import_time_updated`, `import_errors`, `import_started`, `import_continuous`)
                SELECT '%rootPath%importing/no-parse', 'a:1:{s:4:\"root\";s:5:\"plain\";}', 'working', '0', '0', '0', NOW(), NOW(), '0', '0', '1' FROM DUAL
                WHERE NOT EXISTS (SELECT * FROM `%table_prefix%imports` WHERE `import_path`='%rootPath%importing/no-parse' AND `import_continuous`=1 LIMIT 1);
                INSERT INTO `%table_prefix%imports` (`import_path`, `import_options`, `import_status`, `import_users`, `import_images`, `import_albums`, `import_time_created`, `import_time_updated`, `import_errors`, `import_started`, `import_continuous`)
                SELECT '%rootPath%importing/parse-users', 'a:1:{s:4:\"root\";s:5:\"users\";}', 'working', '0', '0', '0', NOW(), NOW(), '0', '0', '1' FROM DUAL
                WHERE NOT EXISTS (SELECT * FROM `%table_prefix%imports` WHERE `import_path`='%rootPath%importing/parse-users' AND `import_continuous`=1 LIMIT 1);
                INSERT INTO `%table_prefix%imports` (`import_path`, `import_options`, `import_status`, `import_users`, `import_images`, `import_albums`, `import_time_created`, `import_time_updated`, `import_errors`, `import_started`, `import_continuous`)
                SELECT '%rootPath%importing/parse-albums', 'a:1:{s:4:\"root\";s:6:\"albums\";}', 'working', '0', '0', '0', NOW(), NOW(), '0', '0', '1' FROM DUAL
                WHERE NOT EXISTS (SELECT * FROM `%table_prefix%imports` WHERE `import_path`='%rootPath%importing/parse-albums' AND `import_continuous`=1 LIMIT 1);"
            ],
            '3.16.0' => [
                'locks' => [], // ADD TABLE
                'images' => [
                    'image_is_approved' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '1'",
                    ],
                ],
            ],
            '3.17.0' => [
                'albums' => [
                    'album_cover_id' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => 'DEFAULT NULL',
                    ],
                    'album_parent_id' => [
                        'op' => 'ADD',
                        'type' => 'bigint(32)',
                        'prop' => 'DEFAULT NULL',
                    ],
                ],
                'images' => [
                    'image_is_360' => [
                        'op' => 'ADD',
                        'type' => 'tinyint(1)',
                        'prop' => "NOT NULL DEFAULT '0'",
                    ]
                ],
                'query' => "UPDATE %table_prefix%albums
                SET album_cover_id = (SELECT image_id FROM %table_prefix%images WHERE image_album_id = album_id AND image_is_approved = 1 LIMIT 1)
                WHERE album_cover_id IS NULL;"
            ],
            '3.20.0' => [
                'assets' => [],
                'pages' => [
                    'page_code' => [
                        'op' => 'ADD',
                        'type' => 'text',
                        'prop' => null,
                    ]
                ],
                'query' => 'UPDATE `%table_prefix%settings` SET `setting_value`="default/favicon.png" WHERE `setting_name`="favicon_image" AND `setting_value`="favicon.png";
                UPDATE `%table_prefix%settings` SET `setting_value`="default/logo.png" WHERE `setting_name`="logo_image" AND `setting_value`="logo.png";
                UPDATE `%table_prefix%settings` SET `setting_value`="default/logo.svg" WHERE `setting_name`="logo_vector" AND `setting_value`="logo.svg";
                UPDATE `%table_prefix%settings` SET `setting_value`="default/home_cover.jpg" WHERE `setting_name`="homepage_cover_image" AND `setting_value`="home_cover.jpg";
                UPDATE `%table_prefix%settings` SET `setting_value`="default/home_cover.jpg" WHERE `setting_name`="homepage_cover_image" AND `setting_value` IS NULL;
                UPDATE `%table_prefix%settings` SET `setting_value`="default/logo_homepage.png" WHERE `setting_name`="logo_image_homepage" AND `setting_value`="logo_homepage.png";
                UPDATE `%table_prefix%settings` SET `setting_value`="default/logo_homepage.svg" WHERE `setting_name`="logo_vector_homepage" AND `setting_value`="logo_homepage.svg";'
            ],
            '3.20.1' => [
                'query' => 'UPDATE `%table_prefix%settings` SET `setting_value`="default/consent-screen_cover.jpg" WHERE `setting_name`="consent_screen_cover_image" AND `setting_value` IS NULL;'
            ],
            '3.20.2' => [
                'query' => 'ALTER TABLE `%table_prefix%importing` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
                ALTER TABLE `%table_prefix%imports` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;'
            ],
            '3.20.4' => [
                'query' => 'UPDATE `%table_prefix%settings` SET `setting_value`="default/watermark.png" WHERE `setting_name`="watermark_image" AND `setting_value`="watermark.png";'
            ],
            '3.20.5' => [
                'query' => 'UPDATE `%table_prefix%settings` SET `setting_typeset`="string" WHERE `setting_name`="explore_albums_min_image_count";',
            ],
            '3.20.6' => [
                'query' => 'UPDATE `%table_prefix%pages` SET `page_icon`="fas fa-landmark" WHERE `page_icon`="icon-text";
                UPDATE `%table_prefix%pages` SET `page_icon`="fas fa-lock" WHERE `page_icon`="icon-lock";
                UPDATE `%table_prefix%pages` SET `page_icon`="fas fa-at" WHERE `page_icon`="icon-mail";',
            ]
        ];
        $sql_update = [];
        if (!$maintenance) {
            $sql_update[] = "UPDATE `%table_prefix%settings` SET `setting_value` = 1 WHERE `setting_name` = 'maintenance';";
        }
        $required_sql_files = [];
        foreach ($update_table as $version => $changes) {
            foreach ($changes as $table => $columns) {
                if ($table == 'query') {
                    continue;
                }
                $schema_table = $schema[$table] ?? [];
                $create_table = false;
                if (!array_key_exists($table, $schema) and !in_array($table, $required_sql_files)) {
                    $create_table = true;
                } else {
                    if ($table == 'storages' and !array_key_exists('storage_bucket', $schema_table)) {
                        $create_table = true;
                    }
                }
                if (!in_array($table, $required_sql_files) and $create_table) {
                    $sql_update[] = file_get_contents(CHV_APP_PATH_INSTALL . 'schemas/'.$dbSchemaVer.'/' . $table . '.sql');
                    $required_sql_files[] = $table;
                }
                if (in_array($table, $required_sql_files)) {
                    continue;
                }
                if (isset($columns['op'])) {
                    switch ($columns['op']) {
                        case 'ALTER':
                            if ($DB_indexes[$table]['searchindex'] and strpos($columns['prop'], 'CREATE FULLTEXT INDEX `searchindex`') !== false) {
                                continue 2;
                            }
                            $sql_update[] = strtr('ALTER TABLE `%table_prefix%' . $table . '` %prop; %tail', ['%prop' => $columns['prop'], '%tail' => $columns['tail']]);
                            break;
                    }
                    continue;
                }
                foreach ($columns as $column => $column_meta) {
                    $query = null;
                    $schema_column = $schema_table[$column] ?? null;
                    switch ($column_meta['op']) {
                        case 'MODIFY':
                            if (array_key_exists($column, $schema[$table]) and ($schema_column['COLUMN_TYPE'] !== $column_meta['type'] or (preg_match('/DEFAULT NULL/i', $column_meta['prop']) and $schema_column['IS_NULLABLE'] == 'NO'))) {
                                $query = '`%column` %type';
                            }
                            break;
                        case 'CHANGE':
                            if (array_key_exists($column, $schema[$table])) {
                                $query = '`%column` `%to` %type';
                            }
                            break;
                        case 'ADD':
                            if (!array_key_exists($column, $schema[$table])) {
                                $query = '`%column` %type';
                            }
                            break;
                    }
                    if (!is_null($query)) {
                        $stock_tr = ['op', 'type', 'to', 'prop', 'tail'];
                        $meta_tr = [];
                        foreach ($stock_tr as $v) {
                            $meta_tr['%' . $v] = $column_meta[$v] ?? '';
                        }
                        $sql_update[] = strtr('ALTER TABLE `%table_prefix%' . $table . '` %op ' . $query . ' %prop; %tail', array_merge(['%column' => $column], $meta_tr));
                    }
                }
            }
            if (isset($changes['query'])) {
                if (version_compare($version, $installed_version, '>')) {
                    $sql_update[] = $changes['query'];
                }
            }
        }
        foreach ($CHV_indexes as $table => $indexes) {
            $field_prefix = DB::getFieldPrefix($table);
            foreach ($indexes as $index => $indexProp) {
                if ($index == 'searchindex' or $index == $field_prefix . '_id' or !G\starts_with($field_prefix . '_', $index)) {
                    continue;
                }
                if (!array_key_exists($index, $DB_indexes[$table])) {
                    $sql_update[] = 'ALTER TABLE `%table_prefix%' . $table . '` ADD ' . $indexProp . ';';
                }
            }
        }
        $updates_stock = [];
        foreach (array_merge($settings_updates, $update_table) as $k => $v) {
            if ($k == '3.0.0') {
                continue;
            }
            $updates_stock[] = $k;
        }
        $settings_flat = [];
        foreach ($updates_stock as $k) {
            $sql = null; // reset the pointer
            if (is_array($settings_updates[$k])) {
                foreach ($settings_updates[$k] as $k => $v) {
                    $settings_flat[$k] = $v;
                    if (in_array($k, $db_settings_keys)) {
                        continue;
                    }
                    $value = (is_null($v) ? 'NULL' : "'" . $v . "'");
                    $sql .= "INSERT INTO `%table_prefix%settings` (setting_name, setting_value, setting_default, setting_typeset) VALUES ('" . $k . "', " . $value . ', ' . $value . ", '" . Settings::getType($v) . "'); " . "\n";
                }
            }
            if ($sql) {
                $sql_update[] = $sql;
            }
        }
        foreach ($settings_delete as $k) {
            if (array_key_exists($k, Settings::get())) {
                $sql_update[] = "DELETE FROM `%table_prefix%settings` WHERE `setting_name` = '" . $k . "';";
            }
        }
        foreach ($settings_rename as $k => $v) {
            if (array_key_exists($k, Settings::get())) {
                $value = (is_null(Settings::get()[$k]) ? 'NULL' : "'" . Settings::get()[$k] . "'");
                $sql_update[] = 'UPDATE `%table_prefix%settings` SET `setting_value` = ' . $value . " WHERE `setting_name` = '" . $v . "';" . "\n" . "DELETE FROM `%table_prefix%settings` WHERE `setting_name` = '" . $k . "';";
            }
        }
        foreach ($settings_switch as $version => $keys) {
            if (!version_compare($version, $installed_version, '>')) {
                continue;
            }
            foreach ($keys as $k => $v) {
                if (!array_key_exists($k, Settings::get())) {
                    continue;
                }
                $value = (is_null(Settings::get()[$k]) ? 'NULL' : "'" . Settings::get()[$k] . "'");
                $value_default = (is_null($settings_flat[$k]) ? 'NULL' : "'" . $settings_flat[$k] . "'");
                $sql_update[] = 'UPDATE `%table_prefix%settings` SET `setting_value` = ' . $value . ", `setting_typeset` = '" . Settings::getType($settings_flat[$k]) . "' WHERE `setting_name` = '" . $v . "';" . "\n" . 'UPDATE `%table_prefix%settings` SET `setting_value` = ' . $value_default . ', `setting_default` = ' . $value_default . " WHERE `setting_name` = '" . $k . "';";
            }
        }
        $sql_update[] = 'UPDATE `%table_prefix%settings` SET `setting_value` = "' . G_APP_VERSION . '" WHERE `setting_name` = "chevereto_version_installed";';
        if (!$maintenance) {
            $sql_update[] = 'UPDATE `%table_prefix%settings` SET `setting_value` = 0 WHERE `setting_name` = "maintenance";';
        }
        $sql_update = join("\r\n", $sql_update);
        $sql_update = strtr($sql_update, [
            '%rootPath%' => G_ROOT_PATH,
            '%table_prefix%' => G\get_app_setting('db_table_prefix'),
            '%table_engine%' => $fulltext_engine,
        ]);
        $sql_update = preg_replace('/[ \t]+/', ' ', preg_replace('/\s*$^\s*/m', "\n", $sql_update));
        $isDumpUpdate = Settings::get('dump_update_query');
        if ($params['dump'] ?? null === true) {
            $isDumpUpdate = true;
        }
        if (!$isDumpUpdate) {
            $totalImages = (int) DB::get('stats', ['type' => 'total'])[0]['stat_images'];
            $stopWordsRegex = '/add|alter|modify|change/i';
            $slowUpdate = preg_match_all($stopWordsRegex, $sql_update);
            if ($slowUpdate && $totalImages >= 1000000) {
                $sql_update = '# To protect your DB is mandatory to run this MySQL script directly in your database console.'
                    . "\n" . '# Database:' . G\get_app_setting('db_name') . "\n" . $sql_update;
                $isDumpUpdate = true;
            }
        }
        if ($isDumpUpdate) {
            G\debug('# Dumped update query. https://chv.to/v3duq');
            G\debug($sql_update);
            G\logger("\n");
            die();
        }
        try {
            $db = DB::getInstance();
            $db->query($sql_update);
            $updated = $db->exec();
            if ($updated) {
                $chevereto_version_installed = DB::get('settings', ['name' => 'chevereto_version_installed'])[0]['setting_value'];
                if (G_APP_VERSION !== $chevereto_version_installed) {
                    G\debug('<pre><code style="display: block; overflow: auto;">' . $sql_update . '</code></pre>');
                    throw new Exception('Problems executing the MySQL update query. It is recommended that you issue the above SQL queries manually.');
                }
            }
            $doing = 'updated';
        } catch (Exception $e) {
            $error = true;
            $error_message = $e->getMessage();
            $doing = 'update_failed';
        }
    } else {
        try {
            $db = DB::getInstance();
        } catch (Exception $e) {
            $error = true;
            $error_message = sprintf($db_conn_error, $e->getMessage());
        }
        $doing = $error ? 'connect' : 'ready';

        if (!is_null($installed_version)) {
            $doing = 'already';
        }
    }
}
$input_errors = [];
if (!isset($installed_version) && !empty($params)) {
    if (isset($params['username']) and !in_array($doing, ['already', 'update'])) {
        $doing = 'ready';
    }
    switch ($doing) {
        case 'connect':
            $db_details = [];
            foreach ($db_array as $k => $v) {
                if ($v and $params[$k] == '') {
                    $error = true;
                    break;
                }
                $db_details[ltrim($k, 'db_')] = $params[$k] ?? null;
            }
            if ($error) {
                $error_message = 'Please fill the database details.';
            } else {
                // Details are complete. Lets check if the DB
                $db_details['driver'] = 'mysql';

                try {
                    $db = new DB($db_details); // Had to initiate a new instance for the new connection params
                } catch (Exception $e) {
                    $error = true;
                    $error_message = sprintf($db_conn_error, $e->getMessage());
                }

                if (!$error) {
                    // MySQL connection OK. Now, populate these values to settings.php
                    $settings_php = ['<?php'];
                    $settings_array = [];
                    foreach ($db_details as $k => $v) {
                        $settings_array['db_' . $k] = $v;
                    }
                    $settings_array['db_pdo_attrs'] = [];
                    $settings_array['debug_level'] = 1;
                    $var = var_export($settings_array, true);
                    $settings_php[] = '$settings = ' . $var . ';';
                    $settings_php = implode("\n", $settings_php);
                    $settings_file = G_APP_PATH . 'settings.php';
                    $fh = @fopen($settings_file, 'w');
                    if (!$fh or !fwrite($fh, $settings_php)) {
                        $doing = 'settings';
                    } else {
                        $doing = 'ready';
                    }
                    @fclose($fh);
                    if (function_exists('opcache_invalidate')) {
                        try {
                            opcache_invalidate($settings_file, true);
                        } catch (Throwable $e) {
                            // Ignore, Zend OPcache API is restricted by "restrict_api" configuration directive
                        }
                    }
                }
                if ($doing == 'ready') {
                    G\redirect('install');
                }
            }
            break;
        case 'ready':
            if (!filter_var($params['email'], FILTER_VALIDATE_EMAIL)) {
                $input_errors['email'] = _s('Invalid email');
            }
            if (!User::isValidUsername($params['username'])) {
                $input_errors['username'] = _s('Invalid username');
            }
            if (!preg_match('/' . getSetting('user_password_pattern') . '/', $params['password'])) {
                $input_errors['password'] = _s('Invalid password');
            }
            if (is_array($input_errors) && count($input_errors) > 0) {
                $error = true;
                $error_message = 'Please correct your data to continue.';
            } else {
                try {
                    $create_table = [];
                    foreach (new \DirectoryIterator(CHV_APP_PATH_INSTALL . 'schemas/'. $dbSchemaVer) as $fileInfo) {
                        if ($fileInfo->isDot() or $fileInfo->isDir()) {
                            continue;
                        }
                        $create_table[$fileInfo->getBasename('.sql')] = realpath($fileInfo->getPathname());
                    }

                    $install_sql = 'SET FOREIGN_KEY_CHECKS=0;' . "\n";

                    if ($is_2X) {
                        // Need to sync this to avoid bad datefolder mapping due to MySQL time != PHP time
                        // In Chevereto v2.X date was TIMESTAMP and in v3.X is DATETIME
                        $DT = new \DateTime();
                        $offset = $DT->getOffset();
                        $offsetHours = round(abs($offset) / 3600);
                        $offsetMinutes = round((abs($offset) - $offsetHours * 3600) / 60);
                        $offset = ($offset < 0 ? '-' : '+') . (strlen($offsetHours) < 2 ? '0' : '') . $offsetHours . ':' . (strlen($offsetMinutes) < 2 ? '0' : '') . $offsetMinutes;
                        $install_sql .= "SET time_zone = '" . $offset . "';";

                        $install_sql .= "
                        ALTER TABLE `chv_images`
                            MODIFY `image_id` bigint(32) NOT NULL AUTO_INCREMENT,
                            MODIFY `image_name` varchar(255),
                            MODIFY `image_date` DATETIME,
                            CHANGE `image_type` `image_extension` varchar(255),
                            CHANGE `uploader_ip` `image_uploader_ip` varchar(255),
                            CHANGE `storage_id` `image_storage_id` bigint(32),
                            DROP `image_delete_hash`,
                            ADD `image_date_gmt` datetime NOT NULL AFTER `image_date`,
                            ADD `image_title` varchar(100) NOT NULL,
                            ADD `image_description` text,
                            ADD `image_nsfw` tinyint(1) NOT NULL DEFAULT '0',
                            ADD `image_user_id` bigint(32) DEFAULT NULL,
                            ADD `image_album_id` bigint(32) DEFAULT NULL,
                            ADD `image_md5` varchar(32) NOT NULL,
                            ADD `image_source_md5` varchar(32) DEFAULT NULL,
                            ADD `image_storage_mode` enum('datefolder','direct','old') NOT NULL DEFAULT 'datefolder',
                            ADD `image_original_filename` text NOT NULL,
                            ADD `image_original_exifdata` longtext,
                            ADD `image_views` bigint(32) NOT NULL DEFAULT '0',
                            ADD `image_category_id` bigint(32) DEFAULT NULL,
                            ADD `image_chain` tinyint(128) NOT NULL,
                            ADD `image_thumb_size` int(11) NOT NULL,
                            ADD `image_medium_size` int(11) NOT NULL DEFAULT '0',
                            ADD `image_expiration_date_gmt` datetime DEFAULT NULL,
                            ADD `image_likes` bigint(32) NOT NULL DEFAULT '0',
                            ADD `image_is_animated` tinyint(1) NOT NULL DEFAULT '0',
                            ADD INDEX `image_name` (`image_name`),
                            ADD INDEX `image_size` (`image_size`),
                            ADD INDEX `image_width` (`image_width`),
                            ADD INDEX `image_height` (`image_height`),
                            ADD INDEX `image_date_gmt` (`image_date_gmt`),
                            ADD INDEX `image_nsfw` (`image_nsfw`),
                            ADD INDEX `image_user_id` (`image_user_id`),
                            ADD INDEX `image_album_id` (`image_album_id`),
                            ADD INDEX `image_storage_id` (`image_storage_id`),
                            ADD INDEX `image_md5` (`image_md5`),
                            ADD INDEX `image_source_md5` (`image_source_md5`),
                            ADD INDEX `image_likes` (`image_views`),
                            ADD INDEX `image_views` (`image_views`),
                            ADD INDEX `image_category_id` (`image_category_id`),
                            ADD INDEX `image_expiration_date_gmt` (`image_expiration_date_gmt`),
                            ADD INDEX `image_is_animated` (`image_is_animated`),
                            ENGINE=" . $fulltext_engine . ";

                        UPDATE `chv_images`
                            SET `image_date_gmt` = `image_date`,
                            `image_storage_mode` = CASE
                            WHEN `image_storage_id` IS NULL THEN 'datefolder'
                            WHEN `image_storage_id` = 0 THEN 'datefolder'
                            WHEN `image_storage_id` = 1 THEN 'old'
                            WHEN `image_storage_id` = 2 THEN 'direct'
                            END,
                            `image_storage_id` = NULL;

                        CREATE FULLTEXT INDEX searchindex ON `chv_images`(image_name, image_title, image_description, image_original_filename);

                        RENAME TABLE `chv_info` to `_chv_info`;
                        RENAME TABLE `chv_options` to `_chv_options`;
                        RENAME TABLE `chv_storages` to `_chv_storages`;";
                        unset($create_table['images']);
                        $chv_initial_settings['crypt_salt'] = $params['crypt_salt'];
                        $table_prefix = 'chv_';
                    } else {
                        $table_prefix = G\get_app_setting('db_table_prefix');
                    }
                    foreach ($create_table as $k => $v) {
                        $install_sql .= strtr(file_get_contents($v), [
                            '%rootPath%' => G_ROOT_PATH,
                            '%table_prefix%' => $table_prefix,
                            '%table_engine%' => $fulltext_engine,
                        ]) . "\n\n";
                    }
                    $chv_initial_settings['id_padding'] = $is_2X ? 0 : 5000;
                    $install_sql .= strtr($stats_query_legacy, [
                        '%rootPath%' => G_ROOT_PATH,
                        '%table_prefix%' => $table_prefix,
                        '%table_engine%' => $fulltext_engine,
                    ]);
                    // $params['dump'] = true;
                    if (($params['dump'] ?? false) === true) {
                        G\debug($install_sql);
                        G\logger("\n");
                        die(0);
                    }
                    $db = DB::getInstance();
                    $db->query($install_sql);
                    $db->exec();
                    $db->closeCursor();
                    $db->beginTransaction();
                    $db->query('INSERT INTO `' . DB::getTable('settings') . '` (setting_name, setting_value, setting_default, setting_typeset) VALUES (:name, :value, :value, :typeset)');
                    foreach ($chv_initial_settings as $k => $v) {
                        $db->bind(':name', $k);
                        $db->bind(':value', $v);
                        $db->bind(':typeset', ($v === 0 or $v === 1) ? 'bool' : 'string');
                        $db->exec();
                    }
                    if ($db->endTransaction()) {
                        $insert_admin = User::insert([
                            'username' => $params['username'],
                            'email' => $params['email'],
                            'is_admin' => 1,
                            'language' => $chv_initial_settings['default_language'],
                            'timezone' => $chv_initial_settings['default_timezone'],
                        ]);
                        Login::addPassword($insert_admin, $params['password']);
                        $lock_file = G_APP_PATH . 'installer.lock';
                        $fh = @fopen($lock_file, 'w');
                        @fclose($fh);
                        $doing = 'finished';
                    }
                } catch (Exception $e) {
                    $error = true;
                    $error_message = 'Installation error: ' . $e->getMessage();
                }
            }
            break;
    }
}
if(PHP_SAPI === 'cli') {
    switch($doing) {
        case 'already':
            G\logger("[OK] Chevereto is already installed\n");
            break;
        case 'finished':
            G\logger("[OK] Chevereto has been installed\n");
            break;
        case 'updated':
            G\logger("[OK] Chevereto database has been updated\n");
            break;
        case 'update_failed':
            G\logger("[ERR] Chevereto database update failure\n");
            die(255);
            break;
    }
} else {
    $doctitle = $doctitles[$doing] . ' - Chevereto ' . get_chevereto_version(true);
    $system_template = CHV_APP_PATH_CONTENT_SYSTEM . 'template.php';
    $install_template = CHV_APP_PATH_INSTALL . 'template/' . $doing . '.php';
    if (file_exists($install_template)) {
        ob_start();
        require_once $install_template;
        $html = ob_get_contents();
        ob_end_clean();
    } else {
        echo "Can't find " . G\absolute_to_relative($install_template);
        die(255);
    }
    if (!require_once($system_template)) {
        echo "Can't find " . G\absolute_to_relative($system_template);
        die(255);
    }
}
die(0);
