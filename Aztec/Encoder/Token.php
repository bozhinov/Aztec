<?php

namespace Aztec\Encoder;

class Token implements \Countable
{
	private $previous = [];
	private $mode = 0;
	private $shiftByteCount = 0;
	private $bitCount = 0;

	public function setState($mode, $binaryBytes, $bitCount = 0)
	{
		$this->mode = $mode;
		$this->shiftByteCount = $binaryBytes;
		$this->bitCount += $bitCount;
	}

	public function getMode()
	{
		return $this->mode;
	}

	public function getShiftByteCount()
	{
		return $this->shiftByteCount;
	}

	public function count()
	{
		return $this->bitCount;
	}

	public function getPrevious()
	{
		return $this->previous;
	}

	public function add($value, $bits)
	{
		$this->bitCount += $bits;
		$this->previous[] = [$value, $bits];
	}

	public function endBinaryShift()
	{
		$this->shiftByteCount = 0;
	}
}
