<?php

namespace Aztec\Encoder;

class Token
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

	public function getBitCount()
	{
		return $this->bitCount;
	}

	public function getPrevious()
	{
		return $this->previous;
	}

	public function setHistory(array $history)
	{
		$this->previous = $history;
	}

	public function addtoHistory(array $previous)
	{
		$this->previous[] = $previous;
	}

	private function instantiate($value, $bitCount, $type)
	{
		$bc = ($type == 1) ? 0 : $this->shiftByteCount;

		$token = new self();
		$token->setState($this->mode, $bc, $this->bitCount);
		$token->setHistory($this->previous);
		$token->addtoHistory([$value, $bitCount, $type]);

		return $token;
	}

	public function add($value, $bitCount)
	{
		return $this->instantiate($value, $bitCount, 0);
	}

	public function addBinaryShift($value, $bitCount)
	{
		return $this->instantiate($value, $bitCount, 1);
	}
}
