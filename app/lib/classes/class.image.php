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

use DateTimeZone;
use G;
use Exception;
use Intervention\Image\ImageManagerStatic;
use LogicException;
use SEOURLify;
use Throwable;

use function G\unlinkIfExists;

class Image
{
    public static $table_chv_image = [
        'name',
        'extension',
        'album_id',
        'size',
        'width',
        'height',
        'date',
        'date_gmt',
        'nsfw',
        'user_id',
        'uploader_ip',
        'storage_mode',
        'storage_id',
        'md5',
        'source_md5',
        'original_filename',
        'original_exifdata',
        'category_id',
        'description',
        'chain',
        'thumb_size',
        'medium_size',
        'title',
        'expiration_date_gmt',
        'likes',
        'is_animated',
        'is_approved',
        'is_360',
    ];

    public static $chain_sizes = ['original', 'image', 'medium', 'thumb'];

    public static function getSingle($id, $sumview = false, $pretty = false, $requester = null)
    {
        $tables = DB::getTables();
        $query = 'SELECT * FROM ' . $tables['images'] . "\n";

        $joins = [
            'LEFT JOIN ' . $tables['storages'] . ' ON ' . $tables['images'] . '.image_storage_id = ' . $tables['storages'] . '.storage_id',
            'LEFT JOIN ' . $tables['storage_apis'] . ' ON ' . $tables['storages'] . '.storage_api_id = ' . $tables['storage_apis'] . '.storage_api_id',
            'LEFT JOIN ' . $tables['users'] . ' ON ' . $tables['images'] . '.image_user_id = ' . $tables['users'] . '.user_id',
            'LEFT JOIN ' . $tables['albums'] . ' ON ' . $tables['images'] . '.image_album_id = ' . $tables['albums'] . '.album_id'
        ];

        if ($requester) {
            if (!is_array($requester)) {
                $requester = User::getSingle($requester, 'id');
            }
            if (version_compare(Settings::get('chevereto_version_installed'), '3.7.0', '>=')) {
                $joins[] = 'LEFT JOIN ' . $tables['likes'] . ' ON ' . $tables['likes'] . '.like_content_type = "image" AND ' . $tables['images'] . '.image_id = ' . $tables['likes'] . '.like_content_id AND ' . $tables['likes'] . '.like_user_id = ' . $requester['id'];
            }
        }

        $query .=  implode("\n", $joins) . "\n";
        $query .= 'WHERE image_id=:image_id;' . "\n";

        if ($sumview) {
            $query .= 'UPDATE ' . $tables['images'] . ' SET image_views = image_views + 1 WHERE image_id=:image_id';
        }

        $db = DB::getInstance();
        $db->query($query);
        $db->bind(':image_id', $id);
        $image_db = $db->fetchSingle();
        if ($image_db) {
            if ($sumview) {
                $image_db['image_views'] += 1;
                // Track stats
                Stat::track([
                    'action'    => 'update',
                    'table'        => 'images',
                    'value'        => '+1',
                    'user_id'    => $image_db['image_user_id'],
                ]);
            }
            if ($requester) {
                $image_db['image_liked'] = (bool) $image_db['like_user_id'];
            }
            $return = $image_db;
            $return = $pretty ? self::formatArray($return) : $return;

            if (!isset($return['file_resource'])) {
                $return['file_resource'] =  self::getSrcTargetSingle($image_db, true);
            }
            return $return;
        } else {
            return $image_db;
        }
    }

    public static function getMultiple($ids, $pretty = false)
    {
        if (!is_array($ids)) {
            throw new ImageException('Expecting $ids array in Image::get_multiple', 100);
        }
        if (count($ids) == 0) {
            throw new ImageException('Null $ids provided in Image::get_multiple', 100);
        }

        $tables = DB::getTables();
        $query = 'SELECT * FROM ' . $tables['images'] . "\n";

        $joins = array(
            'LEFT JOIN ' . $tables['users'] . ' ON ' . $tables['images'] . '.image_user_id = ' . $tables['users'] . '.user_id',
            'LEFT JOIN ' . $tables['albums'] . ' ON ' . $tables['images'] . '.image_album_id = ' . $tables['albums'] . '.album_id'
        );

        $query .=  implode("\n", $joins) . "\n";
        $query .= 'WHERE image_id IN (' . join(',', $ids) . ')' . "\n";

        $db = DB::getInstance();
        $db->query($query);
        $images_db = $db->fetchAll();
        if ($images_db) {
            foreach ($images_db as $k => $v) {
                $images_db[$k] = array_merge($v, self::getSrcTargetSingle($v, true)); // todo
            }
        }
        if ($pretty) {
            $return = [];
            foreach ($images_db as $k => $v) {
                $return[] = self::formatArray($v);
            }
            return $return;
        }
        return $images_db;
    }

    public static function getAlbumSlice($image_id, $album_id = null, $padding = 2)
    {
        $tables = DB::getTables();

        if (!isset($image_id)) {
            throw new ImageException("Image id can't be NULL", 100);
        }

        if (!isset($album_id)) {
            $db = DB::getInstance();
            $db->query('SELECT image_album_id FROM ' . $tables['images'] . ' WHERE image_id=:image_id');
            $db->bind(':image_id', $image_id);
            $image_album_db = $db->fetchSingle();
            $album_id = $image_album_db['image_album_id'];

            if (!isset($album_id)) {
                return;
            }
        }

        if (!is_numeric($padding)) {
            $padding = 2;
        }

        $prevs = new Listing;
        $prevs->setType('images');
        $prevs->setLimit(($padding * 2) + 1);
        $prevs->setSortType('date');
        $prevs->setSortOrder('desc');
        $prevs->setRequester(Login::getUser());
        $prevs->setWhere('WHERE image_album_id=' . $album_id . ' AND image_id <= ' . $image_id);
        $prevs->exec();

        $nexts = new Listing;
        $nexts->setType('images');
        $nexts->setLimit($padding * 2);
        $nexts->setSortType('date');
        $nexts->setSortOrder('asc');
        $nexts->setRequester(Login::getUser());
        $nexts->setWhere('WHERE image_album_id=' . $album_id . ' AND image_id > ' . $image_id);
        $nexts->exec();

        if (is_array($prevs->output)) {
            $prevs->output = array_reverse($prevs->output);
        }
        $list = array_merge($prevs->output, $nexts->output);

        $album_offset = array(
            'top' => $prevs->count - 1,
            'bottom' => $nexts->count
        );

        $album_chop_count = count($list);
        $album_iteration_times = $album_chop_count - ($padding * 2 + 1);

        if ($album_chop_count > ($padding * 2 + 1)) {
            if ($album_offset['top'] > $padding && $album_offset['bottom'] > $padding) {
                // Cut on top
                for ($i = 0; $i < $album_offset['top'] - $padding; $i++) {
                    unset($list[$i]);
                }
                // Cut on bottom
                for ($i = 1; $i <= $album_offset['bottom'] - $padding; $i++) {
                    unset($list[$album_chop_count - $i]);
                }
            } elseif ($album_offset['top'] <= $padding) {
                // Cut bottom
                for ($i = 0; $i < $album_iteration_times; $i++) {
                    unset($list[$album_chop_count - 1 - $i]);
                }
            } elseif ($album_offset['bottom'] <= $padding) {
                // Cut top
                for ($i = 0; $i < $album_iteration_times; $i++) {
                    unset($list[$i]);
                }
            }
            $list = array_values($list);
        }

        $images = [];
        foreach ($list as $k => $v) {
            $format = self::formatArray($v);
            $images[$format['id']] = $format;
        }

        if (is_array($prevs->output) && $prevs->count > 1) {
            $prevLastKey = $prevs->count - 2;
            $prevLastId = $prevs->output[$prevLastKey]['image_id'];
            $slice['prev'] =  $images[$prevLastId];
        }

        if ($nexts->output) {
            $slice['next'] = $images[$nexts->output[0]['image_id']];
        }

        $slice['images'] = $images;

        return $slice;
    }

