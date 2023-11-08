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

use function G\get_app_setting;
use function G\is_url_web;
use function G\rrmdir;
use function G\unlinkIfExists;

class User
{
    public static function getSingle($var, $by = 'id', $pretty = true)
    {
        $user_db = DB::get('users', [$by => $var], 'AND', null, 1);
        if (!$user_db) {
            return null;
        }
        $logins = Login::get(['user_id' => $user_db['user_id']]);
        $logins_aux = [];
        foreach ($logins as $k => $v) {
            $logins_aux[$v['type']][] = $v;
        }
        $user_db['user_login'] = $logins_aux;
        $user_db['user_login_rows'] = count($logins);
        // Count labels
        foreach (['user_image_count', 'user_album_count'] as $v) {
            if (is_null($user_db[$v]) or $user_db[$v] < 0) {
                $user_db[$v] = 0;
            }
        }
        // Content manager?
        $user_db['user_is_content_manager'] = $user_db['user_is_admin'] || $user_db['user_is_manager'];
        if (!array_key_exists('user_following', $user_db)) {
            $user_db['user_following'] = 0;
        }
        if (!array_key_exists('user_followers', $user_db)) {
            $user_db['user_followers'] = 0;
        }
        // Remove any unwanted tag from user_name
        $user_db['user_name'] = self::sanitizeUserName($user_db['user_name']);
        if ($pretty) {
            $user_db = self::formatArray($user_db);
        }

        return $user_db;
    }

    public static function getPrivate()
    {
        return [
            'name'            => _s('Private profile'),
            'username'        => 'private',
            'name_short'    => _s('Private'),
            'url'            => G\get_base_url(),
            'album_count'     => 0,
            'image_count'     => 0,
            'image_count_label'    => _n('image', 'images', 0),
            'album_count_display' => 0,
            'image_count_display' => 0,
            'is_private'    => true
        ];
    }

    public static function getAlbums($var)
    {
        $id = is_array($var) ? $var['id'] : $var;
        $user_albums = [];
        $user_stream = self::getStreamAlbum($var);
        if (is_array($user_stream)) {
            $user_albums['stream'] = $user_stream;
        }
        $map = [];
        $children = [];
        $db = DB::getInstance();
        $db->query('SELECT * FROM ' . DB::getTable('albums') . ' WHERE album_user_id=:image_user_id ORDER BY album_parent_id ASC, album_name ASC LIMIT :limit');
        $db->bind(':limit', intval(get_app_setting('user_albums_list_limit') ?? 300));
        $db->bind(':image_user_id', $id);
        $user_albums_db = $db->fetchAll();
        if ($user_albums_db) {
            $user_albums += $user_albums_db;
        }
        foreach ($user_albums as $k => &$v) {
            $album_id = isset($v['album_id'])
                ? $v['album_id']
                : 'stream';
            $map[$album_id] = $k;
            $parent_id = $v['album_parent_id'] ?? null;
            if (isset($v['album_image_count']) && $v['album_image_count'] < 0) {
                $v['album_image_count'] = 0;
            }
            $children[$parent_id][$album_id] = $v['album_name'];
            if (isset($parent_id)) {
                asort($children[$parent_id]);
            }
        }
        if(count($children[''] ?? []) == 0) {
            return [];
        }
        $list = [];
        foreach (array_keys($children['']) as $key) {
            self::iterate($key, $children, $list, $user_albums, $map, 0);
        }

        return $list;
    }

    private static function iterate($key, $array, &$list, $albums, $map, $level)
    {
        $album = $albums[$map[$key]];
        $album['album_indent'] = $level;
        $album['album_indent_string'] = '';
        if ($level > 0) {
            $album['album_indent_string'] = str_repeat('─', $level) . ' ';
        }
        $album = DB::formatRow($album, 'album');
        Album::fill($album);
        if ($key == 'stream') {
            $list[$key] = $album;
        } else {
            $list[] = $album;
        }
        if (!isset($array[$key])) {
            return;
        }
        $level++;
        foreach (array_keys($array[$key]) as $k) {
            self::iterate($k, $array, $list, $albums, $map, $level);
        }
    }

    public static function getStreamAlbum($user)
    {
        if (!is_array($user)) {
            $user = self::getSingle($user, 'id', true);
        }
        if (isset($user)) {
            return array(
                'album_id'             => null,
                'album_id_encoded'    => null,
                'album_name'         => _s("%s's images", $user['name_short']),
                'album_user_id'     => $user['id'],
                'album_privacy'        => 'public',
                'album_url'            => $user['url']
            );
        }
    }

