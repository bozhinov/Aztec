<?php

namespace Aztec;

class Renderer
{
	private $image;
	private $size;

	function __construct(array $pixelGrid, array $options)
	{
		$width = count($pixelGrid);
		$ratio = $options['ratio'];
		$padding = $options['padding'];
		$this->size = ($width * $ratio) + ($padding * 2);
		$this->image = imagecreate($this->size, $this->size);

		// Extract options
		list($R, $G, $B) = $options['bgColor']->get();
		$bgColorAlloc = imagecolorallocate($this->image, $R, $G, $B);
		imagefill($this->image, 0, 0, $bgColorAlloc);
		list($R, $G, $B) = $options['color']->get();
		$colorAlloc = imagecolorallocate($this->image, $R, $G, $B);

		// Render the code
		for ($x = 0; $x < $width; $x++) {
			for ($y = 0; $y < $width; $y++) {
				if (isset($pixelGrid[$x][$y])){
					imagefilledrectangle(
						$this->image, ($x * $ratio) + $padding,
						($y * $ratio) + $padding,
						(($x + 1) * $ratio - 1) + $padding,
						(($y + 1) * $ratio - 1) + $padding,
						$colorAlloc
					);
				}
			}
		}
	}

	function __destruct()
	{
		if (is_resource($this->image)){
			imagedestroy($this->image);
		}
	}

	public function toBase64()
	{
		ob_start();
		imagepng($this->image);
		$imagedata = ob_get_contents();
		ob_end_clean();

		return base64_encode($imagedata);
	}

	public function toPNG($filename)
	{
		if(is_null($filename)) {
			header("Content-type: image/png");
		}
		imagepng($this->image, $filename);
	}

	public function toGIF($filename)
	{
		if(is_null($filename)) {
			header("Content-type: image/gif");
		}
		imagegif($this->image, $filename);
	}

	public function toJPG($filename, $quality)
	{
		if(is_null($filename)) {
			header("Content-type: image/jpeg");
		}
		imagejpeg($this->image, $filename, $quality);
	}

	public function forPChart($pImage, $X, $Y)
	{
		imagecopy($pImage, $this->image, $X, $Y, 0, 0, $this->size, $this->size);
	}
}