    public static function getSrcTargetSingle($filearray, $prefix = true)
    {
        $prefix = $prefix ? 'image_' : null;
        $folder = CHV_PATH_IMAGES;
        $pretty = !isset($filearray['image_id']);
        $mode = $filearray[$prefix . 'storage_mode'];

        $chain_mask = str_split((string) str_pad(decbin($filearray[$pretty ? 'chain' : 'image_chain']), 4, '0', STR_PAD_LEFT));

        $chain_to_sufix = [
            //'original'	=> '.original.',
            'image'        => '.',
            'thumb'        => '.th.',
            'medium'    => '.md.'
        ];

        if ($pretty) {
            $type = isset($filearray['storage']['id']) ? 'url' : 'path';
        } else {
            $type = isset($filearray['storage_id']) ? 'url' : 'path';
        }

        if ($type == 'url') { // URL resource folder
            $folder = G\add_ending_slash($pretty ? $filearray['storage']['url'] : $filearray['storage_url']);
        }

        switch ($mode) {
            case 'datefolder':
                $datetime = $filearray[$prefix . 'date'];
                $datefolder = preg_replace('/(.*)(\s.*)/', '$1', str_replace('-', '/', $datetime));
                $folder .= G\add_ending_slash($datefolder); // Y/m/d/
                break;
            case 'old':
                $folder .= 'old/';
                break;
            case 'direct':
                // use direct $folder
                break;
            case 'path':
                $folder = G\add_ending_slash($filearray['path']);
                break;
        }

        $targets = [
            'type' => $type,
            'chain'    => [
                //'original'	=> NULL,
                'image'        => null,
                'thumb'        => null,
                'medium'    => null
            ]
        ];

        foreach ($targets['chain'] as $k => $v) {
            $targets['chain'][$k] = $folder . $filearray[$prefix . 'name'] . $chain_to_sufix[$k] . $filearray[$prefix . 'extension'];
        }

        if ($type == 'path') {
            foreach ($targets['chain'] as $k => $v) {
                if (!file_exists($v)) {
                    unset($targets['chain'][$k]);
                };
            }
        } else {
            foreach ($chain_mask as $k => $v) {
                if (!(bool) $v) {
                    unset($targets['chain'][self::$chain_sizes[$k]]);
                }
            }
        }


        return $targets;
    }

    public static function getUrlViewer($id_encoded, $title = null)
    {
        if ($title == null) {
            $seo = null;
        } else {
            $seo = SEOURLify::filter($title);
        }
        $url = $seo ? ($seo . '.' . $id_encoded) : $id_encoded;
        return G\get_base_url(getSetting('route_image') . '/' . $url);
    }

    public static function getAvailableExpirations()
    {
        $string = _s('After %n %t');
        $translate = [ // Just for gettext parsing
            'minute' => _n('minute', 'minutes', 1),
            'hour'    => _n('hour', 'hours', 1),
            'day'        => _n('day', 'days', 1),
            'week'    => _n('week', 'weeks', 1),
            'month'    => _n('month', 'months', 1),
            'year'    => _n('year', 'years', 1),
        ];
        $return = [
            null => _s("Don't autodelete"),
        ];
        $table = [
            ['minute', 5],
            ['minute', 15],
            ['minute', 30],
            ['hour', 1],
            ['hour', 3],
            ['hour', 6],
            ['hour', 12],
            ['day', 1],
            ['day', 2],
            ['day', 3],
            ['day', 4],
            ['day', 5],
            ['day', 6],
            ['week', 1],
            ['week', 2],
            ['week', 3],
            ['month', 1],
            ['month', 2],
            ['month', 3],
            ['month', 4],
            ['month', 5],
            ['month', 6],
            ['year', 1],
        ];
        foreach ($table as $expire) {
            $unit = $expire[0];
            $interval_spec = 'P' . (in_array($unit, ['second', 'minute', 'hour']) ? 'T' : null) . $expire[1] . strtoupper($unit[0]);
            $return[$interval_spec] = strtr($string, ['%n' => $expire[1], '%t' => _n($unit, $unit . 's', $expire[1])]);
        }
        return $return;
    }

    public static function watermarkFromDb() {
        $file = CHV_PATH_CONTENT_IMAGES_SYSTEM . getSetting('watermark_image');
        $assetsDb = DB::get('assets', ['key' => 'watermark_image'], 'AND', [], 1);
        if(
            file_exists($file)
            && md5_file($file) != $assetsDb['asset_md5']
            && !G\starts_with('default/', getSetting('watermark_image'))
        ) {
            unlinkIfExists($file);
        }
        if(!file_exists($file)) {
            $fh = @fopen($file, 'w');
            if (!$fh or fwrite($fh, $assetsDb['asset_blob']) === false) {
                $st = false;
            } else {
                $st = true;
            }
            @fclose($fh);
            if (!$st) {
                throw new LogicException(_s("Can't open %s for writing", $file), 100);
            }
        }
        
    }

