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
use SEOURLify;

class Album
{
    public static function getSingle($id, $sumview = false, $pretty = true, $requester = null)
    {
        $tables = DB::getTables();
        $query = 'SELECT * FROM ' . $tables['albums'] . "\n";
        $joins = [
            'LEFT JOIN ' . $tables['users'] . ' ON ' . $tables['albums'] . '.album_user_id = ' . $tables['users'] . '.user_id'
        ];

        if ($requester) {
            if (!is_array($requester)) {
                $requester = User::getSingle($requester, 'id');
            }
            if (version_compare(Settings::get('chevereto_version_installed'), '3.9.0', '>=')) {
                $joins[] = 'LEFT JOIN ' . $tables['likes'] . ' ON ' . $tables['likes'] . '.like_content_type = "album" AND ' . $tables['albums'] . '.album_id = ' . $tables['likes'] . '.like_content_id AND ' . $tables['likes'] . '.like_user_id = ' . $requester['id'];
            }
        }

        $query .=  implode("\n", $joins) . "\n";
        $query .= 'WHERE album_id=:album_id;' . "\n";

        if ($sumview) {
            $query .= 'UPDATE ' . $tables['albums'] . ' SET album_views = album_views + 1 WHERE album_id=:album_id';
        }

        $db = DB::getInstance();
        $db->query($query);
        $db->bind(':album_id', $id);
        $album_db = $db->fetchSingle();
        if (!isset($album_db)) {
            return $album_db;
        }

        if ($sumview) {
            $album_db['album_views'] += 1;
            // Track stats
            Stat::track([
                'action'    => 'update',
                'table'        => 'albums',
                'value'        => '+1',
                'user_id'    => $album_db['album_user_id'],
            ]);
        }

        if ($requester) {
            $album_db['album_liked'] = (bool) $album_db['like_user_id'];
        }
        $return = $album_db;
        if($return === false) {
            return $return;
        }
        
        return $pretty ? self::formatArray($return) : $return;
    }

    public static function getMultiple($ids, $pretty = false)
    {
        if (!is_array($ids)) {
            throw new AlbumException('Expecting $ids array in ' . __METHOD__, 100);
        }
        if (count($ids) == 0) {
            throw new AlbumException('Null $ids provided in ' . __METHOD__, 100);
        }

        $tables = DB::getTables();
        $query = 'SELECT * FROM ' . $tables['albums'] . "\n";
        $joins = array(
            'LEFT JOIN ' . $tables['users'] . ' ON ' . $tables['albums'] . '.album_user_id = ' . $tables['users'] . '.user_id'
        );

        $query .=  implode("\n", $joins) . "\n";
        $query .= 'WHERE album_id IN (' . join(',', $ids) . ')' . "\n";

        $db = DB::getInstance();
        $db->query($query);
        $db_rows = $db->fetchAll();
        if ($pretty) {
            $return = [];
            foreach ($db_rows as $k => $v) {
                $return[$k] = self::formatArray($v);
            }
            return $return;
        }
        return $db_rows;
    }

    public static function sumView($id, $album = [])
    {
        if (!G\is_integer($id)) {
            throw new Exception('Invalid $id in ' . __METHOD__);
        }
        if ($album['id'] !== $id) {
            $album = self::getSingle($id, false);
            if (!$album) {
                throw new Exception(sprintf('Invalid album %s in ' . __METHOD__, $id));
            }
        }
        $increment = '+1';
        DB::increment('albums', ['views' => $increment], ['id' => $id]);
        Stat::track([
            'action'    => 'update',
            'table'        => 'albums',
            'value'        => $increment,
            'user_id'    => $album['album_user_id'],
        ]);
        $_SESSION['album_view_stock'][] = $id;
    }

    public static function getUrl($album_id, $title = null)
    {
        if ($title == null) {
            $seo = null;
        } else {
            $seo = SEOURLify::filter($title);
        }
        $url = $seo ? ($seo . '.' . $album_id) : $album_id;
        return G\get_base_url(getSetting('route_album') . '/' . $url);
    }

