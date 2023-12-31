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
use Intervention\Image\ImageManagerStatic;
use Throwable;

use function G\is_animated_webp;
use function G\unlinkIfExists;

class Upload
{
    // filename => name.ext
    // file => /full/path/to/name.ext
    // name => name

    const URL_SCHEMES = [
        'http',
        'https',
        'ftp'
    ];

    public $source;
    public $uploaded;
    public $detectFlood = true;
    public $options;

    public function checkValidUrl(string $url): void
    {
        $aux = strtolower($url);
        $scheme = parse_url($aux, PHP_URL_SCHEME);
        if(!in_array($scheme, self::URL_SCHEMES)) {
            throw new UploadException(
                strtr(
                    "Unsupported URL scheme `%scheme%`", [
                        '%scheme%' => $scheme
                    ]
                ),
                400
            );
        }
        $host = parse_url($aux, PHP_URL_HOST);
        if(parse_url(G_HTTP_HOST, PHP_URL_HOST) === $host) {
            throw new UploadException(
                "Unsupported self host URL upload",
                400
            );
        }
        $ip = gethostbyname($host);
        $typePub = \IPLib\Range\Type::getName(\IPLib\Range\Type::T_PUBLIC);
        $address = \IPLib\Factory::parseAddressString($ip);
        $type = $address->getRangeType();
        $typeName = \IPLib\Range\Type::getName($type);
        if($typeName !== $typePub) {
            throw new UploadException(
                "Unsupported non-public IP address for upload",
                400
            );
        }
    }

    public function setSource($source)
    {
        $this->source = $source;
        $this->type = (G\is_image_url($this->source) || G\is_url($this->source))
            ? 'url'
            : 'file';
        if($this->type === 'url') {
            if(Settings::get('enable_uploads_url') === false) {
                throw new UploadException('URL uploading is forbidden', 403);
            }
            $this->checkValidUrl($this->source);
        }
    }

    public function setDestination($destination)
    {
        $this->destination = G\forward_slash($destination);
    }

    public function setStorageId($storage_id)
    {
        $this->storage_id = is_numeric($storage_id) ? $storage_id : null;
    }

    public function setFilename($name)
    {
        $this->name = $name;
    }

    public function setOptions($options)
    {
        $this->options = $options;
    }

    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    public static function getDefaultOptions()
    {
        return array(
            'max_size' => G\get_bytes('2 MB'),
            'filenaming' => 'original',
            'exif' => true,
            'allowed_formats' => self::getAvailableImageFormats(), // array
        );
    }