    public static function watermark($image_path, $options = [])
    {
        $options = array_merge([
            'ratio'        => getSetting('watermark_percentage') / 100,
            'position'    => explode(' ', getSetting('watermark_position')),
            'file'        => CHV_PATH_CONTENT_IMAGES_SYSTEM . getSetting('watermark_image')
        ], $options);
        self::watermarkFromDb();
        if (!is_readable($options['file'])) {
            throw new Exception("Can't read watermark file at " . $options['file'], 100);
        }
        $image = ImageManagerStatic::make($image_path);
        $options['ratio'] = min(1, (!is_numeric($options['ratio']) ? 0.01 : max(0.01, $options['ratio'])));
        if (!in_array($options['position'][0], ['left', 'center', 'right'])) {
            $options['position'][0] = 'right';
        }
        if (!in_array($options['position'][1], ['top', 'center', 'bottom'])) {
            $options['position'][0] = 'bottom';
        }
        $watermarkPos = [];
        if($options['position'][1] !== 'center') {
            $watermarkPos[] = $options['position'][1];
        }
        if($options['position'][0] !== 'center') {
            $watermarkPos[] = $options['position'][0];
        }
        $watermark = ImageManagerStatic::make($options['file']);
        $watermark_area = $image->getWidth() * $image->getHeight() * $options['ratio'];
        $watermark_image_ratio = $watermark->getWidth()/$watermark->getHeight();
        $watermark_new_height = round(sqrt($watermark_area / $watermark_image_ratio), 0);
        if ($watermark_new_height > $image->getHeight()) {
            $watermark_new_height = $image->getHeight();
        }
        if (getSetting('watermark_margin') and $options['position'][1] !== 'center' and $watermark_new_height + getSetting('watermark_margin') > $image->getHeight()) {
            $watermark_new_height -= $watermark_new_height + 2 * getSetting('watermark_margin') - $image->getHeight();
        }
        $watermark_new_width  = round($watermark_image_ratio * $watermark_new_height, 0);
        if ($watermark_new_width > $image->getWidth()) {
            $watermark_new_width = $image->getWidth();
        }
        if (getSetting('watermark_margin') and $options['position'][0] !== 'center' and $watermark_new_width + getSetting('watermark_margin') > $image->getWidth()) {
            $watermark_new_width -= $watermark_new_width + 2 * getSetting('watermark_margin') - $image->getWidth();
            $watermark_new_height = $watermark_new_width / $watermark_image_ratio;
        }
        if ($watermark_new_width !== $watermark->getWidth()) {
            $watermark->resize($watermark_new_width, null, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
        }      
        $watermark->opacity(getSetting('watermark_opacity'));
        $image
            ->insert(
                $watermark,
                $watermarkPos === []
                    ? 'center'
                    : implode('-', $watermarkPos),
                getSetting('watermark_margin'),
                getSetting('watermark_margin')
            )
            ->save();

        return true;
    }

    public static function upload($source, $destination, $filename = null, $options = [], $storage_id = null, $guestSessionHandle = true)
    {
        $default_options = Upload::getDefaultOptions();
        $options = array_merge($default_options, $options);
        if (!is_null($filename) && !$options['filenaming']) {
            $options['filenaming'] = 'original';
        }
        $upload = new Upload();
        $upload->setSource($source);
        $upload->setDestination($destination);
        $upload->setOptions($options);
        if (!is_null($storage_id)) {
            $upload->setStorageId($storage_id);
        }
        if (!is_null($filename)) {
            $upload->setFilename($filename);
        }
        if ($guestSessionHandle == false) {
            $upload->detectFlood = false;
        }
        $upload->exec();
        $return = [
            'uploaded' => $upload->uploaded,
            'source' => $upload->source,
        ];
        if (isset($upload->moderation)) {
            $return['moderation'] = $upload->moderation;
        }

        return $return;
    }

    // Mostly for people uploading two times the same image to test or just bug you
    // $mixed => $_FILES or md5 string
    public static function isDuplicatedUpload($mixed, $time_frame = 'P1D')
    {
        if (is_array($mixed) && isset($mixed['tmp_name'])) {
            $filename = $mixed['tmp_name'];
            if (stream_resolve_include_path($filename) == false) {
                throw new Exception("Concurrency: $filename is gone", 666);
            }
            $md5_file = @md5_file($filename);
        } else {
            $filename = $mixed;
            $md5_file = $filename;
        }
        if (!isset($md5_file)) {
            throw new Exception('Unable to process md5_file', 100);
        }
        $db = DB::getInstance();
        $db->query('SELECT * FROM ' . DB::getTable('images') . ' WHERE (image_md5=:md5 OR image_source_md5=:md5) AND image_uploader_ip=:ip AND image_date_gmt > :date_gmt');
        $db->bind(':md5', $md5_file);
        $db->bind(':ip', G\get_client_ip());
        $db->bind(':date_gmt', G\datetime_sub(G\datetimegmt(), $time_frame));
        $db->exec();
        return $db->fetchColumn();
    }

    public static function uploadToWebsite($source, $user = null, $params = [], $guestSessionHandle = true, $ip = null)
    {
        $params['use_file_date'] = $params['use_file_date'] ?? false;
        G\nullify_string($params['album_id']);
        try {
            if (isset($user)) {
                if (is_array($user) == false) {
                    if (is_numeric($user)) { // ...$param unpack doesn't preserve type and turns everything into string
                        $user = User::getSingle($user);
                    } else {
                        $user = User::getSingle($user, 'username');
                    }
                }
                if ($user !== null && getSetting('upload_max_filesize_mb_bak') !== null && getSetting('upload_max_filesize_mb') == getSetting('upload_max_filesize_mb_guest')) {
                    Settings::setValue('upload_max_filesize_mb', getSetting('upload_max_filesize_mb_bak'));
                }
            }

            $do_dupe_check = !getSetting('enable_duplicate_uploads') && !($user['is_admin'] ?? false);
            if ($do_dupe_check && self::isDuplicatedUpload($source)) {
                throw new Exception(_s('Duplicated upload'), 101);
            }
            $get_active_storages = Storage::getAll(['is_active' => 1]);
            if (!empty($get_active_storages)) {
                $sequential_storage = count($get_active_storages) > 1;
                if ($sequential_storage) {
                    $storage_id = null;
                    $last_used_storage = getSetting('last_used_storage');
                } else {
                    $storage_id = $get_active_storages[0]['id'];
                }
                $active_storages = [];
                for ($i = 0; $i < count($get_active_storages); $i++) {
                    $storage_pointer = $get_active_storages[$i]['id']; // id
                    $active_storages[$storage_pointer] = $get_active_storages[$i]; // key fixed array

                    if (is_null($storage_id) && $last_used_storage == $storage_pointer) {
                        $storage_id = $get_active_storages[$i + 1]['id'] ?: $get_active_storages[0]['id'];
                    }
                }
                $storage = $active_storages[$storage_id];
            }
            $storage_mode = getSetting('upload_storage_mode');
            switch ($storage_mode) {
                case 'direct':
                    $upload_path = CHV_PATH_IMAGES;
                    break;
                case 'datefolder':
                    $stockDate = G\datetime();
                    $stockDateGmt = G\datetimegmt();
                    if (is_array($source) && $params['use_file_date'] && $source['type'] === 'image/jpeg') {
                        try {
                            $exifSource = \exif_read_data($source['tmp_name']);
                        } catch(Throwable $e) {
                        }
                        if (isset($exifSource['DateTime'])) {
                            $stockDateGmt = date_create_from_format("Y:m:d H:i:s", $exifSource['DateTime'], new DateTimeZone('UTC'));
                            $stockDateGmt = $stockDateGmt->format('Y-m-d H:i:s');
                            $stockDate = G\datetimegmt_convert_tz($stockDateGmt, getSetting('default_timezone'));
                        }
                    }
                    $datefolder_stock = [
                        'date' => $stockDate,
                        'date_gmt' => $stockDateGmt,
                    ];
                    $datefolder = date('Y/m/d/', strtotime($datefolder_stock['date']));
                    $upload_path = CHV_PATH_IMAGES . $datefolder;
                    break;
            }
            $filenaming = getSetting('upload_filenaming');
            if ($filenaming !== 'id' && in_array($params['privacy'] ?? '', ['password', 'private', 'private_but_link'])) {
                $filenaming = 'random';
            }
            $upload_options = [
                'max_size' => G\get_bytes(getSetting('upload_max_filesize_mb') . ' MB'),
                'exif' => (getSetting('upload_image_exif_user_setting') && $user) ? $user['image_keep_exif'] : getSetting('upload_image_exif'),
            ];
            if ($filenaming == 'id') {
                try {
                    $dummy = [
                        'name' => '',
                        'extension' => '',
                        'size' => 0,
                        'width' => 0,
                        'height' => 0,
                        'date' => '0000-01-01 00:00:00',
                        'date_gmt' => '0000-01-01 00:00:00',
                        'nsfw' => 0,
                        'uploader_ip' => '',
                        'md5' => '',
                        'original_filename' => '',
                        'chain' => 0,
                        'thumb_size' => 0,
                        'medium_size' => 0,
                    ];
                    $dummy_insert = DB::insert('images', $dummy);
                    DB::delete('images', ['id' => $dummy_insert]);
                    $target_id = $dummy_insert;
                } catch (Throwable $e) {
                    $filenaming = 'original';
                }
            }
            $upload_options['filenaming'] = $filenaming;
            $upload_options['allowed_formats'] = self::getEnabledImageFormats();
            $image_upload = self::upload($source, $upload_path, $filenaming == 'id' ? encodeID($target_id) : null, $upload_options, $storage_id ?? null, $guestSessionHandle);
            $chain_mask = [0, 1, 0, 1]; // original image medium thumb
            if ($do_dupe_check && self::isDuplicatedUpload($image_upload['uploaded']['fileinfo']['md5'])) {
                throw new Exception(_s('Duplicated upload'), 102);
            }
            $image_ratio = $image_upload['uploaded']['fileinfo']['width'] / $image_upload['uploaded']['fileinfo']['height'];
            $must_resize = false;
            $image_max_size_cfg = [
                'width'        => Settings::get('upload_max_image_width') ?: $image_upload['uploaded']['fileinfo']['width'],
                'height'    => Settings::get('upload_max_image_height') ?: $image_upload['uploaded']['fileinfo']['height'],
            ];
            if ($image_max_size_cfg['width'] < $image_upload['uploaded']['fileinfo']['width'] || $image_max_size_cfg['height'] < $image_upload['uploaded']['fileinfo']['height']) {
                $image_max = $image_max_size_cfg;
                $image_max['width'] = (int) round($image_max_size_cfg['height'] * $image_ratio);
                $image_max['height'] =  (int) round($image_max_size_cfg['width'] / $image_ratio);
                if ($image_max['height'] > $image_max_size_cfg['height']) {
                    $image_max['height'] = $image_max_size_cfg['height'];
                    $image_max['width'] =  (int) round($image_max['height'] * $image_ratio);
                }
                if ($image_max['width'] > $image_max_size_cfg['width']) {
                    $image_max['width'] = $image_max_size_cfg['width'];
                    $image_max['height'] =  (int) round($image_max['width'] / $image_ratio);
                }
                if ($image_max != ['width' => $image_upload['uploaded']['fileinfo']['width'], 'height' => $image_max_size_cfg['height']]) { // loose just in case..
                    $must_resize = true;
                    $params['width'] = $image_max['width'];
                    $params['height'] = $image_max['height'];
                }
            }
            foreach (['width', 'height'] as $k) {
                if (!isset($params[$k]) || !is_numeric($params[$k])) {
                    continue;
                }
                if ($params[$k] != $image_upload['uploaded']['fileinfo'][$k]) {
                    $must_resize = true;
                }
            }
            $is_360 = (bool) $image_upload['uploaded']['fileinfo']['is_360'];
            if (G\is_animated_image($image_upload['uploaded']['file'])) {
                $must_resize = false;
            }
            if ($must_resize) {
                $source_md5 = $image_upload['uploaded']['fileinfo']['md5'];
                if ($do_dupe_check && self::isDuplicatedUpload($source_md5)) {
                    throw new Exception(_s('Duplicated upload'), 103);
                }
                if ($image_ratio == $params['width'] / $params['height']) {
                    $image_resize_options = [
                        'width'        => $params['width'],
                        'height'    => $params['height']
                    ];
                } else {
                    $image_resize_options = ['width' => $params['width']];
                }
                $image_upload['uploaded'] = self::resize($image_upload['uploaded']['file'], dirname($image_upload['uploaded']['file']), null, $image_resize_options);
                $image_upload['uploaded']['fileinfo']['is_360'] = $is_360;
            }
            $image_thumb_options = [
                'width'        => getSetting('upload_thumb_width'),
                'height'    => getSetting('upload_thumb_height')
            ];
            $medium_size = getSetting('upload_medium_size');
            $medium_fixed_dimension = getSetting('upload_medium_fixed_dimension');
            $is_animated_image = G\is_animated_image($image_upload['uploaded']['file']);
            $image_thumb = self::resize($image_upload['uploaded']['file'], dirname($image_upload['uploaded']['file']), $image_upload['uploaded']['name'] . '.th', $image_thumb_options);
            $original_md5 = $image_upload['source']['fileinfo']['md5'];
            $watermark_enable = getSetting('watermark_enable');
            if ($watermark_enable) {
                $watermark_user = $user ? ($user['is_admin'] ? 'admin' : 'user') : 'guest';
                $watermark_enable = getSetting('watermark_enable_' . $watermark_user);
            }
            $watermark_gif = (bool) getSetting('watermark_enable_file_gif');
            $apply_watermark = $watermark_enable;
            if ($is_animated_image || $image_upload['uploaded']['fileinfo']['is_360']) {
                $apply_watermark = false;
            }
            if ($apply_watermark) {
                foreach (['width', 'height'] as $k) {
                    $min_value = getSetting('watermark_target_min_' . $k);
                    if ($min_value == 0) { // Skip on zero
                        continue;
                    }
                    $apply_watermark = $image_upload['uploaded']['fileinfo'][$k] >= $min_value;
                }
                if ($apply_watermark and $image_upload['uploaded']['fileinfo']['extension'] == 'gif' and !$watermark_gif) {
                    $apply_watermark = false;
                }
            }
            if ($apply_watermark && self::watermark($image_upload['uploaded']['file'])) {
                $image_upload['uploaded']['fileinfo'] = G\get_image_fileinfo($image_upload['uploaded']['file']); // Remake the fileinfo array, new full array file info (todo: faster!)
                $image_upload['uploaded']['fileinfo']['md5'] = $original_md5; // Preserve original MD5 for watermarked images
            }
            if ($image_upload['uploaded']['fileinfo'][$medium_fixed_dimension] > $medium_size or $is_animated_image) {
                $image_medium_options = [];
                $image_medium_options[$medium_fixed_dimension] = $medium_size;
                if ($is_animated_image) {
                    $image_medium_options['forced'] = true;
                    $image_medium_options[$medium_fixed_dimension] = min($image_medium_options[$medium_fixed_dimension], $image_upload['uploaded']['fileinfo'][$medium_fixed_dimension]);
                }
                $image_medium = self::resize($image_upload['uploaded']['file'], dirname($image_upload['uploaded']['file']), $image_upload['uploaded']['name'] . '.md', $image_medium_options);
                $chain_mask[2] = 1;
            }
            $chain_value = bindec((int) implode('', $chain_mask));
            $disk_space_needed = $image_upload['uploaded']['fileinfo']['size'];
            if(isset($image_thumb['fileinfo']['size'])) {
                $disk_space_needed += $image_thumb['fileinfo']['size'];
            }
            if(isset($image_medium['fileinfo']['size'])) {
                $disk_space_needed += $image_medium['fileinfo']['size'];
            }
            $switch_to_local =  false;
            if (isset($storage_id) and !empty($storage['capacity']) and $disk_space_needed > ($storage['capacity'] - $storage['space_used'])) {
                if (count($active_storages) > 0) { // Moar
                    $capable_storages = [];
                    foreach ($active_storages as $k => $v) {
                        if ($v['id'] == $storage_id or $disk_space_needed > ($v['capacity'] - $v['space_used'])) {
                            continue;
                        }
                        $capable_storages[] = $v['id'];
                    }
                    if (count($capable_storages) == 0) {
                        $switch_to_local = true;
                    } else {
                        $storage_id = $capable_storages[0];
                        $storage = $active_storages[$storage_id];
                    }
                } else {
                    $switch_to_local = true;
                }
                if ($switch_to_local) {
                    $storage_id = null;
                    $downstream = $image_upload['uploaded']['file'];
                    $fixed_filename = $image_upload['uploaded']['filename'];
                    $uploaded_file = G\name_unique_file($upload_path, $fixed_filename, $upload_options['filenaming']);
                    try {
                        $renamed_uploaded = rename($downstream, $uploaded_file);
                    } catch(Throwable $e) {
                        $renamed_uploaded = file_exists($uploaded_file);
                    }
                    if (!$renamed_uploaded) {
                        throw new Exception("Can't re-allocate image to local storage", 500);
                    }
                    $image_upload['uploaded'] = [
                        'file'        => $uploaded_file,
                        'filename'    => G\get_filename($uploaded_file),
                        'name'        => G\get_basename_without_extension($uploaded_file),
                        'fileinfo'    => G\get_image_fileinfo($uploaded_file)
                    ];
                    $chain_props = [
                        'thumb'        => ['suffix' => 'th'],
                        'medium'    => ['suffix' => 'md']
                    ];
                    if (!$image_medium) {
                        unset($chain_props['medium']);
                    }
                    foreach ($chain_props as $k => $v) {
                        $chain_file = G\add_ending_slash(dirname($image_upload['uploaded']['file'])) . $image_upload['uploaded']['name'] . '.' . $v['suffix'] . '.' . ${"image_$k"}['fileinfo']['extension'];
                        try {
                            $renamed_chain = rename(${"image_$k"}['file'], $chain_file);
                        } catch(Throwable $e) {
                            $renamed_chain = file_exists($chain_file);
                        }
                        if (!$renamed_chain) {
                            throw new Exception("Can't re-allocate image " . $k . " to local storage", 500);
                        }
                        ${"image_$k"} = [
                            'file'        => $chain_file,
                            'filename'    => G\get_filename($chain_file),
                            'name'        => G\get_basename_without_extension($chain_file),
                            'fileinfo'    => G\get_image_fileinfo($chain_file)
                        ];
                    }
                }
            }

            $image_insert_values = [
                'storage_mode'    => $storage_mode,
                'storage_id'    => $storage_id ?? null,
                'user_id'        => $user['id'] ?? null,
                'album_id'        => $params['album_id'] ?? null,
                'nsfw'            => $params['nsfw'] ?? null,
                'category_id'    => $params['category_id'] ?? null,
                'title'            => $params['title'] ?? null,
                'description'    => $params['description'] ?? null,
                'chain'            => $chain_value,
                'thumb_size'    => $image_thumb['fileinfo']['size'] ?? 0,
                'medium_size'    => $image_medium['fileinfo']['size'] ?? 0,
                'is_animated'    => $is_animated_image,
                'source_md5' => $source_md5 ?? null,
                'is_360' => $is_360
            ];
            // array merge changes NULL to ""
            if (isset($datefolder_stock)) {
                foreach($datefolder_stock as $k => $v) {
                    $image_insert_values[$k] = $v;
                }
            }

            if (getSetting('enable_expirable_uploads')) {
                if (!$user && getSetting('auto_delete_guest_uploads') !== null) {
                    $params['expiration'] = getSetting('auto_delete_guest_uploads');
                }
                if (!isset($params['expiration']) && isset($user['image_expiration'])) {
                    $params['expiration'] = $user['image_expiration'];
                }
                try {
                    if (!empty($params['expiration']) && array_key_exists($params['expiration'], self::getAvailableExpirations())) {
                        $params['expiration_date_gmt'] = G\datetime_add(G\datetimegmt(), strtoupper($params['expiration']));
                    }
                    if (!empty($params['expiration_date_gmt'])) {
                        $expirable_diff = G\datetime_diff(G\datetimegmt(), $params['expiration_date_gmt'], 'm');
                        $image_insert_values['expiration_date_gmt'] = $expirable_diff < 5 ? G\datetime_modify(G\datetimegmt(), '+5 minutes') : $params['expiration_date_gmt'];
                    }
                } catch (Exception $e) {
                } // Silence
            }
            if (isset($storage_id)) {
                foreach (self::$chain_sizes as $k => $v) {
                    if (!(bool) $chain_mask[$k]) {
                        continue;
                    }
                    switch ($v) {
                        case 'image':
                            $prop = $image_upload['uploaded'];
                            break;
                        default:
                            $prop = ${"image_$v"};
                            break;
                    }
                    $toStorage[$v] = [
                        'file' => $prop['file'],
                        'filename' => $prop['filename'],
                        'mime' => $prop['fileinfo']['mime'],
                    ];
                }
                Storage::uploadFiles($toStorage, $storage, [
                    'keyprefix' => $storage_mode == 'datefolder' ? $datefolder : null
                ]);
            }
            if (!array_key_exists('title', $params)) {
                $title_from_exif = null;
                if(isset($image_upload['source']['image_exif']['ImageDescription'])) {
                    $title_from_exif = trim($image_upload['source']['image_exif']['ImageDescription']);
                }
                if ($title_from_exif) {
                    $title_from_exif = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $title_from_exif);
                    $image_title = $title_from_exif;
                } else {
                    $title_from_filename = preg_replace('/[-_\s]+/', ' ', trim($image_upload['source']['name']));
                    $image_title = $title_from_filename;
                }
                $image_insert_values['title'] = $image_title;
            }
            if ($filenaming == 'id' && isset($target_id)) { // Insert as a reserved ID
                $image_insert_values['id'] = $target_id;
            }
            $image_insert_values['title'] = mb_substr($image_insert_values['title'], 0, 100, 'UTF-8');
            if (isset($user) && isset($image_insert_values['album_id'])) {
                $album = Album::getSingle($image_insert_values['album_id']);
                if ($album['user']['id'] != $user['id']) {
                    unset($image_insert_values['album_id'], $album);
                }
            }
            if (isset($ip)) {
                $image_insert_values['uploader_ip'] = $ip;
            }
            $uploaded_id = self::insert($image_upload, $image_insert_values);
            if ($filenaming == 'id') {
                unset($reserved_id);
            }
            if (isset($toStorage)) {
                foreach ($toStorage as $k => $v) {
                    unlinkIfExists($v['file']); // Remove the source image
                }
            }
            if (in_array($params['privacy'] ?? '', ['private', 'private_but_link'])) {
                if (is_null($album) or !in_array($album['privacy'], ['private', 'private_but_link'])) {
                    $upload_timestamp = $params['timestamp'] ?: time();
                    $session_handle = 'upload_' . $upload_timestamp;
                    session_start();
                    if (isset($_SESSION[$session_handle])) {
                        $album = Album::getSingle(decodeID($_SESSION[$session_handle]));
                    } else {
                        $album = null;
                    }
                    if (!empty($album) or !in_array($album['privacy'], ['private', 'private_but_link'])) {
                        $inserted_album = Album::insert([
                            'name' => _s('Private upload') . ' ' . G\datetime('Y-m-d'),
                            'user_id' => $user['id'],
                            'privacy' => $params['privacy']
                        ]);
                        $_SESSION[$session_handle] = encodeID($inserted_album);
                        $image_insert_values['album_id'] = $inserted_album;
                    } else {
                        $image_insert_values['album_id'] = $album['id'];
                    }
                }
            }

            if (isset($image_insert_values['album_id'])) {
                Album::addImage($image_insert_values['album_id'], $uploaded_id);
            }

            if ($user) {
                DB::increment('users', ['image_count' => '+1'], ['id' => $user['id']]);
            } elseif ($guestSessionHandle == true) {
                session_start();
                if (!isset($_SESSION['guest_images'])) {
                    $_SESSION['guest_images'] = [];
                }
                $_SESSION['guest_images'][] = $uploaded_id;
            }

            if ($switch_to_local) {
                $image_viewer = self::getUrlViewer(encodeID($uploaded_id));
                // NOTIFY > External storage switched to local storage
                system_notification_email(['subject' => 'Upload switched to local storage', 'message' => strtr('System has switched to local storage due to not enough disk capacity (%c) in the external storage server(s). The image %s has been allocated to local storage.', ['%c' => $disk_space_needed . ' B', '%s' => '<a href="' . $image_viewer . '">' . $image_viewer . '</a>'])]);
            }

            return $uploaded_id;
        } catch (Exception $e) {
            if(isset($image_upload['uploaded'], $image_upload['uploaded']['file'])) {
                unlinkIfExists($image_upload['uploaded']['file']);
            }
            if(isset($image_medium['file'])) {
                unlinkIfExists($image_medium['file']);
            }
            if(isset($image_thumb['file'])) {
                unlinkIfExists($image_thumb['file']);
            }
            throw $e;
        }
    }

