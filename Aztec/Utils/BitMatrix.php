<?php

namespace Aztec\Utils;

class BitMatrix
{
    private $matrix;
	private $width;

    public function __construct($width)
    {
        $this->matrix = [new \SplFixedArray($width), new \SplFixedArray($width)];
		$this->width = $width;
    }

    public function set($x, $y, $bit = 1)
    {
        $this->matrix[$x][$y] = $bit;
    }

    public function get($x, $y)
    {
		if (!isset($this->matrix[$x][$y])){
			return FALSE;
		} else {
			return $this->matrix[$x][$y];
		}
    }

    public function getWidth()
    {
        return $this->width;
    }
}