    public static function getUrl($handle)
    {
        $username = is_array($handle) ? ($handle[isset($handle['user_username']) ? 'user_username' : 'username'] ?? null) : $handle;
        $id = is_array($handle) ? ($handle[isset($handle['user_id']) ? 'user_id' : 'id'] ?? null) : null;
        $path = getSetting('user_routing') ? null : 'user/';
        $url = $path . $username;
        // Single user mode on
        if (is_array($handle) and getSetting('website_mode') == 'personal' and $id == getSetting('website_mode_personal_uid')) {
            $url = getSetting('website_mode_personal_routing') !== '/' ? getSetting('website_mode_personal_routing') : null;
        }
        if (getSetting('user_subdomain_wildcard')) {
            return get_base_url_wildcard(null, $username);
        } else {
            return G\get_base_url($url);
        }
    }

    public static function getUrlAlbums($user_url)
    {
        return rtrim($user_url, '/') . '/albums';
    }

    /* Insert user
     * @returns uid
     */
    public static function insert($values)
    {
        if (!is_array($values)) {
            throw new DBException('Expecting array values, ' . gettype($values) . ' given in ' . __METHOD__, 100);
        }
        // TODO: Role handler (for importer)
        if (!isset($values['date'])) {
            $values['date'] = G\datetime();
        }
        if (!isset($values['date_gmt'])) {
            $values['date_gmt'] = G\datetimegmt();
        }
        if (!isset($values['language'])) {
            $values['language'] = getSetting('default_language');
        }
        if (!isset($values['timezone'])) {
            $values['timezone'] = getSetting('default_timezone');
        }
        if (isset($values['name'])) {
            $values['name'] = self::sanitizeUserName($values['name']);
        }
        if (!isset($values['registration_ip'])) {
            $values['registration_ip'] = G\get_client_ip();
        }
        if (!array_key_exists('is_dark_mode', $values)) {
            $values['is_dark_mode'] = Settings::get('theme_tone') == 'dark';
        }
        if (!Login::isAdmin()) {
            $db = DB::getInstance();
            $db->query('SELECT COUNT(*) c FROM ' . DB::getTable('users') . ' WHERE user_registration_ip=:ip AND user_status != "valid" AND user_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY)');
            $db->bind(':ip', $values['registration_ip']);
            if ($db->fetchSingle()['c'] > 5) {
                throw new Exception('Flood detected', 666);
            }
        }

        $user_id = DB::insert('users', $values);

        // Email notify
        if (!Login::isAdmin() && Settings::get('notify_user_signups')) {
            $message = implode('<br>', [
                'A new user has just signed up %user (%edit)',
                '',
                'Username: %username',
                'Email: %email',
                'Status: %status',
                'IP: %registration_ip',
                'Date (GMT): %date_gmt',
                '',
                'You can disable these notifications on %configure'
            ]);
            foreach (['username', 'email', 'status', 'registration_ip', 'date_gmt'] as $k) {
                $table['%' . $k] = $values[$k];
            }
            $table['%edit'] = '<a href="' . G\get_base_url('dashboard/user/' . $user_id) . '">edit</a>';
            $table['%user'] = '<a href="' . self::getUrl($values['username']) . '">' . $values['username'] . '</a>';
            $table['%configure'] = '<a href="' . G\get_base_url('dashboard/settings/users') . '">dashboard/settings/users</a>';
            system_notification_email([
                'subject' => sprintf('New user signup %s', $values['username']),
                'message' => strtr($message, $table),
            ]);
        }
        // Track stats
        Stat::track([
            'action'    => 'insert',
            'table'        => 'users',
            'value'        => '+1',
            'date_gmt'    => $values['date_gmt'],
            'user_id' => $user_id,
        ]);
        return $user_id;
    }

    public static function update($id, $values)
    {
        if (isset($values['name'])) {
            $values['name'] = self::sanitizeUserName($values['name']);
        }
        return DB::update('users', $values, ['id' => $id]);
    }

