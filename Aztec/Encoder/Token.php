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

	public function add($value, $bitCount)
	{
		$token = clone $this;
		$token->addtoHistory([$value, $bitCount, FALSE]);
		return $token;
	}

	public function addBinaryShift($value, $bitCount)
	{
		$token = clone $this;
		$token->setState($this->mode, 0, $this->bitCount);
		$token->addtoHistory([$value, $bitCount, TRUE]);
		return $token;
	}
}
