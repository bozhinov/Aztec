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
    private $previous;
	private $type = 0;
    private $totalBitCount;
    private $mode;
    private $shiftByteCount;
    private $bitCount;

    private $value;
    private $bitCount2;

    public function __construct(Token $previous = null, $totalBitCount, $value, $bitCount2)
    {
        $this->previous = $previous;
        $this->totalBitCount = $totalBitCount;
		$this->value = $value;
		$this->bitCount2 = $bitCount2;
    }

	private function setType($type)
	{
		$this->type = $type;
	}

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

    public function getTotalBitCount()
    {
        return $this->totalBitCount;
    }

    public function add($value, $bitCount)
    {
        $token = new self($this, $this->totalBitCount + $bitCount, $value, $bitCount);
		$token->setState($this->mode, $this->shiftByteCount, $this->bitCount);
		$token->setType(0);

		return $token;
    }

    public function addBinaryShift($value, $byteCount)
    {
        $bitCount = ($byteCount * 8);
        if ($byteCount <= 31) {
            $bitCount += 10;
        } elseif ($byteCount <= 62) {
            $bitCount += 20;
        } else {
            $bitCount += 21;
        }

		$token = new self($this, $this->totalBitCount + $bitCount, $value, $byteCount);
		$token->setType(1);

        return $token;
    }

	public function getData()
	{
		return [$this->value, $this->bitCount2, $this->type];
	}
}