    // $name, $user_id = null, $privacy = 'public', $description = '', $password = null
    public static function insert($values)
    {
        if (!isset($values['user_id'])) {
            $values['user_id'] = null;
        }
        if (!isset($values['description'])) {
            $values['description'] = '';
        }
        if (!isset($values['password'])) {
            $values['password'] = null;
        }
        if (($values['privacy'] ?? null) == 'password' && !G\check_value($values['password'])) {
            throw new AlbumException('Missing album $password', 101);
        }
        $flood = self::handleFlood();
        if ($flood) {
            throw new AlbumException(
                _s(
                    'Flooding detected. You can only upload %limit% %content% per %time%',
                    [
                        '%content%' => _n('album', 'albums', $flood['limit']),
                        '%limit%' => $flood['limit'],
                        '%time%' => $flood['by']
                    ]
                ),
                130
            );
        }
        if (!isset($values['name'])) {
            $values['name'] = _s('Untitled') . ' ' . G\datetime();
        }
        $privacyOpts = ['public', 'password', 'private_but_link'];
        if (Login::isLoggedUser()) {
            $privacyOpts[] = 'private';
        }
        if (in_array($values['privacy'], $privacyOpts) == false) {
            $values['privacy'] = 'public';
        }
        G\nullify_string($values['description']);
        if (empty($values['creation_ip'])) {
            $values['creation_ip'] = G\get_client_ip();
        }
        $album_array = [
            'name'            => $values['name'],
            'user_id'        => $values['user_id'],
            'date'            => G\datetime(),
            'date_gmt'        => G\datetimegmt(),
            'privacy'        => $values['privacy'],
            'password'        => $values['privacy'] == 'password' ? $values['password'] : null,
            'description'    => $values['description'],
            'creation_ip'    => $values['creation_ip'],
            'parent_id' => $values['parent_id'] ?? null
        ];
        $insert = DB::insert('albums', $album_array);
        // +1 on user
        if (Login::isLoggedUser()) {
            DB::increment('users', ['album_count' => '+1'], ['id' => $values['user_id']]);
        } else {
            // Save this upload into "session" record
            if (!isset($_SESSION['guest_albums'])) {
                $_SESSION['guest_albums'] = [];
            }
            $_SESSION['guest_albums'][] = $insert;
        }
        // Track stats
        Stat::track([
            'action'    => 'insert',
            'table'        => 'albums',
            'value'        => '+1',
            'date_gmt'    => $album_array['date_gmt']
        ]);
        return $insert;
    }

    // Move album $from album(s) $to another album
    public static function moveContents($from, $to=null)
    {
        $ids = is_array($from) ? $from : array($from);
        $db = DB::getInstance();
        $db->query('UPDATE ' . DB::getTable('albums') . ' SET album_parent_id=:album_parent_id WHERE album_id IN (' . implode(',', $ids) . ')');
        $db->bind(':album_parent_id', $to);
        $albums = $db->exec();
        if (!$albums) {
            return false;
        }
        return true;
    }

    public static function addImage($album_id, $id)
    {
        return self::addImages($album_id, array($id));
    }