    public static function uploadPicture($user, $type, $source)
    {
        $type = strtolower($type);
        if (!in_array($type, ['background', 'avatar'])) {
            throw new UserException('unexpected upload value', 403);
        }
        if (!is_array($user)) {
            $user = self::getSingle($user, 'id');
        }
        if (!$user) {
            throw new UserException("target user doesn't exists", 403);
        }
        $localPath = CHV_PATH_CONTENT_IMAGES_USERS . $user['id_encoded'] . '/';
        $storagePath = ltrim(G\absolute_to_relative($localPath), '/');
        $image_upload = Image::upload(
            $source,
            $localPath,
            ($type == 'avatar' ? 'av' : 'bkg') . '_' . strtotime(G\datetimegmt()),
            ['max_size' => G\get_bytes(Settings::get('user_image_' . $type . '_max_filesize_mb') . ' MB')]
        );
        $uploaded = $image_upload['uploaded'];
        if ($type == 'avatar') {
            $max_res = ['width' => 160, 'height' => 160];
            $must_resize = ($uploaded['fileinfo']['width'] !== $max_res['width'] and $uploaded['fileinfo']['height'] !== $max_res['height']);
            $filename = $uploaded['name'];
        } else {
            $max_res = ['width' => 1920];
            $must_resize = $uploaded['fileinfo']['width'] > $max_res['width'];
            $medium = Image::resize($uploaded['file'], null, $uploaded['name'] . '.md', ['width' => 500]);
            $toStorage[] = [
                'file' => $medium['file'],
                'filename' => $medium['filename'],
                'mime' => $medium['fileinfo']['mime'],
            ];
        }
        if ($must_resize) {
            $uploaded = Image::resize($uploaded['file'], null, null, $max_res);
        }
        $toStorage[] = [
            'file' => $uploaded['file'],
            'filename' => $uploaded['filename'],
            'mime' => $uploaded['fileinfo']['mime'],
        ];
        if (isset($uploaded)) {
            $toDelete = [];
            $convert = new ImageConvert($uploaded['file'], 'jpg', $uploaded['file'], 90);
            $uploaded['file'] = $convert->out;
            $user_edit = self::update($user['id'], [$type . '_filename' => $uploaded['filename']]);
            if ($user_edit) {
                $assetStorage = AssetStorage::getStorage();
                Storage::uploadFiles($toStorage, $assetStorage, [
                    'keyprefix' => $storagePath
                ]);

                if(isset($user[$type])) {
                    $image_path = $storagePath . $user[$type]['filename'];
                    if ($type == 'background') {
                        $pathinfo = pathinfo($image_path);
                        $image_md_path = str_replace($pathinfo['basename'], $pathinfo['filename'] . '.md.' . $pathinfo['extension'], $image_path);
                        $toDelete[] = ['key' => $image_md_path];
                    }
                    $toDelete[] = ['key' => $image_path];
                }
                if($toDelete !== []) {
                    Storage::deleteFiles($toDelete, $assetStorage);
                }
            }
            if(!AssetStorage::isLocalLegacy()) {
                $toUnlink = [$uploaded['file']];
                if ($type == 'background') {
                    $pathinfo = pathinfo($uploaded['file']);
                    $image_md_path = str_replace($pathinfo['basename'], $pathinfo['filename'] . '.md.' . $pathinfo['extension'], $uploaded['file']);
                    $toUnlink[] = $image_md_path;
                }
                foreach($toDelete as $pos => $delete) {
                    $toUnlink[] = G_ROOT_PATH . $delete['key'];
                }
                foreach($toUnlink as $remove) {
                    unlinkIfExists($remove);
                }
            }
            $uploaded['fileinfo']['url'] = G\str_replace_first(G\get_root_url(), $assetStorage['url'], $uploaded['fileinfo']['url']);

            return $uploaded['fileinfo'];
        }

        return null;
    }

    public static function deletePicture($user, $deleting)
    {
        $deleting = strtolower($deleting);
        if (!in_array($deleting, ['background', 'avatar'])) {
            throw new UserException('Unexpected delete value', 100);
        }
        if (!is_array($user)) {
            $user = self::getSingle($user, 'id', true);
        }
        if (!$user) {
            throw new UserException("Target user doesn't exists", 101);
        }
        if (!$user[$deleting]) {
            throw new UserException('user ' . $deleting . " doesn't exists", 102);
        }
        $localPath = CHV_PATH_CONTENT_IMAGES_USERS . $user['id_encoded'] . '/';
        $storagePath = ltrim(G\absolute_to_relative($localPath), '/');
        $assetStorage = AssetStorage::getStorage();
        $toDelete = [];
        $image_path = $storagePath . $user[$deleting]['filename'];
        if ($deleting == 'background') {
            $pathinfo = pathinfo($image_path);
            $image_md_path = str_replace($pathinfo['basename'], $pathinfo['filename'] . '.md.' . $pathinfo['extension'], $image_path);
            $toDelete[] = ['key' => $image_md_path];
        }
        $toDelete[] = ['key' => $image_path];
        Storage::deleteFiles($toDelete, $assetStorage);
        self::update($user['id'], [$deleting . '_filename' => null]);

        return true;
    }

