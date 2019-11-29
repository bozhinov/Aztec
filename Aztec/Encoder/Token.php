<?php

/*
 * Copyright 2013 Metzli and ZXing authors
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

	public function add($value, $bitCount)
	{
		$token = new self([$value, $bitCount, 0]);
		$token->setState($this->mode, $this->shiftByteCount, $this->bitCount);
		$token->setHistory($this->previous);
		$token->addtoHistory([$value, $bitCount, 0]);

		return $token;
	}

	public function addBinaryShift($value, $bitCount)
	{
		$token = new self();
		$token->setState($this->mode, 0, $this->bitCount);
		$token->setHistory($this->previous);
		$token->addtoHistory([$value, $bitCount, 1]);

		return $token;
	}
}