    public static function addImages($album_id, $ids)
    {
        // $album_id can be null.. Remember the user stream
        if (!is_array($ids) or count($ids) == 0) {
            throw new AlbumException('Expecting array values, ' . gettype($values) . ' given in ' . __METHOD__, 100);
        }
        // Get the images
        $images = Image::getMultiple($ids, true);
        // Get the albums
        $albums = [];
        foreach ($images as $k => $v) {
            if ($v['album']['id'] and $v['album']['id'] !== $album_id) {
                $album_k = $v['album']['id'];
                if (!array_key_exists($album_k, $albums)) {
                    $albums[$album_k] = [];
                }
                $albums[$album_k][] = $v['id'];
            }
        }

        $db = DB::getInstance();
        $db->query('UPDATE `' . DB::getTable('images') . '` SET `image_album_id`=:image_album_id WHERE `image_id` IN (' . implode(',', $ids) . ')');
        $db->bind(':image_album_id', $album_id);
        $exec = $db->exec();
        if ($exec and $db->rowCount() > 0) {
            // Update the new album
            if (!is_null($album_id)) {
                self::updateImageCount($album_id, $db->rowCount());
            }
            // Update the old albums
            if (count($albums) > 0) {
                $album_query = '';
                $album_query_tpl = 'UPDATE `' . DB::getTable('albums') . '` SET `album_image_count` = GREATEST(`album_image_count` - :counter, 0) WHERE `album_id` = :album_id;';
                foreach ($albums as $k => $v) {
                    $album_query .= strtr($album_query_tpl, [':counter' => count($v), ':album_id' => $k]);
                }
                $db = DB::getInstance();
                $db->query($album_query);
                $db->exec();
            }
        }
        // Deprecate old covers
        $db = DB::getInstance();
        $db->query('UPDATE `' . DB::getTable('albums') . '` SET `album_cover_id` = NULL WHERE `album_cover_id` IN('.implode(',', $ids).');');
        $db->exec();

        // Fix missing cover
        $album = Album::getSingle($album_id, false, true);
        if ($album['cover_id'] === null) {
            self::populateCover($album_id);
        }

        return $exec;
    }

    public static function update($id, $values)
    {
        if (array_key_exists('description', $values)) {
            G\nullify_string($values['description']);
        }
        return DB::update('albums', $values, ['id' => $id]);
    }