    public static function delete($user)
    {
        if (!is_array($user)) {
            $user = self::getSingle($user, 'id', true);
        }
        $user_images_path = CHV_PATH_CONTENT_IMAGES_USERS . $user['id_encoded'];
        rrmdir($user_images_path);
        $db = DB::getInstance();
        $db->query('SELECT image_id FROM ' . DB::getTable('images') . ' WHERE image_user_id=:image_user_id');
        $db->bind(':image_user_id', $user['id']);
        $user_images = $db->fetchAll();
        foreach ($user_images as $user_image) {
            Image::delete($user_image['image_id']);
        }
        Notification::delete([
            'table'        => 'users',
            'user_id'    => $user['id'],
        ]);
        Stat::track([
            'action'    => 'delete',
            'table'        => 'users',
            'value'        => '-1',
            'user_id'    => $user['id'],
            'date_gmt'    => $user['date_gmt']
        ]);
        $sql = strtr('UPDATE `%table_users` SET user_likes = user_likes - COALESCE((SELECT COUNT(*) FROM `%table_likes` WHERE like_user_id = %user_id AND user_id = like_content_user_id AND like_user_id <> like_content_user_id GROUP BY like_content_user_id),"0");', [
            '%table_users'    => DB::getTable('users'),
            '%table_likes'    => DB::getTable('likes'),
            '%user_id'        => $user['id'],
        ]);
        DB::queryExec($sql);
        $sql = strtr('UPDATE `%table_users` SET user_followers = user_followers - COALESCE((SELECT 1 FROM `%table_follows` WHERE follow_user_id = %user_id AND user_id = follow_followed_user_id AND follow_user_id <> follow_followed_user_id GROUP BY follow_followed_user_id),"0");', [
            '%table_users'    => DB::getTable('users'),
            '%table_follows' => DB::getTable('follows'),
            '%user_id'        => $user['id'],
        ]);
        DB::queryExec($sql);
        $sql = strtr('UPDATE `%table_users` SET user_following = user_following - COALESCE((SELECT 1 FROM `%table_follows` WHERE follow_followed_user_id = %user_id AND user_id = follow_user_id AND follow_user_id <> follow_followed_user_id GROUP BY follow_user_id),"0");', [
            '%table_users'    => DB::getTable('users'),
            '%table_follows' => DB::getTable('follows'),
            '%user_id'        => $user['id'],
        ]);
        DB::queryExec($sql);
        DB::delete('albums', ['user_id' => $user['id']]); // Delete albums DB
        DB::delete('images', ['user_id' => $user['id']]); // Delete images DB
        DB::delete('logins', ['user_id' => $user['id']]); // Delete logins
        DB::delete('likes', ['user_id' => $user['id']]); // Delete user likes
        DB::delete('follows', ['user_id' => $user['id'], 'followed_user_id' => $user['id']], 'OR'); // Delete user's followers and follows
        DB::delete('users', ['id' => $user['id']]); // Delete user DB
    }

    public static function statusRedirect($status)
    {
        if (isset($status) and $status != null and $status !== 'valid') {
            if ($status == 'awaiting-email') {
                $status = 'email-needed';
            }
            G\redirect('account/' . $status);
        }
    }
    /**
     * Checks if a username string is usable as a valid username or not.
     */
    public static function isValidUsername($string)
    {
        $restricted = [
            'tag', 'tags',
            'categories',
            'profile',
            'messages',
            'map',
            'feed',
            'events',
            'notifications',
            'discover',
            'upload',
            'following', 'followers',
            'flow', 'trending', 'popular', 'fresh', 'upcoming', 'editors', 'profiles',
            'activity', 'upgrade', 'account',
            'affiliates', 'billing',
            'do', 'go', 'redirect',
            'api', 'sdk', 'plugin', 'plugins', 'tools',
            'external',
            'importer',
        ];
        $virtual_routes = ['image', 'album'];
        foreach ($virtual_routes as $k) {
            $restricted[] = getSetting('route_' . $k);
        }
        return preg_match('/' . getSetting('username_pattern') . '/', $string) && !in_array($string, $restricted) && !G\is_route_available($string) && !file_exists(G_ROOT_PATH . $string);
    }

    public static function formatArray($object)
    {
        if ($object) {
            $output = DB::formatRow($object);
            self::fill($output);
        }
        return $object ? $output : null;
    }

