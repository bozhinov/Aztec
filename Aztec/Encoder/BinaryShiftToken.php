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

use Aztec\BitArray;

class BinaryShiftToken extends Token
{
    private $shiftStart;
    private $shiftByteCount;

    public function __construct(Token $previous = null, $totalBitCount, $shiftStart, $shiftByteCount)
    {
        parent::__construct($previous, $totalBitCount);
        $this->shiftStart = $shiftStart;
        $this->shiftByteCount = $shiftByteCount;
    }

    public function appendTo(BitArray &$bitArray, array $text_e)
    {
        for ($i = 0; $i < $this->shiftByteCount; $i++) {
            if ($i == 0 || ($i == 31 && $this->shiftByteCount <= 62)) {
                $bitArray->append(31, 5);
                if ($this->shiftByteCount > 62) {
                    $bitArray->append($this->shiftByteCount - 31, 16);
                } elseif ($i == 0) {
                    $bitArray->append(min($this->shiftByteCount, 31), 5);
                } else {
                    $bitArray->append($this->shiftByteCount - 31, 5);
                }
            }
            $bitArray->append($text_e[$this->shiftStart + $i], 8);
        }
    }
}