    public static function getEnabledImageFormats()
    {
        $formats = explode(',', Settings::get('upload_enabled_image_formats'));
        if (in_array('jpg', $formats)) {
            $formats[] = 'jpeg';
        }
        return $formats;
    }

    public static function resize($source, $destination, $filename = null, $options = [])
    {
        $resize = new Imageresize;
        $resize->setSource($source);
        $resize->setDestination($destination);
        if ($filename) {
            $resize->setFilename($filename);
        }
        $resize->setOptions($options);
        if (isset($options['width'])) {
            $resize->set_width($options['width']);
        }
        if (isset($options['height'])) {
            $resize->set_height($options['height']);
        }
        if (isset($resize->width, $options['height']) && $resize->width == $options['height']) {
            $resize->set_fixed();
        }
        if (isset($options['forced']) && $options['forced'] === true) {
            $resize->setOption('forced', true);
        }
        $resize->exec();
        return $resize->resized;
    }

    public static function insert($image_upload, $values = [])
    {
        $table_chv_image = self::$table_chv_image;
        foreach ($table_chv_image as $k => $v) {
            $table_chv_image[$k] = 'image_' . $v;
        }

        if (empty($values['uploader_ip'])) {
            $values['uploader_ip'] = G\get_client_ip();
        }

        // Remove eternal/useless Exif MakerNote
        if (isset($image_upload['source']['image_exif']['MakerNote'])) {
            unset($image_upload['source']['image_exif']['MakerNote']);
        }

        $original_exifdata = $image_upload['source']['image_exif'] ? json_encode(G\array_utf8encode($image_upload['source']['image_exif'])) : null;
        
        $values['nsfw'] = in_array(strval($values['nsfw']), ['0', '1']) ? $values['nsfw'] : 0;
        if (Settings::get('moderatecontent') && $values['nsfw'] == 0 && Settings::get('moderatecontent_flag_nsfw')) {
            switch ($image_upload['moderation']->rating_letter) {
                case 'a':
                    $values['nsfw'] = '1';
                break;
                case 't':
                    if (Settings::get('moderatecontent_flag_nsfw') == 't') {
                        $values['nsfw'] = 1;
                    }
                break;
            }
        }

        $is360 = false;
        if(isset($image_upload['uploaded']['fileinfo']['is_360'])) {
            $is360 = (bool) $image_upload['uploaded']['fileinfo']['is_360'];
        } 
        $populate_values = [
            'uploader_ip' => $values['uploader_ip'],
            'md5' => $image_upload['uploaded']['fileinfo']['md5'],
            'original_filename' => $image_upload['source']['filename'],
            'original_exifdata' => $original_exifdata,
            'is_360' => $is360,
        ];

        if (!isset($values['date'])) {
            $populate_values = array_merge($populate_values, [
                'date' => G\datetime(),
                'date_gmt' => G\datetimegmt(),
            ]);
        }

        // Populate values with fileinfo + populate_values
        $values = array_merge($image_upload['uploaded']['fileinfo'], $populate_values, $values);

        // This doesn't work all the time...
        foreach (['title', 'description', 'category_id', 'album_id'] as $v) {
            G\nullify_string($values[$v]);
        }

        // Now use only the values accepted by the table
        foreach ($values as $k => $v) {
            if (!in_array('image_' . $k, $table_chv_image) && $k !== 'id') {
                unset($values[$k]);
            }
        }

        $values['is_approved'] = 1;
        switch (Settings::get('moderate_uploads')) {
            case 'all':
                $values['is_approved'] = 0;
            break;
            case 'guest':
                $values['is_approved'] = (int) isset($values['user_id']);
            break;
        }

        if (Settings::get('moderatecontent_auto_approve') && isset($image_upload['moderation'])) {
            $values['is_approved'] = 1;
        }

        $insert = DB::insert('images', $values);

        $disk_space_used = $values['size'] + $values['thumb_size'] + $values['medium_size'];

        // Track stats
        Stat::track([
            'action'    => 'insert',
            'table'        => 'images',
            'value'        => '+1',
            'date_gmt'    => $values['date_gmt'],
            'disk_sum'    => $disk_space_used,
        ]);

        // Update album count
        if (!is_null($values['album_id']) and $insert) {
            Album::updateImageCount($values['album_id'], 1);
        }

        return $insert;
    }

