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
use Throwable;

class Ftp
{
    public $ftp;
        
    public function __construct($args=[])
    {
        if (!function_exists('ftp_connect')) {
            throw new FtpException("ftp_connect function doesn't exists in this setup. You must enable PHP FTP support to interact with FTP servers.", 500);
        }
        foreach (['server', 'user', 'password'] as $v) {
            if (!array_key_exists($v, $args)) {
                throw new FtpException("Missing $v value", 100);
            }
        }
        $parsed_server = parse_url($args['server']);
        $host = $parsed_server['host'] ?: $args['server'];
        $port = $parsed_server['port'] ?: 21;
        try {
            $this->ftp = ftp_connect($host, $port);
        } catch (Throwable $e) {
            throw new FtpException("Can't connect to ".$args['server']." server", 200, $e);
        }
        try {
            ftp_login($this->ftp, $args['user'], $args['password']);
        } catch (Throwable $e) {
            throw new FtpException("Can't FTP login to ".$args['server']." server", 201, $e);
        }
        $args['passive'] = isset($args['passive']) ? (bool)$args['passive'] : true;
        try {
            ftp_pasv($this->ftp, $args['passive']);
        } catch (Throwable $e) {
            throw new FtpException("Can't ".($args['passive'] ? "enable" : "disable")." passive mode in server ".$args['server'], 202, $e);
        }
        if (isset($args['path'])) {
            try {
                $this->chdir($args['path']);
            } catch (Exception $e) {
                $this->mkdirRecursive($args['path']);
                $this->chdir($args['path']);
            }
        }
        return $this;
    }
    
    public function close()
    {
        ftp_close($this->ftp);
        unset($this->ftp);
        return true;
    }
    
    public function chdir($path)
    {
        $this->checkResource();
        try {
            ftp_chdir($this->ftp, $path);
        } catch (Throwable $e) {
            throw new FtpException("Unable to change dir '$path'", 300, $e);
        }
    }
    
    public function put($args=[])
    {
        foreach (['filename', 'source_file', 'path'] as $v) {
            if (!array_key_exists($v, $args)) {
                throw new FtpException("Missing $v value", 100);
            }
        }
        if (!array_key_exists('method', $args) or !in_array($args['method'], [FTP_BINARY, FTP_ASCII])) {
            $args['method'] = FTP_BINARY;
        }
        if (isset($args['path'])) {
            $this->chdir($args['path']);
        }
        $this->checkResource();
        try {
            ftp_put($this->ftp, $args['filename'], $args['source_file'], $args['method']);
        } catch (Throwable $e) {
            throw new FtpException("Can't upload '".$args['filename']."'", 401, $e);
        }
    }
    
    public function delete($file)
    {
        $this->checkResource();
        try {
            $binary = ftp_raw($this->ftp, 'TYPE I'); // SIZE command works only in Binary
            $raw = ftp_raw($this->ftp, "SIZE $file")[0];
        } catch (Throwable $e) {
            throw new FtpException("Can't delete file '$file'", 401, $e);
        }
        preg_match('/^(\d+)\s+(.*)$/', $raw, $matches);
        $code = $matches[1];
        $return = $matches[2];
        if ($code > 500) { // SIZE is supported and the file doesn't exits
            return;
        }
        try {
            ftp_delete($this->ftp, $file);
        } catch (Throwable $e) {
            throw new FtpException("Can't delete file '$file'", 200, $e);
        }
    }
    
    
    public function mkdirRecursive($path)
    {
        $path =  trim($path, '/');
        $this->checkResource();
        $cwd = @ftp_pwd($this->ftp);
        if (!$cwd) {
            throw new FtpException("Can't get current working directory for " . $path, 200);
        }
        $cwd .= '/';
        foreach (explode('/', $path) as $part) {
            $cwd .= $part . '/';
            if (empty($part)) {
                continue;
            }
            try {
                ftp_chdir($this->ftp, $cwd);
            } catch(Throwable $e) {
                try {
                    ftp_mkdir($this->ftp, $part);
                } catch(Throwable $e) {
                    throw new FtpException("Can't make recursive dir for " . $part, 200, $e);
                }
                try {
                    ftp_chdir($this->ftp, $part);
                } catch(Throwable $e) {
                    throw new FtpException("Unable to change fir to " . $part, 200, $e);
                }
            }
        }
    }
    
    protected function checkResource()
    {
        if (!is_resource($this->ftp)) {
            throw new FtpException("Invalid FTP buffer", 200);
        }
    }
}

class FtpException extends Exception
{
}
