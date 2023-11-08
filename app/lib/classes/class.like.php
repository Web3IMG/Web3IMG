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

class Like
{
    public static $table_fields = [
        'user_id',
        'date',
        'date_gmt',
        'content_id',
        'content_type',
        'content_user_id',
        'ip',
    ];
    
    public static function insert($args=[])
    {
        self::validateInput($args);
        if (empty($args['ip'])) {
            $args['ip'] = G\get_client_ip();
        }
        $args = array_merge($args, [
            'date'		=> G\datetime(),
            'date_gmt'	=> G\datetimegmt(),
        ]);
        $type = $args['content_type']; // image | album
        $table = $type . 's';
        $content_db = DB::get($table, ['id' => $args['content_id']])[0];
        if (!$content_db) {
            throw new Exception('invalid');
        }
        if ($table == 'images' && !$content_db['image_is_approved']) {
            throw new Exception('content is not approved');
        }
        $args['content_user_id'] = $content_db[$type . '_user_id'];
        ksort($args);
        $db_table_fields = [];
        asort(static::$table_fields);
        foreach (static::$table_fields as $k) {
            $db_table_fields[] = 'like_' . $k;
        }
        
        $db_insert_values = [];
        foreach ($args as $k => $v) {
            $value = is_null($v) ? "NULL" : "'$v'";
            $db_insert_values[] = $value . " as $k";
        }
        
        $db = DB::getInstance();
        $insert_query = "INSERT INTO ".DB::getTable('likes')." (".implode(', ', $db_table_fields).") SELECT * FROM (SELECT ".implode(', ', $db_insert_values).") AS tmp WHERE NOT EXISTS (SELECT * FROM ".DB::getTable('likes')." WHERE like_user_id = :user_id AND like_content_id = :content_id AND like_content_type = :content_type) LIMIT 1;";
        $db->query($insert_query);
        foreach (['user_id', 'content_id', 'content_type'] as $k) {
            $db->bind(':' . $k, $args[$k]);
        }
        $exec = $db->exec();
        $like_id = $db->lastInsertId();
        if ($exec) {
            // Sub-statemens
            $tables = DB::getTables();
            // Assumes only registered user likes
            $sql_tpl = [
                'UPDATE `%table_'.$table.'` SET '.$type.'_likes = '.$type.'_likes + 1 WHERE '.$type.'_id = %'.$type.'_id;',
                'UPDATE `%table_users` SET user_liked = user_liked + 1 WHERE user_id = %like_user_id;'
            ];
            // Autolike off + notifications only when other likes your content
            if ((isset($args['user_id']) && isset($args['content_user_id'])) && $args['user_id'] !== $args['content_user_id']) {
                $sql_tpl[] = 'UPDATE `%table_users` SET user_likes = user_likes + 1 WHERE user_id = %content_user_id;';
                // Insert notification
                Notification::insert([
                    'table'				=> 'likes',
                    'content_type'		=> $type,
                    'user_id'			=> $args['content_user_id'],
                    'trigger_user_id'	=> $args['user_id'],
                    'type_id'			=> $like_id,
                ]);
            }
            $sql_tpl = implode("\n", $sql_tpl);
            $sql = strtr($sql_tpl, [
                '%table_images'		=> $tables['images'],
                '%table_albums'		=> $tables['albums'],
                '%table_users'		=> $tables['users'],
                '%image_id'			=> $args['content_id'],
                '%album_id'			=> $args['content_id'],
                '%like_user_id'		=> $args['user_id'],
                '%content_user_id'	=> $args['content_user_id'],
            ]);
            DB::queryExec($sql);
            Stat::track([
                'action'		=> 'insert',
                'table'			=> 'likes',
                'content_type'	=> $type,
                'value'			=> '+1',
                'date_gmt'		=> $args['date_gmt']
            ]);
            return [
                'id'	=> $args['content_id'],
                'type'	=> $args['content_type'],
                'likes'	=> self::getContentLikesCount($args),
            ];
        } else {
            return false;
        }
    }
    
