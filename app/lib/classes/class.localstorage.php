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

use function G\is_writable;
use function G\unlinkIfExists;

// todo props standard

class LocalStorage
{
    public $url;
    public $path;
    public $realPath;
    public $deleted = [];

    public function __construct($args=[])
    {
        $this->url = rtrim($args['url'], '/') . '/';
        $this->path = rtrim($args['bucket'], '/'). '/';
        $this->realPath = realpath($this->path) . '/';
        if($this->realPath === '/') {
            $this->realPath = $this->path;
        }
        $this->assertPath($this->realPath);
    }
    protected function assertPath($path)
    {
        if (is_writable($path) === false) {
            throw new Exception(
                sprintf("Path %s is not writable", $path),
                100
            );
        }
    }
    public function put($args=[])
    {
        // [filename] => photo-1460378150801-e2c95cb65a50.jpg
        // [source_file] => /tmp/photo-1460378150801-e2c95cb65a50.jpg
        // [path] => /path/sdk/2018/08/18/
        extract($args);
        $this->assertPath($path);
        $target_filename = $path . $filename;
        $target_filename = str_replace('/.\/', '/', $target_filename);
        if($source_file == $target_filename) {
            return;
        }
        $uploaded = copy($source_file, $target_filename);
        $errors = error_get_last();
        if ($uploaded == false) {
            throw new Exception(
                strtr("Can't move source file %source% to %destination%: %message%", [
                    '%source%' => $source_file,
                    '%destination%' => $target_filename,
                    '%message%' => 'Copy error ' . $errors['type'] . ' > ' . $errors['message'],
                ]),
                400
            );
        }
        chmod($target_filename, 0644);
        clearstatcache();
    }
    public function delete($filename)
    {
        $filename = $this->getWorkingPath($filename);
        if (file_exists($filename) == false) {
            return;
        }
        if (unlinkIfExists($filename) == false) {
            throw new Exception("Can't delete file '$filename' in " . __METHOD__, 200);
        }
        clearstatcache();
    }
    public function deleteMultiple(array $filenames=[])
    {
        $this->deleted = [];
        foreach ($filenames as $k => $v) {
            $this->delete($v);
            array_push($this->deleted, $v);
        }
    }
    public function mkdirRecursive($dirname)
    {
        $dirname = $this->getWorkingPath($dirname);
        if (is_dir($dirname)) {
            return;
        }
        $path_perms = fileperms($this->realPath);
        $old_umask = umask(0);
        $make_pathname = mkdir($dirname, $path_perms, true);
        chmod($dirname, $path_perms);
        umask($old_umask);
        if (!$make_pathname) {
            throw new Exception('$dirname '. $dirname . ' is not a dir', 130);
        }
    }
    protected function getWorkingPath($dirname)
    {
        if (G\starts_with('/', $dirname) == false) { // relative thing
            return $this->realPath . $dirname;
        }
        return realpath($dirname);
    }
}
