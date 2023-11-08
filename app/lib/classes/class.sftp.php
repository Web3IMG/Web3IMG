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

class Sftp
{
    public $sftp; // sftp session

    public function __construct($args=[])
    {
        foreach (['server', 'user', 'password'] as $v) {
            if (!array_key_exists($v, $args)) {
                throw new SftpException("Missing $v value in " . __METHOD__, 100);
            }
        }
        $parsed_server = parse_url($args['server']);
        $host = $parsed_server['host'] ?: $args['server'];
        $port = $parsed_server['port'] ?: 22;
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false and filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            $hbn = gethostbyname($host);
            if ($hbn == $host) {
                throw new SftpException("Can't resolve host for $host in " . __METHOD__, 101);
            } else {
                $host = $hbn;
            }
        }
        $this->sftp = new \phpseclib\Net\SFTP($host, $port);
        if (!$this->sftp) {
            throw new SftpException("Can't connect to ".$args['server']." server", 200);
        }
        if (!$this->sftp->login($args['user'], $args['password'])) {
            $errors = !empty($this->sftp->getSFTPErrors()) ? implode("\n", $this->sftp->getSFTPErrors()) : '';
            throw new SftpException("Can't SFTP login to ".$args['server']." server " . $errors, 301);
        }
        if (isset($args['path'])) {
            try {
                $this->chdir($args['path']);
            } catch (Exception $e) {
                // Create missing path if possible
                $this->mkdirRecursive($args['path']);
                $this->chdir($args['path']);
            }
        }
        return $this;
    }

    public function close()
    {
        $this->sftp->exec('exit');
        unset($this->sftp);
        return true;
    }

    public function chdir($path)
    {
        $this->checkResource();
        if (!$this->sftp->chdir($path)) {
            throw new SftpException("Can't change dir '$path' in " . __METHOD__, 300);
        }
    }

    public function put($args=[])
    {
        foreach (['filename', 'source_file', 'path'] as $v) {
            if (!array_key_exists($v, $args)) {
                throw new SftpException("Missing $v value in ".__METHOD__, 100);
            }
        }
        $this->checkResource();
        if (array_key_exists('path', $args) and !$this->sftp->chdir($args['path'])) {
            throw new SftpException("Can't change dir '".$args['path']."' in " . __METHOD__, 200);
        }
        if (!$this->sftp->put($args['filename'], $args['source_file'], 1)) { // 1 for local file, 2 for string
            throw new SftpException("Can't upload '".$args['filename']."' to '".$args['path']."' in " . __METHOD__, 200);
        }
    }

    public function delete($file)
    {
        $this->checkResource();
        // Check if the file exists
        if (!$this->sftp->stat($file[0])) {
            return true;
        }
        if (!$this->sftp->delete($file)) {
            throw new SftpException("Can't delete file '$file' in " . __METHOD__, 200);
        }
    }

    public function deleteMultiple(array $files=[])
    {
        $this->checkResource();
        if (count($files) == 0) {
            throw new SftpException("Missing or invalid array argument in " . __METHOD__, 200);
        }
        foreach ($files as $file) {
            $this->sftp->delete($file, false);
        }
        
        return $files;
    }


    public function mkdirRecursive($path)
    {
        $this->checkResource();
        return $this->sftp->mkdir($path, -1, true);
    }

    protected function checkResource()
    {
        if (!is_object($this->sftp)) {
            throw new SftpException("Invalid SFTP object in " . __METHOD__, 200);
        }
    }
}

class SftpException extends Exception
{
}