    public static function populateCover($id)
    {
        $db = DB::getInstance();
        $db->query('UPDATE `' . DB::getTable('albums') . '`
                SET album_cover_id = (SELECT image_id FROM `' . DB::getTable('images') . '` WHERE image_album_id = album_id AND image_is_approved = 1 ORDER BY image_id DESC LIMIT 1)
                WHERE album_id = :album_id;');
        $db->bind(':album_id', $id);
        $db->exec();
    }

    // Delete album, return the number of deleted images
    public static function delete($id)
    {
        $images_deleted = 0;
        $user_id = DB::get('albums', ['id' => $id])[0]['album_user_id'] ?? null;
        $album = self::getSingle($id);
        if (!$album) {
            return false;
        }
        // Recursive removal
        foreach (DB::get('albums', ['parent_id' => $id]) as $child) {
            $images_deleted += static::delete($child['album_id']);
        }
        $delete = DB::delete('albums', ['id' => $id]);
        if (!$delete) {
            return false;
        }
        // Delete album images
        $db = DB::getInstance();
        $db->query('SELECT image_id FROM ' . DB::getTable('images') . ' WHERE image_album_id=:image_album_id');
        $db->bind(':image_album_id', $id);
        $album_image_ids = $db->fetchAll();
        // Delete the files
        foreach ($album_image_ids as $k => $v) {
            if (Image::delete($v['image_id'], false)) { // We will update the user counts (image + album) at once
                $images_deleted++;
            }
        }
        // Update user
        if (isset($user_id)) {
            $user_updated_counts = [
                'album_count' => '-1',
                'image_count' => '-' . $images_deleted
            ];
            DB::increment('users', $user_updated_counts, ['id' => $user_id]);
        }
        // Track stats
        Stat::track([
            'action'    => 'delete',
            'table'        => 'albums',
            'value'        => '-1',
            'date_gmt'    => $album['date_gmt']
        ]);
        return $images_deleted;
    }

    public static function deleteMultiple($ids)
    {
        if (!is_array($ids)) {
            throw new AlbumException('Expecting array argument, ' . gettype($ids) . ' given in ' . __METHOD__, 100);
        }
        $affected = 0;
        foreach ($ids as $id) {
            $affected += self::delete($id);
        }
        return $affected;
    }

    public static function updateImageCount($id, $counter = 1, $operator = '+')
    {
        $query = 'UPDATE `' . DB::getTable('albums') . '` SET `album_image_count` = ';
        if (in_array($operator, ['+', '-'])) {
            $query .= 'GREATEST(`album_image_count` ' . $operator . ' ' . $counter . ', 0)';
        } else {
            $query .= $counter;
        }
        $query .= ' WHERE `album_id` = :album_id';
        $db = DB::getInstance();
        $db->query($query);
        $db->bind(':album_id', $id);
        $exec = $db->exec();
        return $exec;
    }

    public static function fill(&$album, &$user = [])
    {
        $album['id_encoded'] = isset($album['id']) ? encodeID($album['id']) : null;
        if (!isset($album['name']) && isset($user['id'])) {
            $album['name'] = _s("%s's images", $user['name_short']);
        }
        if (!isset($album['id'])) {
            $album['url'] = $user ? User::getUrl($user['username']) : null;
            $album['url_short'] = $album['url'];
        } else {
            $album['url'] = self::getUrl($album['id_encoded'], getSetting('seo_album_urls') ? $album['name'] : '');
            $album['url_short'] = self::getUrl($album['id_encoded'], '');
        }
        $album['name_html'] = G\safe_html($album['name'] ?? '');
        if (!isset($album['privacy'])) {
            $album['privacy'] = "public";
        }
        switch ($album['privacy']) {
            case 'private_but_link':
                $album['privacy_notes'] = _s('Note: This content is private but anyone with the link will be able to see this.');
                break;
            case 'password':
                $album['privacy_notes'] = _s('Note: This content is password protected. Remember to pass the content password to share.');
                break;
            case 'private':
                $album['privacy_notes'] = _s('Note: This content is private. Change privacy to "public" to share.');
                break;
            default:
                $album['privacy_notes'] = null;
                break;
        }

        $private_str = _s('Private');
        $privacy_to_label = [
            'public'            => _s('Public'),
            'private'            => $private_str . '/' . _s('Me'),
            'private_but_link'    => $private_str . '/' . _s('Link'),
            'password'            => $private_str . '/' . _s('Password'),
        ];


        $album['privacy_readable'] = $privacy_to_label[$album['privacy']];
        $album['name_with_privacy_readable'] = ($album['name'] ?? '') . ' (' . $album['privacy_readable'] . ')';
        $album['name_with_privacy_readable_html'] = G\safe_html($album['name_with_privacy_readable']);
        $album['name_truncated'] = G\truncate($album['name'] ?? '', 28);
        $album['name_truncated_html'] = G\safe_html($album['name_truncated']);

        if (!empty($user)) {
            User::fill($user);
        }

        $display_url = '';
        $display_width = '';
        $display_height = '';

        if(!empty($album['cover_id'])) {
            $image = Image::getSingle($album['cover_id'], false, false);
            if(!empty($image)) {
                $image = DB::formatRow($image);
                unset($image['album']);
                Image::fill($image);
                $display_url = $image['display_url'];
                $display_width = $image['display_width'];
                $display_height = $image['display_height'];
            }
        }

        $album['display_url'] = $display_url;
        $album['display_width'] = $display_width;
        $album['display_height'] = $display_height;
        if(!isset($album['date_gmt'])) {
            $album['date_gmt'] = $user['date_gmt'] ?? G\datetimegmt();
        }
        $album['date_fixed_peer'] = Login::getUser() ? G\datetimegmt_convert_tz($album['date_gmt'], Login::getUser()['timezone']) : $album['date_gmt'];
    }

    public static function formatArray(array $dbrow, $safe = false)
    {
        $output = DB::formatRow($dbrow);
        self::fill($output, $output['user']);
        $output['views_label'] = _n('view', 'views', $output['views'] ?? 0);
        $output['how_long_ago'] = time_elapsed_string($output['date_gmt'] ?? '');

        if (isset($output['images_slice'])) {
            foreach ($output['images_slice'] as $k => &$v) {
                $v = Image::formatArray($v);
                $v['flag'] = $v['nsfw'] ? 'unsafe' : 'safe';
            }
        }

        if ($safe) {
            unset($output['id'], $output['privacy_extra'], $output['user']['id']);
        }

        return $output;
    }

    public static function checkPassword($password, $user_password)
    {
        return G\timing_safe_compare($password, $user_password);
    }

    public static function storeUserPasswordHash($album_id, $user_password)
    {
        $_SESSION['password']['album'][$album_id] = password_hash($user_password, PASSWORD_BCRYPT);
    }

    public static function checkSessionPassword($album = [])
    {
        $user_password_hash = $_SESSION['password']['album'][$album['id']];
        if (!isset($user_password_hash) || !password_verify($album['password'], $user_password_hash)) {
            unset($_SESSION['password']['album'][$album['id']]);
            return false;
        }
        return true;
    }

    // Handle album creation flood
    protected static function handleFlood()
    {
        $logged_user = Login::getUser();
        if (!$logged_user or $logged_user['is_admin']) {
            return false;
        }
        $flood_limit = [
            'minute'    => 20,
            'hour'      => 200,
            'day'       => 400,
            'week'      => 2000,
            'month'     => 10000
        ];
        try {
            $db = DB::getInstance();
            $flood_db = $db->queryFetchSingle(
                "SELECT
			COUNT(IF(album_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE), 1, NULL)) AS minute,
			COUNT(IF(album_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 1, NULL)) AS hour,
			COUNT(IF(album_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY), 1, NULL)) AS day,
			COUNT(IF(album_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 WEEK), 1, NULL)) AS week,
			COUNT(IF(album_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MONTH), 1, NULL)) AS month
			FROM " . DB::getTable('albums') . " WHERE album_creation_ip='" . G\get_client_ip() . "' AND album_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MONTH)"
            );
        } catch (Exception $e) {
        } // Silence

        $is_flood = false;
        $flood_by = '';
        foreach (['minute', 'hour', 'day', 'week', 'month'] as $v) {
            if ($flood_limit[$v] > 0 and $flood_db[$v] >= $flood_limit[$v]) {
                $flood_by = $v;
                $is_flood = true;
                break;
            }
        }
        if ($is_flood) {
            if (!isset($_SESSION['flood_albums_notify'], $_SESSION['flood_albums_notify'][$flood_by])) {
                try {
                    $message_report = '<html><body>' . "\n";
                    $message_report .= strtr('Flooding IP <a href="' . G\get_base_url('search/images/?q=ip:%ip') . '">%ip</a>', ['%ip' => G\get_client_ip()]) . '<br>';
                    $message_report .= 'User <a href="' . $logged_user['url'] . '">' . $logged_user['name'] . '</a><br>';
                    $message_report .= '<br>';
                    $message_report .= '<b>Albums per time period</b>' . "<br>";
                    $message_report .= 'Minute: ' . $flood_db['minute'] . "<br>";
                    $message_report .= 'Hour: ' . $flood_db['hour'] . "<br>";
                    $message_report .= 'Week: ' . $flood_db['day'] . "<br>";
                    $message_report .= 'Month: ' . $flood_db['week'] . "<br>";
                    $message_report .= '</body></html>';
                    send_mail(getSetting('email_incoming_email'), 'Flood report user ID ' . $logged_user['id'], $message_report);
                    $_SESSION['flood_albums_notify'][$flood_by] = true;
                } catch (Exception $e) {
                } // Silence
            }
            return ['flood' => true, 'limit' => $flood_limit[$flood_by], 'count' => $flood_db[$flood_by], 'by' => $flood_by];
        }

        return false;
    }
}

class AlbumException extends Exception
{
}