    public static function fill(&$user)
    {
        $user['id_encoded'] = encodeID($user['id'] ?? 0);
        $user['image_count_display'] = !isset($user['image_count']) ? 0 : G\abbreviate_number($user['image_count']);
        $user['album_count_display'] = !isset($user['album_count']) ? 0 : G\abbreviate_number($user['album_count']);
        $user['url'] = self::getUrl($user);
        $user['url_albums'] = self::getUrlAlbums($user['url']);
        $user['url_liked'] = $user['url'] . '/liked';
        $user['url_following'] = $user['url'] . '/following';
        $user['url_followers'] = $user['url'] . '/followers';
        if (isset($user['website']) && !is_url_web($user['website'])) {
            unset($user['website']);
        }
        if (isset($user['website'])) {
            $user['website_safe_html'] = G\safe_html($user['website']);
            $user['website_display'] = $user['is_admin'] ? $user['website_safe_html'] : get_redirect_url($user['website_safe_html']);
        }
        if (isset($user['bio'])) {
            $user['bio_safe_html'] = G\safe_html($user['bio']);
            $user['bio_linkify'] = $user['is_admin'] ? G\linkify($user['bio_safe_html'], ['attr' => ['target' => '_blank']]) : linkify_redirector($user['bio_safe_html']);
        }
        if (empty($user['name']) && !empty($user['username'])) {
            $user['name'] = ucfirst($user['username']);
        }
        $user['name'] = $user['name'] ?? ucfirst($user['username'] ?? '') ?? '';
        foreach (['image_count', 'album_count'] as $v) {
            $single = $v == 'image_count' ? 'image' : 'album';
            $plural = $v == 'image_count' ? 'images' : 'albums';
            if (is_callable('_n')) {
                $user[$v . '_label'] = _n($single, $plural, $user[$v] ?? 0);
            } else {
                $user[$v . '_label'] = ($user[$v] ?? null) == 1 ? $single : $plural;
            }
        }
        $name_array = explode(' ', $user['name'] ?? '');
        $user['firstname'] = mb_strlen($name_array[0]) > 20 ? trim(mb_substr($name_array[0], 0, 20, 'UTF-8')) : $name_array[0];
        $user['firstname_html'] = G\safe_html(strip_tags($user['firstname']));
        $user['name_short'] = mb_strlen($user['name']) > 20 ? $user['firstname'] : $user['name'];
        $user['name_short_html'] = G\safe_html(strip_tags($user['name_short']));
        if (isset($user['avatar_filename'])) {
            $avatar_file = $user['id_encoded'] . '/' . $user['avatar_filename'];
            $user['avatar'] = array(
                'filename'    => $user['avatar_filename'],
                'url'        => get_users_image_url($avatar_file)
            );
        }
        unset($user['avatar_filename']);
        if (isset($user['background_filename'])) {
            $background_file = $user['id_encoded'] . '/' . $user['background_filename'];
            $background_path = CHV_PATH_CONTENT_IMAGES_USERS . $background_file;
            $pathinfo = pathinfo($background_path);
            $background_md_file = $user['id_encoded'] . '/' . $pathinfo['filename'] . '.md.' . $pathinfo['extension'];
            $user['background'] = array(
                'filename'    => $user['background_filename'],
                'url'        => get_users_image_url($user['id_encoded'] . '/' . $user['background_filename']),
                'medium'    => [
                    'filename'    => $pathinfo['basename'],
                    'url'         => get_users_image_url($background_md_file)
                ]
            );
        }
        unset($user['background_filename']);
        unset($user['facebook_username']);
        if (isset($user['twitter_username'])) {
            $user['twitter'] = array(
                'username'    => $user['twitter_username'],
                'url'        => 'http://twitter.com/' . $user['twitter_username']
            );
        }
        unset($user['twitter_username']);
        if(!isset($user['notifications_unread'])) {
            $user['notifications_unread'] = 0;
        }
        $user['notifications_unread_display'] = $user['notifications_unread'] > 10 ? '+10' : $user['notifications_unread'];
    }

    public static function sanitizeUserName($name)
    {
        return preg_replace('#<|>#', '', $name);
    }

    // Clean unconfirmed accounts
    public static function cleanUnconfirmed($limit = null)
    {
        $db = DB::getInstance();
        $query = 'SELECT * FROM ' . DB::getTable('users') . ' WHERE user_status IN ("awaiting-confirmation", "awaiting-email") AND user_date_gmt <= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 DAY) ORDER BY user_id DESC';
        if (is_int($limit)) {
            $query .= ' LIMIT ' . $limit;
        }
        $db->query($query);
        $users = $db->fetchAll();
        foreach ($users as $user) {
            $user = self::formatArray($user);
            self::delete($user);
        }
    }
}

class UserException extends Exception
{
}