    public static function update($id, $values)
    {
        $values = G\array_filter_array($values, self::$table_chv_image, 'exclusion');

        foreach (['title', 'description', 'category_id', 'album_id'] as $v) {
            if (!array_key_exists($v, $values)) {
                continue;
            }
            G\nullify_string($values[$v]);
        }

        if (isset($values['album_id'])) {
            $image_db = self::getSingle($id, false, false);
            $old_album = $image_db['image_album_id'];
            $new_album = $values['album_id'];
            $update = DB::update('images', $values, ['id' => $id]);
            if ($update and $old_album !== $new_album) {
                if (!is_null($old_album)) { // Update the old album
                    Album::updateImageCount($old_album, 1, '-');
                }
                if (!is_null($new_album)) { // Update the new album
                    Album::updateImageCount($new_album, 1);
                }
            }
            return $update;
        } else {
            return DB::update('images', $values, ['id' => $id]);
        }
    }

    public static function delete($id, $update_user = true)
    {
        $image = self::getSingle($id, false, true);
        $disk_space_used = $image['size'] + ($image['thumb']['size'] ?? 0) + ($image['medium']['size'] ?? 0);

        if ($image['file_resource']['type'] == 'path') {
            foreach ($image['file_resource']['chain'] as $file_delete) {
                if (file_exists($file_delete) and !unlinkIfExists($file_delete)) {
                    throw new ImageException("Can't delete file", 200);
                }
            }
        } else {
            $targets = [];

            foreach ($image['file_resource']['chain'] as $k => $v) {
                $targets[$k] = [
                    'key' => preg_replace('#' . G\add_ending_slash($image['storage']['url']) . '#', '', $v),
                    'size' => $image[$k]['size'],
                ];
            }
            Storage::deleteFiles($targets, $image['storage']);
        }

        if ($update_user and isset($image['user']['id'])) {
            DB::increment('users', ['image_count' => '-1'], ['id' => $image['user']['id']]);
        }

        // Update album count
        if (isset($image['album']['id']) && $image['album']['id'] > 0) {
            Album::updateImageCount($image['album']['id'], 1, '-');
        }

        // Update if album cover
        if (isset($image['album']['cover_id']) && $image['album']['cover_id'] === $image['id']) {
            Album::populateCover($image['album']['id']);
        }

        // Track stats
        Stat::track([
            'action'    => 'delete',
            'table'        => 'images',
            'value'        => '-1',
            'date_gmt'    => $image['date_gmt'],
            'disk_sum'    => $disk_space_used,
            'likes'        => $image['likes'],
        ]);

        // Remove "liked" counter for each user who liked this image
        DB::queryExec('UPDATE ' . DB::getTable('users') . ' INNER JOIN ' . DB::getTable('likes') . ' ON user_id = like_user_id AND like_content_type = "image" AND like_content_id = ' . $image['id'] . ' SET user_liked = GREATEST(cast(user_liked AS SIGNED) - 1, 0);');

        if (isset($image['user']['id'])) {
            $autoliked = DB::get('likes', ['user_id' => $image['user']['id'], 'content_type' => 'image', 'content_id' => $image['id']])[0] ?? [];
            $likes_counter = (int) $image['likes']; // This is stored as "bigint" but PDO MySQL get it as string. Fuck my code, fuck PHP.
            if ($autoliked !== []) {
                $likes_counter -= 1;
            }
            if ($likes_counter > 0) {
                $likes_counter = 0 - $likes_counter;
            }
            // Update user "likes" counter (if needed)
            if ($likes_counter !== 0) {
                DB::increment('users', ['likes' => $likes_counter], ['id' => $image['user']['id']]);
            }
            // Remove notifications related to this image (owner notifications)
            Notification::delete([
                'table'        => 'images',
                'image_id'    => $image['id'],
                'user_id'    => $image['user']['id'],
            ]);
        }

        // Remove image likes
        DB::delete('likes', ['content_type' => 'image', 'content_id' => $image['id']]);

        // Log image deletion
        DB::insert('deletions', [
            'date_gmt'            => G\datetimegmt(),
            'content_id'        => $image['id'],
            'content_date_gmt'    => $image['date_gmt'],
            'content_user_id'    => $image['user']['id'] ?? null,
            'content_ip'        => $image['uploader_ip'],
            'content_views'        => $image['views'],
            'content_md5'        => $image['md5'],
            'content_likes'        => $image['likes'],
            'content_original_filename'    => $image['original_filename'],
        ]);

        return DB::delete('images', ['id' => $id]);
    }

