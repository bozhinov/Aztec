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

class DynamicDataEncoder
{
	private $charMap;
	private $shiftTable;
    private $latchTable;

	private $MODE_UPPER = 0;
    private $MODE_LOWER = 1;
    private $MODE_DIGIT = 2;
    private $MODE_MIXED = 3;
    private $MODE_PUNCT = 4;

	function __construct()
	{
		$this->charMap = $this->genCharMapping();
		$this->shiftTable = $this->genShiftTable();
		$this->latchTable = [
				[0,327708,327710,327709,656318],
				[590318,0,327710,327709,656318],
				[262158,590300,0,590301,932798],
				[327709,327708,656318,0,327710],
				[327711,656380,656382,656381,0]
			];
	}

	private function genShiftTable()
	{
		$shiftTable = [];
		for ($i = 0; $i < 6; $i++) {
			$shiftTable[] = array_fill(0, 6, -1);
		}
		$shiftTable[0][4] = 0;
		$shiftTable[1][4] = 0;
		$shiftTable[1][0] = 28;
		$shiftTable[3][4] = 0;
		$shiftTable[2][4] = 0;
		$shiftTable[2][0] = 15;

		return $shiftTable;
	}

    private function getLatch($fromMode, $toMode)
    {
        return $this->latchTable[$fromMode][$toMode];
    }

    private function getShift($fromMode, $toMode)
    {
        return $this->shiftTable[$fromMode][$toMode];
    }

	private function genCharMapping()
	{
		$charMap = [];
		for ($i = 0; $i < 5; $i++) {
			$charMap[] = array_fill(0, 256, 0);
		}

		# ord(' ') = 32
		# ord('A') = 65
		# ord('Z') = 90
		# ord('a') = 97
		# ord('z') = 122
		# ord('0') = 48
		# ord('9') = 57
		# ord(',') = 44
		# ord('.') = 46

		$charMap[0][32] = 1;
		for ($c = 65; $c <= 90; $c++) {
			$charMap[0][$c] = ($c - 65 + 2);
		}

		$charMap[1][32] = 1;
		for ($c = 97; $c <= 122; $c++) {
			$charMap[1][$c] = ($c - 97 + 2);
		}

		$charMap[2][32] = 1;
		for ($c = 48; $c <= 57; $c++) {
			$charMap[2][$c] = ($c - 48 + 2);
		}
		$charMap[2][44] = 12;
		$charMap[2][46] = 13;

		//	'\0', ' ', '\1', '\2', '\3', '\4', '\5', '\6', '\7', '\b', '\t', '\n',
		//	'\13', '\f', '\r', '\33', '\34', '\35', '\36', '\37', '@', '\\', '^',
		//	'_', '`', '|', '~', '\177',
		$mixedTable = [32 => 1,	64 => 20, 92 => 27,	94 => 22, 95 => 23, 96 => 24, 124 => 25, 126 => 26];

		foreach($mixedTable as $i => $val)
		{
			$charMap[3][$val] = $i;
		}

		// '\0', '\r', '\0', '\0', '\0', '\0', '!', '\'', '#', '$', '%', '&', '\'',
		// '(', ')', '*', '+', ',', '-', '.', '/', ':', ';', '<', '=', '>', '?',
		// '[', ']', '{', '}',
		$punctTable = [
			33 => 6, 35 => 8, 36 => 9, 37 => 10,
			38 => 11, 39 => 12,	40 => 13, 41 => 14,
			42 => 15, 43 => 16,	44 => 17, 45 => 18,
			46 => 19, 47 => 20,	58 => 21, 59 => 22,
			60 => 23, 61 => 24,	62 => 25, 63 => 26,
			91 => 27, 92 => 5, 93 => 28, 123 => 29,
			125 => 30
		];

		foreach($punctTable as $i => $val){
			$charMap[4][$i] = $val;
		}

		return $charMap;
	}

	private function getCharMapping($char, $mode)
    {
        return $this->charMap[$mode][ord($char)];
    }

    public function encode($data)
    {
        $text = str_split($data);
		$textCount = count($text);
		$token = new SimpleToken(null, 0, 0, 0);
		$token->setState(0,0,0);
        $states = [$token];
        for ($index = 0; $index < $textCount; $index++) {
            $nextChar = (($index + 1 != $textCount) ? $text[$index + 1] : '');
            switch ($text[$index]) {
                case '\r':
                    $pairCode = (($nextChar == '\n') ? 2 : 0);
                    break;
                case '.':
                    $pairCode = (($nextChar == ' ') ? 3 : 0);
                    break;
                case ',':
                    $pairCode = (($nextChar == ' ') ? 4 : 0);
                    break;
                case ':':
                    $pairCode = (($nextChar == ' ') ? 5 : 0);
                    break;
                default:
                    $pairCode = 0;
                    break;
            }
            if ($pairCode > 0) {
                $states = $this->updateStateListForPair($states, $index, $pairCode);
                $index++;
            } else {
                $states = $this->updateStateListForChar($states, $index, $text);
            }
        }

        $minState = $states[0];
        foreach ($states as $state) {
            if ($state->getBitCount() < $minState->getBitCount()) {
                $minState = $state;
            }
        }

        return $this->toBitArray($minState, $text);
    }

