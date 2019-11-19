<?php

namespace Aztec;

class Renderer
{
	private $image;
	private $pixelGrid;
	private $options;

	function __construct(array $pixelGrid, array $options)
	{
		$this->pixelGrid = $pixelGrid;
		$this->options = $options;
	}

	function __destruct()
	{
		if (is_resource($this->image)){
			imagedestroy($this->image);
		}
	}

	public function toBase64()
	{
		$this->createImage();
		ob_start();
		imagePng($this->image);
		$imagedata = ob_get_contents();
		ob_end_clean();

		return base64_encode($imagedata);
	}

	public function toPNG($filename)
	{
		$this->createImage();
		if(is_null($filename)) {
			header("Content-type: image/png");
		}
		imagepng($this->image, $filename);
	}

	public function toGIF($filename)
	{
		$this->createImage();
		if(is_null($filename)) {
			header("Content-type: image/gif");
		}
		imagegif($this->image, $filename);
	}

	public function toJPG($filename, $quality)
	{
		$this->createImage();
		if(is_null($filename)) {
			header("Content-type: image/jpeg");
		}
		imagejpeg($this->image, $filename, $quality);
	}

	public function forPChart($pImage, $X, $Y)
	{
		$this->createImage();
		imagecopy($pImage, $this->image, $X, $Y, 0, 0, imagesx($this->image), imagesy($this->image));
	}

	private function createImage()
	{
		$width = count($this->pixelGrid[0]);
        $f = $this->options['ratio'];
        $this->image = imagecreate($width * $f, $width * $f);

		// Extract options
		list($R,$G,$B) = $this->options['bgColor']->get();
		$bgColorAlloc = imagecolorallocate($this->image,$R,$G,$B);
		imagefill($this->image, 0, 0, $bgColorAlloc);
		list($R,$G,$B) = $this->options['color']->get();
		$colorAlloc = imagecolorallocate($this->image,$R,$G,$B);

		// Render the code
        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $width; $y++) {
				$bit = (isset($this->pixelGrid[$x][$y])) ? $this->pixelGrid[$x][$y] : FALSE;
                if ($bit !== FALSE) {
                    imagefilledrectangle($this->image, $x * $f, $y * $f, (($x + 1) * $f - 1), (($y + 1) * $f - 1), $colorAlloc);
                }
            }
        }
	}
}