    /**
     * Do the thing.
     *
     * @Exception 4xx
     */
    public function exec()
    {
        $this->options = array_merge(self::getDefaultOptions(), (array) $this->options);
        $this->validateInput(); // Exception 1
        $this->fetchSource(); // Exception 2
        $this->validateSourceFile(); // Exception 3
        if (!is_array($this->options['allowed_formats'])) {
            $this->options['allowed_formats'] = explode(',', $this->options['allowed_formats']);
        }
        $this->source_name = G\get_basename_without_extension($this->type == 'url' ? $this->source : $this->source['name']);
        $this->extension = $this->source_image_fileinfo['extension'];
        if (!isset($this->name)) {
            $this->name = $this->source_name;
        }
        $this->name = ltrim($this->name, '.');
        if (G\get_file_extension($this->name) == $this->extension) {
            $this->name = G\get_basename_without_extension($this->name);
        }
        $this->fixed_filename = preg_replace('/(.*)\.(th|md|original|lg)\.([\w]+)$/', '$1.$3', $this->name . '.' . $this->extension);
        $is_360 = false;
        if ($this->extension == 'jpg') {
            $xmpDataExtractor = new XmpMetadataExtractor();
            $xmpData = $xmpDataExtractor->extractFromFile($this->downstream);
            $is_360 = false;
            if(isset($xmpData, $xmpData['rdf:RDF']['rdf:Description']['@attributes']['ProjectionType'])) {
                $is_360 = $xmpData['rdf:RDF']['rdf:Description']['@attributes']['ProjectionType'] == 'equirectangular';
            }
            if (array_key_exists('exif', $this->options)) {
                $this->source_image_exif = null;
                try {
                    $this->source_image_exif = \exif_read_data($this->downstream);
                } catch(Throwable $e) {
                }
                if (isset($this->source_image_exif)) {
                    $this->source_image_exif['FileName'] = $this->source_filename;
                    if (isset($this->source_image_exif['Orientation'])) {
                        ImageManagerStatic::make($this->downstream)->orientate()->save();
                    }
                }
                if (!$this->options['exif']) {
                    unset($this->source_image_exif);
                    if(ImageManagerStatic::getManager()->config['driver'] === 'imagick') {
                        $img = ImageManagerStatic::make($this->downstream);
                        $img->getCore()->stripImage();
                        $img->save();
                    } else {
                        $img = @imagecreatefromjpeg($this->downstream);
                        if ($img) {
                            imagejpeg($img, $this->downstream, 90);
                            imagedestroy($img);
                        } else {
                            throw new UploadException("GD: Unable to create a new JPEG without Exif data", 444);
                        }
                    }
                    
                }
            }
        }

        /*
         * Set uploaded_file
         * Local storage uploads will be allocated at the target destination
         * External storage will be allocated to the temp directory
         */
        if (isset($this->storage_id)) {
            $this->uploaded_file = G\forward_slash(dirname($this->downstream)) . '/' . Storage::getStorageValidFilename($this->fixed_filename, $this->storage_id, $this->options['filenaming'], $this->destination);
        } else {
            $this->uploaded_file = G\name_unique_file($this->destination, $this->fixed_filename, $this->options['filenaming']);
        }

        $this->panicExtension($this->uploaded_file);

        $this->source = [
            'filename' => $this->source_filename, // file.ext
            'name' => $this->source_name, // file
            'image_exif' => $this->source_image_exif ?? '', // exif-data array
            'fileinfo' => G\get_image_fileinfo($this->downstream), // fileinfo array
        ];

        // 666 because concurrency is evil
        if (stream_resolve_include_path($this->downstream) == false) {
            throw new UploadException('Concurrency: Downstream gone, aborting operation', 666);
        }
        if (stream_resolve_include_path($this->uploaded_file) != false) {
            throw new UploadException('Concurrency: Target uploaded file already exists, aborting operation', 666);
        }

        try {
            $uploaded = rename($this->downstream, $this->uploaded_file);
        } catch(Throwable $e) {
            $uploaded = file_exists($this->uploaded_file);
        }
        unlinkIfExists($this->downstream);

        if (!$uploaded) {
            unlinkIfExists($this->uploaded_file);
            throw new UploadException("Can't move temp file to its destination", 400);
        }

        if (!isset($this->storage_id)) {
            try {
                chmod($this->uploaded_file, 0644);
            } catch(Throwable $e) {
            }
        }

        $fileinfo = G\get_image_fileinfo($this->uploaded_file);
        $fileinfo['is_360'] = $is_360;
        $this->uploaded = array(
            'file' => $this->uploaded_file,
            'filename' => G\get_filename($this->uploaded_file),
            'name' => G\get_basename_without_extension($this->uploaded_file),
            'fileinfo' => $fileinfo,
        );
    }

    // Get available (supported) extensions
    public static function getAvailableImageFormats()
    {
        $formats = Settings::get('upload_available_image_formats');

        return explode(',', $formats);
    }

    // Failover since v3.8.12
    public static function getEnabledImageFormats()
    {
        return Image::getEnabledImageFormats();
    }

    /**
     * validate_input aka "first stage validation"
     * This checks for valid input source data.
     *
     * @Exception 1XX
     */
    protected function validateInput()
    {
        $check_missing = ['type', 'source', 'destination'];
        missing_values_to_exception($this, "CHV\UploadException", $check_missing, 100);

        // Validate $type
        if (!preg_match('/^(url|file)$/', $this->type)) {
            throw new UploadException('Invalid $type "' . $this->type . '"', 110);
        }

        // Handle flood
        if ($this->detectFlood && $flood = self::handleFlood()) {
            throw new UploadException(
                _s(
                    'Flooding detected. You can only upload %limit% %content% per %time%',
                    [
                        '%content%' => _n('image', 'images', $flood['limit']),
                        '%limit%' => $flood['limit'],
                        '%time%' => $flood['by']
                    ]
                ),
                130
            );
        }

        // Validate $source
        if ($this->type == 'file') {
            if (count($this->source) < 5) { // Valid $_FILES ?
                throw new UploadException('Invalid file source', 120);
            }
        } elseif ($this->type == 'url') {
            if (!G\is_image_url($this->source) && !G\is_url($this->source)) {
                throw new UploadException('Invalid image URL', 122);
            }
        }

        // Validate $destination
        if (!is_dir($this->destination)) { // Try to create the missing directory
            $base_dir = G\add_ending_slash(G_ROOT_PATH . explode('/', preg_replace('#' . G_ROOT_PATH . '#', '', $this->destination, 1))[0]);
            $base_perms = fileperms($base_dir);
            $old_umask = umask(0);
            $make_destination = mkdir($this->destination, $base_perms, true);
            chmod($this->destination, $base_perms);
            umask($old_umask);
            if (!$make_destination) {
                throw new UploadException('$destination ' . $this->destination . ' is not a dir', 130);
            }
        }

        // Can read $destination dir?
        if (!is_readable($this->destination)) {
            throw new UploadException("Can't read target destination dir", 131);
        }

        // Can write $destination dir?
        if (!is_writable($this->destination)) {
            throw new UploadException("Can't write target destination dir", 132);
        }

        // Fix $destination trailing
        $this->destination = G\add_ending_slash($this->destination);
    }