    public static function deleteMultiple($ids)
    {
        if (!is_array($ids)) {
            throw new ImageException('Expecting array argument, ' . gettype($ids) . ' given in ' . __METHOD__, 100);
        }
        $affected = 0;
        foreach ($ids as $id) {
            if (self::delete($id)) {
                $affected += 1;
            }
        }
        return $affected;
    }

    public static function deleteExpired($limit = 50)
    {
        if (!$limit || !is_numeric($limit)) {
            $limit = 50;
        }
        $db = DB::getInstance();
        $db->query('SELECT image_id FROM ' . DB::getTable('images') . ' WHERE image_expiration_date_gmt IS NOT NULL AND image_expiration_date_gmt < :datetimegmt ORDER BY image_expiration_date_gmt DESC LIMIT ' . $limit . ';'); // Just 50 files per request to prevent CPU meltdown or something like that
        $db->bind(':datetimegmt', G\datetimegmt());
        $expired_db = $db->fetchAll();
        if ($expired_db) {
            $expired = [];
            foreach ($expired_db as $k => $v) {
                $expired[] = $v['image_id'];
            }
            self::deleteMultiple($expired);
        }
    }

    public static function fill(&$image)
    {
        $image['id_encoded'] = encodeID($image['id']);
        $targets = self::getSrcTargetSingle($image, false);
        $medium_size = getSetting('upload_medium_size');
        $medium_fixed_dimension = getSetting('upload_medium_fixed_dimension');
        if ($targets['type'] == 'path') {
            if ($image['size'] == 0) {
                $get_image_fileinfo = G\get_image_fileinfo($targets['chain']['image']);
                $update_missing_values = [
                    'width'        => $get_image_fileinfo['width'],
                    'height'    => $get_image_fileinfo['height'],
                    'size'        => $get_image_fileinfo['size'],
                ];
                foreach (['thumb', 'medium'] as $k) {
                    if (!array_key_exists($k, $targets['chain'])) {
                        continue;
                    }
                    if ($image[$k . '_size'] == 0) {
                        $update_missing_values[$k . '_size'] = intval(filesize(G\get_image_fileinfo($targets['chain'][$k])));
                    }
                }
                self::update($image['id'], $update_missing_values);
                $image = array_merge($image, $update_missing_values);
            }
            $is_animated = isset($targets['chain']['image']) && G\is_animated_image($targets['chain']['image']);
            if (count($targets['chain']) > 0 && !$targets['chain']['thumb']) {
                try {
                    $thumb_options = [
                        'width'        => getSetting('upload_thumb_width'),
                        'height'    => getSetting('upload_thumb_height'),
                        'forced'    => $image['extension'] == 'gif' && $is_animated
                    ];
                    $targets['chain']['thumb'] = self::resize($targets['chain']['image'], pathinfo($targets['chain']['image'], PATHINFO_DIRNAME), $image['name'] . '.th', $thumb_options)['file'];
                } catch (Exception $e) {
                }
            }
            if ($image[$medium_fixed_dimension] > $medium_size && count($targets['chain']) > 0 && !isset($targets['chain']['medium'])) {
                try {
                    $medium_options = [
                        $medium_fixed_dimension        => $medium_size,
                        'forced'                    => $image['extension'] == 'gif' && $is_animated
                    ];
                    $targets['chain']['medium'] = self::resize($targets['chain']['image'], pathinfo($targets['chain']['image'], PATHINFO_DIRNAME), $image['name'] . '.md', $medium_options)['file'];
                } catch (Exception $e) {
                }
            }
            if (count($targets['chain']) > 0) {
                $original_md5 = $image['md5'];
                $image = array_merge($image, (array) @get_image_fileinfo($targets['chain']['image'])); // Never do an array merge over an empty thing!
                $image['md5'] = $original_md5;
            }
            if ($is_animated && !$image['is_animated']) {
                self::update($image['id'], ['is_animated' => 1]);
                $image['is_animated'] = 1;
            }
        } else {
            $image_fileinfo = [
                'ratio'                => $image['width'] / $image['height'],
                'size'                => intval($image['size']),
                'size_formatted'    => G\format_bytes($image['size'])
            ];
            $image = array_merge($image, get_image_fileinfo($targets['chain']['image']), $image_fileinfo);
        }

        $image['file_resource'] = $targets;
        $image['url_viewer'] = self::getUrlViewer($image['id_encoded'], getSetting('seo_image_urls') ? $image['title'] : null);
        $image['url_short'] = self::getUrlViewer($image['id_encoded']);
        foreach ($targets['chain'] as $k => $v) {
            if ($targets['type'] == 'path') {
                $image[$k] = file_exists($v) ? get_image_fileinfo($v) : null;
            } else {
                $image[$k] = get_image_fileinfo($v);
            }
            $image[$k]['size'] = $image[($k == 'image' ? '' : $k . '_') . 'size'];
        }
        $image['size_formatted'] = G\format_bytes($image['size']);
        $display_url = $image['url'];
        $display_width = $image['width'];
        $display_height = $image['height'];
        if (!empty($image['medium'])) {
            $display_url = $image['medium']['url'];
            $image_ratio = $image['width']/$image['height'];
            switch ($medium_fixed_dimension) {
                case 'width':
                    $display_width = $medium_size;
                    $display_height = intval(round($medium_size/$image_ratio));
                break;
                case 'height':
                    $display_height = $medium_size;
                    $display_width = intval(round($medium_size*$image_ratio));
                break;
            }
        } elseif ($image['size'] > G\get_bytes('200 KB')) {
            $display_url = $image['thumb']['url'] ?? '';
            $display_width = getSetting('upload_thumb_width');
            $display_height = getSetting('upload_thumb_height');
        }
        $image['display_url'] = $display_url;
        $image['display_width'] = $display_width;
        $image['display_height'] = $display_height;
        $image['views_label'] = _n('view', 'views', $image['views']);
        $image['likes_label'] = _n('like', 'likes', $image['likes']);
        $image['how_long_ago'] = time_elapsed_string($image['date_gmt']);
        $image['date_fixed_peer'] = Login::getUser() ? G\datetimegmt_convert_tz($image['date_gmt'], Login::getUser()['timezone']) : $image['date_gmt'];
        $image['title_truncated'] = G\truncate($image['title'], 28);
        $image['title_truncated_html'] = G\safe_html($image['title_truncated']);
        $image['is_use_loader'] = getSetting('image_load_max_filesize_mb') !== '' ? ($image['size'] > G\get_bytes(getSetting('image_load_max_filesize_mb') . 'MB')) : false;
    }

    public static function formatArray($dbrow, $safe = false)
    {
        $output = DB::formatRow($dbrow);

        if (isset($output['user']['id'])) {
            User::fill($output['user']);
        } else {
            unset($output['user']);
        }

        if (isset($output['album']['id']) || isset($output['user']['id'])) {
            Album::fill($output['album'], $output['user']);
        } else {
            unset($output['album']);
        }

        self::fill($output);

        if ($safe) {
            unset($output['storage']);
            unset($output['id'], $output['path'], $output['uploader_ip']);
            unset($output['album']['id'], $output['album']['privacy_extra'], $output['album']['user_id']);
            unset($output['user']['id']);
            unset($output['file_resource']);
            unset($output['file']['resource']['chain']);
        }

        return $output;
    }
}

class ImageException extends Exception
{
}