    private function updateStateListForChar(array $states, $index, $text)
    {
        $result = [];
		$ch = $text[$index];

        foreach ($states as $state) {
			$charInCurrentTable = ($this->getCharMapping($ch, $state->getMode()) > 0);
			$stateNoBinary = null;
			for ($mode = 0; $mode <= 4; $mode++) {
				$charInMode = $this->getCharMapping($ch, $mode);
				if ($charInMode > 0) {
					if ($stateNoBinary === null) {
						$stateNoBinary = $this->endBinaryShift($state, $index);
					}
					if (!$charInCurrentTable || $mode == $state->getMode() || $mode == 2) {
						$result[] = $this->latchAndAppend($stateNoBinary, $mode, $charInMode);
					}
					if (!$charInCurrentTable && $this->getShift($state->getMode(), $mode) >= 0) {
						$result[] = $this->shiftAndAppend($stateNoBinary, $mode, $charInMode);
					}
				}
			}
			if ($state->getBinaryShiftByteCount() > 0 || $this->getCharMapping($ch, $state->getMode()) == 0) {
				$result[] = $this->addBinaryShiftChar($state, $index);
			}
        }

        return $this->simplifyStates($result);
    }

    private function updateStateListForPair(array $states, $index, $pairCode)
    {
        $result = [];
        foreach ($states as $state) {
			$stateNoBinary = $this->endBinaryShift($state, $index);

			$result[] = $this->latchAndAppend($stateNoBinary, 4, $pairCode);
			if ($state->getMode() != 4) {
				$result[] = $this->shiftAndAppend($stateNoBinary, 4, $pairCode);
			}
			if ($pairCode == 3 || $pairCode == 4) {
				$interm = $this->latchAndAppend($stateNoBinary, 2, 16 - $pairCode);
				$result[] = $this->latchAndAppend($interm, 2, 1);
			}
			if ($state->getBinaryShiftByteCount() > 0) {
				$interm = $this->addBinaryShiftChar($state, $index);
				$result[] = $this->addBinaryShiftChar($interm + 1);
			}
        }

        return $this->simplifyStates($result);
    }

    private function simplifyStates(array $states)
    {
        $result = [];
        foreach ($states as $newState) {
            $add = true;
            for ($i = 0; $i < count($result); $i++) {
                if ($this->isBetterThanOrEqualTo($result[$i], $newState)) {
                    $add = false;
                    break;
                }
                if ($this->isBetterThanOrEqualTo($newState, $result[$i])) {
                    unset($result[$i]);
                    $result = array_values($result);
                    $i--;
                }
            }
            if ($add) {
                $result[] = $newState;
            }
        }

        return $result;
    }

    private function isBetterThanOrEqualTo($one, $other)
    {
        $mySize = $one->getBitCount() + ($this->getLatch($one->getMode(), $other->getMode()) >> 16);
        if ($other->getBinaryShiftByteCount() > 0 && ($one->getBinaryShiftByteCount() == 0 || $one->getBinaryShiftByteCount() > $other->getBinaryShiftByteCount())) {
            $mySize += 10;
        }

        return $mySize <= $other->getBitCount();
    }

    private function shiftAndAppend($token, $mode, $value)
    {
		$current_mode = $token->getMode();
        $thisModeBitCount = ($current_mode == $this->MODE_DIGIT ? 4 : 5);
        $token = $token->add($this->getShift($current_mode, $mode), $thisModeBitCount);
        $token = $token->add($value, 5);
		$bitCount = $token->getBitCount();
        $token->setState($current_mode, 0, $bitCount + $thisModeBitCount + 5);

		return $token;
    }

	private function latchAndAppend($token, $mode, $value)
    {
        $bitCount = $token->getBitCount();
		$current_mode = $token->getMode();

        if ($mode != $current_mode) {
            $latch = $this->getLatch($current_mode, $mode);
            $token = $token->add(($latch & 0xFFFF), ($latch >> 16));
            $bitCount += ($latch >> 16);
        }
        $latchModeBitCount = ($mode == $this->MODE_DIGIT ? 4 : 5);
        $token = $token->add($value, $latchModeBitCount);

        $token->setState($mode, 0, $bitCount + $latchModeBitCount);
		return $token;
    }

	private function addBinaryShiftChar($token, $index)
    {
        $current_mode = $token->getMode();
        $bitCount = $token->getBitCount();

        if ($current_mode == $this->MODE_PUNCT || $current_mode == $this->MODE_DIGIT) {
            $latch = $this->getLatch($current_mode, $this->MODE_UPPER);
            $token = $token->add(($latch & 0xFFFF), ($latch >> 16));
            $bitCount += ($latch >> 16);
            $current_mode = $this->MODE_UPPER;
        }

		$shiftByteCount = $token->getBinaryShiftByteCount();
        if ($shiftByteCount == 0 || $shiftByteCount == 31) {
            $deltaBitCount = 18;
        } elseif ($shiftByteCount == 62) {
            $deltaBitCount = 9;
        } else {
            $deltaBitCount = 8;
        }
        $token->setState($current_mode, $shiftByteCount + 1, $bitCount + $deltaBitCount);
        if ($shiftByteCount + 1 == (2047 + 31)) {
            $token = $this->endBinaryShift($token, $index + 1);
        }

        return $token;
    }

    private function endBinaryShift($token, $index)
    {
		$shiftByteCount = $token->getBinaryShiftByteCount();
        if ($shiftByteCount == 0) {
            return $token;
        }

		$mode = $token->getMode();
		$bitCount = $token->getBitCount();
		
        $token = $token->addBinaryShift($index - $shiftByteCount, $shiftByteCount);

        $token->setState($mode, 0, $bitCount);

		return $token;
    }

    private function toBitArray($token, array $text)
    {
        $symbols = [];
        $token = $this->endBinaryShift($token, count($text));
        while ($token !== null) {
            array_unshift($symbols, $token);
            $token = $token->getPrevious();
        }

        $bitArray = new BitArray();
        foreach ($symbols as $symbol) {
            $bitArray = $symbol->appendTo($bitArray, $text);
        }

        return $bitArray;
    }

}