    public static function getTempNam($destination)
    {
        $tempNam = @tempnam(sys_get_temp_dir(), 'chvtemp');
        if (!$tempNam || !@is_writable($tempNam)) {
            $tempNam = @tempnam($destination, 'chvtemp');
            if (!$tempNam) {
                throw new UploadException("Can't get a tempnam", 200);
            }
        }

        return $tempNam;
    }

    protected function panicExtension(string $filename) {
        if(
            G\ends_with('.php', $filename)
            || G\ends_with('.htaccess', $filename))
        {
            throw new UploadException(sprintf('Unwanted extension for %s', $filename));
        }
        $extension = G\get_file_extension($filename);
        if(!in_array($extension, self::getEnabledImageFormats())) {
            throw new UploadException(sprintf('Unable to handle upload for %s', $filename));
        }
    }

    /**
     * Fetch the $source file.
     *
     * @Exception 2XX
     */
    protected function fetchSource()
    {
        $this->downstream = static::getTempNam($this->destination);
        if ($this->type == 'file') {
            if ($this->source['error'] !== UPLOAD_ERR_OK) {
                switch ($this->source['error']) {
                    case UPLOAD_ERR_INI_SIZE: // 1
                        throw new UploadException('File too big (UPLOAD_ERR_INI_SIZE)', 201);
                        break;
                    case UPLOAD_ERR_FORM_SIZE: // 2
                        throw new UploadException('File exceeds form max size (UPLOAD_ERR_FORM_SIZE)', 201);
                        break;
                    case UPLOAD_ERR_PARTIAL: // 3
                        throw new UploadException('File was partially uploaded (UPLOAD_ERR_PARTIAL)', 201);
                        break;
                    case UPLOAD_ERR_NO_FILE: // 4
                        throw new UploadException('No file was uploaded (UPLOAD_ERR_NO_FILE)', 201);
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR: // 5
                        throw new UploadException('Missing temp folder (UPLOAD_ERR_NO_TMP_DIR)', 201);
                        break;
                    case UPLOAD_ERR_CANT_WRITE: // 6
                        throw new UploadException('System write error (UPLOAD_ERR_CANT_WRITE)', 201);
                        break;
                    case UPLOAD_ERR_EXTENSION: // 7
                        throw new UploadException('The upload was stopped (UPLOAD_ERR_EXTENSION)', 201);
                        break;
                }
            }
            try {
                $renamed = rename($this->source['tmp_name'], $this->downstream);
            } catch(Throwable $e) {
                $renamed  = file_exists($this->downstream);
            }
            if(!$renamed) {
                throw new UploadException('Unable to rename tmp_name to downstream', 122);
            }
        } elseif ($this->type == 'url') {
            G\fetch_url($this->source, $this->downstream);
        }

        $this->source_filename = basename($this->type == 'file' ? $this->source['name'] : $this->source);
    }

