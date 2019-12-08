<?php

namespace Aztec\Encoder;

class Token implements \Countable
{
	private $previous = [];
	private $mode = 0;
	private $shiftByteCount = 0;
	private $bitCount = 0;

	public function setState($mode, $binaryBytes, $bitCount)
	{
		$this->mode = $mode;
		$this->shiftByteCount = $binaryBytes;
		$this->bitCount = $bitCount;
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

	public function addtoHistory(array $previous)
	{
		$this->previous[] = $previous;
	}

	private function instantiate($value, $bitCount, $binaryShift)
	{
		$bc = ($binaryShift) ? 0 : $this->shiftByteCount;

		$token = clone $this;
		$token->setState($this->mode, $bc, $this->bitCount);
		$token->addtoHistory([$value, $bitCount, $binaryShift]);

		return $token;
	}

	public function add($value, $bitCount)
	{
		return $this->instantiate($value, $bitCount, FALSE);
	}

	public function addBinaryShift($value, $bitCount)
	{
		return $this->instantiate($value, $bitCount, TRUE);
	}
}
