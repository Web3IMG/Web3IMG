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
use Intervention\Image\Image;
use Intervention\Image\ImageManagerStatic;

class Imageresize
{
    // filename => name.ext
    // file => /full/path/to/name.ext
    // name => name

    public $resized;

    public $fixed =  false;

    private $image;

    public function setSource($source)
    {
        clearstatcache(true, $source);
        $this->source = $source;
        $this->image = ImageManagerStatic::make($source);
    }

    public function setDestination($destination)
    {
        $this->destination = $destination;
    }

    public function setFilename($name)
    {
        $this->filename = $name;
    }

    // Set options
    public function setOptions($options)
    {
        $this->options = $options;
    }

    // Set individual option
    public function setOption($key, $value)
    {
        $this->options[$key] = $value;
    }

    public function set_width($width)
    {
        $this->width = intval($width);
    }

    public function set_height($height)
    {
        $this->height = intval($height);
    }

    public function set_fixed()
    {
        $this->fixed = true;
    }

    /**
     * Do the thing.
     *
     * @Exception 4xx
     */
    public function exec()
    {
        $this->validateInput(); // Exception 1xx

        // Save the source filename
        $source_filename = G\get_basename_without_extension($this->source);

        // Set file extension
        $this->file_extension = $this->source_image_fileinfo['extension'];

        // Workaround the $filename
        if (!isset($this->filename)) {
            $this->filename = $source_filename;
        }

        // Fix the destination path
        $this->destination = G\add_ending_slash($this->destination);

        // Set $resized_file
        $this->resized_file = $this->destination . $this->filename . '.' . $this->file_extension;

        // Do the resize process
        $this->resize_image();

        $this->resized = [
            'file' => $this->resized_file,
            'filename' => G\get_filename($this->resized_file),
            'name' => G\get_basename_without_extension($this->resized_file),
            'fileinfo' => G\get_image_fileinfo($this->resized_file),
        ];
    }

    // @Exception 1xx
    protected function validateInput()
    {
        $check_missing = ['source'];
        missing_values_to_exception($this, 'CHV\ImageresizeException', $check_missing, 100);

        if (!$this->width and !$this->height) {
            throw new ImageresizeException('Missing ' . '$width and/or ' . '$height', 102);
        }

        if (!$this->destination) {
            $this->destination = G\add_ending_slash(dirname($this->source));
        }

        // Validate $source file
        if (!file_exists($this->source)) {
            throw new ImageresizeException("Source file doesn't exists", 110);
        }

        // $source file looks like an image?
        $this->source_image_fileinfo = G\get_image_fileinfo($this->source);
        if (!$this->source_image_fileinfo) {
            throw new ImageresizeException("Can't get source image info", 111);
        }

        // Validate $destination
        if (!is_dir($this->destination)) {
            $old_umask = umask(0);
            $make_destination = mkdir($this->destination, 0755, true);
            umask($old_umask);
            if (!$make_destination) {
                throw new ImageresizeException('$destination ' . $this->destination . ' is not a dir', 120);
            }
        }

        // Can write $destination dir?
        if (!is_writable($this->destination)) {
            throw new ImageresizeException("Can't write target destination dir " . $this->destination, 122);
        }

        // Validate width and height
        if (isset($this->width) && !is_int($this->width)) {
            throw new ImageresizeException('Expecting integer value in $width, ' . gettype($this->width) . ' given', 130);
        }

        if (isset($this->height) && !is_int($this->height)) {
            throw new ImageresizeException('Expecting integer value in $height, ' . gettype($this->width) . ' given', 131);
        }
    }

    // @Exception 2xx
    protected function resize_image()
    {
        // Fix the $width and $height vars
        if (isset($this->width, $this->height)) {
            $this->set_fixed();
        } else {
            if ($this->fixed) {
                if ($this->width) {
                    $this->height = $this->width;
                } else {
                    $this->width = $this->height;
                }
            } else {
                if (isset($this->width)) {
                    $this->height = intval(round($this->width / $this->source_image_fileinfo['ratio']));
                } else {
                    $this->width = intval(round($this->height * $this->source_image_fileinfo['ratio']));
                }
            }
        }
        $imageSX = $this->source_image_fileinfo['width'];
        $imageSY = $this->source_image_fileinfo['height'];
        if (isset($this->width, $this->height) && $this->width == $imageSX && $this->height == $imageSY && !$this->options['forced']) {
            @copy($this->source, $this->resized_file);

            return;
        }
        if ($this->fixed) {
            $this->image->fit($this->width, $this->height);
        } else {
            $this->image->resize($this->width, $this->height);
        }
        $this->image->save($this->resized_file);
        if (!file_exists($this->resized_file)) {
            throw new ImageresizeException("Can't create final output image", 230);
        }
    }
}

class ImageresizeException extends Exception
{
}