    /**
     * validate_source_file aka "second stage validation"
     * This checks for valid input source data.
     *
     * @Exception 3XX
     */
    protected function validateSourceFile()
    {
        // Nothing to do here
        if (!file_exists($this->downstream)) {
            throw new UploadException("Can't fetch target upload source (downstream)", 300);
        }

        $this->source_image_fileinfo = G\get_image_fileinfo($this->downstream);

        // file info?
        if (!$this->source_image_fileinfo) {
            throw new UploadException("Can't get target upload source info", 310);
        }

        // Valid image fileinto?
        if ($this->source_image_fileinfo['width'] == '' || $this->source_image_fileinfo['height'] == '') {
            throw new UploadException('Invalid image', 311);
        }

        // Available image format?
        if (!in_array($this->source_image_fileinfo['extension'], self::getAvailableImageFormats())) {
            throw new UploadException('Unavailable image format', 313);
        }

        // Enabled image format?
        if (!in_array($this->source_image_fileinfo['extension'], $this->options['allowed_formats'])) {
            throw new UploadException(sprintf('Disabled image format (%s)', $this->source_image_fileinfo['extension']), 314);
        }

        // Mime
        if (!$this->isValidImageMime($this->source_image_fileinfo['mime'])) {
            throw new UploadException('Invalid image mimetype', 312);
        }

        // Size
        if (!$this->options['max_size']) {
            $this->options['max_size'] = self::getDefaultOptions()['max_size'];
        }
        if ($this->source_image_fileinfo['size'] > $this->options['max_size']) {
            throw new UploadException('File too big - max ' . G\format_bytes($this->options['max_size']), 313);
        }

        // BMP?
        if ($this->source_image_fileinfo['extension'] == 'bmp') {
            $this->ImageConvert = new ImageConvert($this->downstream, 'png', $this->downstream);
            $this->downstream = $this->ImageConvert->out;
            $this->source_image_fileinfo = G\get_image_fileinfo($this->downstream);
        }

        if ($this->source_image_fileinfo['extension'] == 'webp'
            && is_animated_webp($this->downstream)
            && ImageManagerStatic::getManager()->config['driver'] === 'gd'
        ) {
            throw new Exception('Animated WebP is not supported (libgd)', 314);
        }

        if (Settings::get('moderatecontent') && (Settings::get('moderatecontent_block_rating') != '' || Settings::get('moderatecontent_flag_nsfw'))) {
            $moderateContent = new ModerateContent($this->downstream, $this->source_image_fileinfo);
            if ($moderateContent->isSuccess()) {
                $this->moderation = $moderateContent->moderation();
            } else {
                throw new UploadException('Error processing content moderation: ' . $moderateContent->errorMessage());
            }
        }
    }

    // Handle flood uploads
    protected static function handleFlood()
    {
        $logged_user = Login::getUser();

        if (!getSetting('flood_uploads_protection') || Login::isAdmin()) {
            return false;
        }

        $flood_limit = [];
        foreach (['minute', 'hour', 'day', 'week', 'month'] as $v) {
            $flood_limit[$v] = getSetting('flood_uploads_' . $v);
        }

        try {
            $db = DB::getInstance();
            $flood_db = $db->queryFetchSingle(
                'SELECT
				COUNT(IF(image_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MINUTE), 1, NULL)) AS minute,
				COUNT(IF(image_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR), 1, NULL)) AS hour,
				COUNT(IF(image_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY), 1, NULL)) AS day,
				COUNT(IF(image_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 WEEK), 1, NULL)) AS week,
				COUNT(IF(image_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MONTH), 1, NULL)) AS month
			FROM ' . DB::getTable('images') . " WHERE image_uploader_ip='" . G\get_client_ip() . "' AND image_date_gmt >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 MONTH)"
            );
        } catch (Exception $e) {
        } // Silence

        $is_flood = false;
        $flood_by = '';
        foreach (['minute', 'hour', 'day', 'week', 'month'] as $v) {
            if ($flood_limit[$v] > 0 && ($flood_db[$v] ?? 0) >= $flood_limit[$v]) {
                $flood_by = $v;
                $is_flood = true;
                break;
            }
        }

        if ($is_flood) {
            session_start();
            if (getSetting('flood_uploads_notify') and !$_SESSION['flood_uploads_notify'][$flood_by]) {
                try {
                    $message = strtr('Flooding IP <a href="' . G\get_base_url('search/images/?q=ip:%ip') . '">%ip</a>', ['%ip' => G\get_client_ip()]) . '<br>';
                    if ($logged_user) {
                        $message .= 'User <a href="' . $logged_user['url'] . '">' . $logged_user['name'] . '</a><br>';
                    }
                    $message .= '<br>';
                    $message .= '<b>Uploads per time period</b>' . '<br>';
                    $message .= 'Minute: ' . $flood_db['minute'] . '<br>';
                    $message .= 'Hour: ' . $flood_db['hour'] . '<br>';
                    $message .= 'Week: ' . $flood_db['day'] . '<br>';
                    $message .= 'Month: ' . $flood_db['week'] . '<br>';
                    system_notification_email(['subject' => 'Flood report IP ' . G\get_client_ip(), 'message' => $message]);
                    $_SESSION['flood_uploads_notify'][$flood_by] = true;
                } catch (Exception $e) {
                } // Silence
            }

            return ['flood' => true, 'limit' => $flood_limit[$flood_by], 'count' => $flood_db[$flood_by], 'by' => $flood_by];
        }

        return false;
    }

    protected function isValidImageMime($mime)
    {
        return preg_match("#image\/(gif|pjpeg|jpeg|png|x-png|bmp|x-ms-bmp|x-windows-bmp|webp)$#", $mime);
    }

    protected function isValidNamingOption($string)
    {
        return in_array($string, array('mixed', 'random', 'original'));
    }
}

class UploadException extends Exception
{
}
