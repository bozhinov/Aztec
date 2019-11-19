<?php

namespace Aztec;

use Aztec\azColor;
use Aztec\azException;
use Aztec\Encoder;
use Aztec\Renderer;

class Aztec
{
	private $options = [];
	private $pixelGrid;

	public function __construct(array $options = [])
    {
		$this->options['color'] = (isset($options['color'])) ? $options['color'] : new azColor(0);
		$this->options['bgColor'] = (isset($options['bgColor'])) ? $options['bgColor'] : new azColor(255);
		$this->options['eccPercent'] = (isset($options['eccPercent'])) ? $options['eccPercent'] : 33;
		$this->options['hint'] = (isset($options['hint'])) ? $options['hint'] : "dynamic";
		$this->options['ratio'] = (isset($options['ratio'])) ? $options['ratio'] : 4;
		$this->options['quality'] = (isset($options['quality'])) ? $options['quality'] : 90;

		$this->validateOptions();
    }

	public function config(array $options)
	{
		$this->__construct($options);
	}

	private function option_in_range(string $name, int $start, int $end)
	{
        if (!is_numeric($this->options[$name]) || $this->options[$name] < $start || $this->options[$name] > $end) {
			throw azException::InvalidInput("Invalid value for \"$name\". Expected an integer between $start and $end.");
        }
	}

    private function validateOptions()
    {
		$this->option_in_range('ratio', 1, 10);
		$this->option_in_range('eccPercent', 1, 200);
		$this->option_in_range('quality', 0, 100);

		if (!in_array($this->options["hint"], ["binary", "dynamic", "text"])){
			throw azException::InvalidInput("Invalid value for \"hint\". Expected \"binary\", \"text\" or \"dynamic\".");
        }

		if (!($this->options['color'] instanceof azColor)) {
			throw azException::InvalidInput("Invalid value for \"color\". Expected a pColor object.");
		}

		if (!($this->options['bgColor'] instanceof azColor)) {
			throw azException::InvalidInput("Invalid value for \"bgColor\". Expected a pColor object.");
		}
    }

	public function toFile(string $filename, bool $forWeb = false)
	{
		$ext = strtoupper(substr($filename, -3));
		($forWeb) AND $filename = null;

		$Renderer = new Renderer($this->pixelGrid, $this->options);

		switch($ext)
		{
			case "PNG":
				$Renderer->toPNG($filename);
				break;
			case "GIF":
				$Renderer->toGIF($filename);
				break;
			case "JPG":
				$Renderer->toJPG($filename, $this->options['quality']);
				break;
			default:
				throw azException::InvalidInput('File extension unsupported!');
		}
	}

	public function forWeb(string $ext)
	{
		if (strtoupper($ext) == "BASE64"){
			echo (new Renderer($this->pixelGrid, $this->options))->toBase64();
		} else {
			$this->toFile($ext, true);
		}
	}

	public function forPChart(\pChart\pDraw $MyPicture, $X = 0, $Y = 0)
	{
		$Renderer = new Renderer($this->pixelGrid, $this->options);
		$Renderer->forPChart($MyPicture->gettheImage(), $X, $Y);
	}

    /**
     * Encodes the given data
     */
    public function encode($data)
    {
		$this->pixelGrid = (new Encoder())->encode($data, $this->options['eccPercent'], $this->options["hint"]);
    }

}