    public static function delete($args=[])
    {
        if (!is_array($args)) {
            $args = ['id' => $args['id']];
        }
        $type = $args['content_type']; // image | album
        $table = $type . 's';
        $like = self::getSingle($args);
        $delete = DB::delete('likes', $args);
        if ($delete) {
            // Get liked content
            $content = DB::get($table, ['id' => $args['content_id']])[0];
            // Track like
            Stat::track([
                'action'	=> 'delete',
                'table'		=> 'likes',
                'content_type'	=> $type,
                'value'		=> '-1',
                'date_gmt'	=> $like['date_gmt']
            ]);
            // Remove notifications related to this like
            Notification::delete([
                'table'			=> 'likes',
                'user_id'		=> $content[$type . '_user_id'],
                'type_id'		=> $like['id'],
            ]);
            // Update image likes, user's liked and user's likes
            $sql_tpl =
                'UPDATE `%table_'.$table.'` SET '.$type.'_likes = '.$type.'_likes - 1 WHERE '.$type.'_id = %content_id AND '.$type.'_likes > 0;' . "\n" .
                'UPDATE `%table_users` SET user_liked = user_liked - 1 WHERE user_id = %like_user_id AND user_liked > 0;';
            
            if (isset($content['image_user_id']) && $args['user_id'] !== $content['image_user_id']) {
                $sql_tpl .= "\n" . 'UPDATE `%table_users` SET user_likes = user_likes - 1 WHERE user_id = %content_user_id AND user_likes > 0;';
            }
            
            $sql = strtr($sql_tpl, [
                '%table_images'			=> DB::getTable('images'),
                '%table_albums'			=> DB::getTable('albums'),
                '%table_users'			=> DB::getTable('users'),
                '%table_notifications'	=> DB::getTable('notifications'),
                '%content_id' 			=> $args['content_id'] ?? '',
                '%content_type'			=> $args['content_type'] ?? '',
                '%content_user_id' 		=> $content[$type . '_user_id'] ?? '',
                '%like_id'				=> $like['id'],
                '%like_user_id'			=> $args['user_id'] ?? ''
            ]);
            
            DB::queryExec($sql);
            
            return [
                'id'	=> $args['content_id'],
                'type'	=> $args['content_type'],
                'likes'	=> self::getContentLikesCount($args),
            ];
        } else {
            return false;
        }
    }
    
    public static function getContentLikesCount($args=[])
    {
        self::validateInput($args);
        $type = $args['content_type'];
        $table = $type . 's';
        return DB::get($table, ['id' => $args['content_id']])[0][$type . '_likes'];
    }
    
    // Get a single content like
    public static function getSingle($args=[])
    {
        $like = self::get($args, null, 1);
        return $like ?? null;
    }
    
    // Get all content likes
    public static function getAll($args=[], $sort=[])
    {
        $likes = self::get($args, $sort, null);
        return $likes ?? null;
    }
    
    // Get core
    public static function get($args, $sort=[], $limit=null)
    {
        $get = DB::get('likes', $args, 'AND', $sort, $limit);
        return DB::formatRows($get);
    }
    
    protected static function validateInput($args=[])
    {
        if (!is_array($args)) {
            throw new LikeException('Expecting array, '.gettype($args).' given in ' . __METHOD__, 100);
        }
        if (empty($args['user_id'])) {
            throw new LikeException('Missing user_id in ' . __METHOD__, 101);
        } else {
            $user_db = User::getSingle($args['user_id'], 'id', false);
            if (!$user_db) {
                throw new LikeException('Invalid user_id in ' . __METHOD__, 102);
            }
        }
        if (empty($args['content_id'])) {
            throw new LikeException('Missing content_id in ' . __METHOD__, 103);
        }
        if (!in_array($args['content_type'], ['image', 'album'])) {
            throw new LikeException('Invalid content_type in ' . __METHOD__, 104);
        }
    }
}

class LikeException extends Exception
{
}
