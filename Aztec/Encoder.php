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

namespace Aztec;

use Aztec\EncoderDynamic;
use Aztec\EncoderReedSolomon;

class Encoder
{
	private $LAYERS_COMPACT = 5;
	private $LAYERS_FULL = 33;
	private $MATRIX;
	private $compact = true;
	private $wordSize = [
		4,  6,  6,  8,  8,  8,  8,  8,  8, 10, 10,
		10, 10, 10, 10, 10, 10, 10, 10, 10, 10, 10,
		10, 12, 12, 12, 12, 12, 12, 12, 12, 12, 12,
	];
	
	public function EncoderBinary($data)
    {
		$data = array_values(unpack('C*', $data));
		$len = count($data);

		# CODE_UPPER_BS = 31;	
		$bstream = [[31, 5]];

		# Used to split the string in (2048 + 32 - 1) long pieces
		# Barcode can't store that much anyway
		if ($len >= 32) {
			$bstream[] = [0, 5];
			# Used to be $len - 32 but that resulted 
			# in AK at the end of the decoded string
			$bstream[] = [($len - 31), 11];
        } else {
			$bstream[] = [$len, 5];
        }

		foreach($data as $ord){
			$bstream[] = [$ord, 8];
		}

        return $bstream;
    }

	private function mSet($x, $y)
	{
		$this->MATRIX[$x][$y] = 1;
	}

	private function toByte($bstream)
	{
		$data = [];
		foreach($bstream as $d){
			for ($i = $d[1] - 1; $i >= 0; $i--) {
				$data[] = ($d[0] >> $i) & 1;
			}
		}
		return $data;
	}

	public function appendBstream(&$bstream, $data, $bits = 1)
    {
        for ($i = $bits - 1; $i >= 0; $i--) {
            $bstream[] = ($data >> $i) & 1;
        }
    }

	public function encode(string $content, int $eccPercent = 33, $hint = "dynamic")
	{
		switch ($hint) {
			case "dynamic":
				$bstream = (new EncoderDynamic())->encode($content);
				break;
			case "binary":
				$bstream = $this->EncoderBinary($content);
				break;
		}

		$bits = $this->toByte($bstream);
		$bitCount = count($bits);

		$eccBits = intval($bitCount * $eccPercent / 100 + 11);
		$totalSizeBits = $bitCount + $eccBits;

		$layers = 0;
		$wordSize = 0;
		$stuffedBits = null;
		for ($layers = 1; $layers < $this->LAYERS_COMPACT; $layers++) {
			$bitsPerLayer = (88 + 16 * $layers) * $layers;
			if ($bitsPerLayer >= $totalSizeBits) {
				if ($wordSize != $this->wordSize[$layers]) {
					$wordSize = $this->wordSize[$layers];
					$stuffedBits = $this->stuffBits($bits, $wordSize);
				}
				if (count($stuffedBits) + $eccBits <= $bitsPerLayer) {
					break;
				}
			}
		}

		if ($layers == $this->LAYERS_COMPACT) {
			$this->compact = false;
			for ($layers = 1; $layers < $this->LAYERS_FULL; $layers++) {
				$bitsPerLayer = (112 + 16 * $layers) * $layers;
				if ($bitsPerLayer >= $totalSizeBits) {
					if ($wordSize != $this->wordSize[$layers]) {
						$wordSize = $this->wordSize[$layers];
						$stuffedBits = $this->stuffBits($bits, $wordSize);
					}
					if (count($stuffedBits) + $eccBits <= $bitsPerLayer) {
						break;
					}
				}
			}
		}

		if ($layers == $this->LAYERS_FULL) {
			throw new \InvalidArgumentException('Data too large');
		}

		$messageSizeInWords = intval((count($stuffedBits) + $wordSize - 1) / $wordSize);

		// generate check words
		$messageBits = $this->generateCheckWords($stuffedBits, $bitsPerLayer, $wordSize);

		// allocate symbol
		if ($this->compact) {
			$matrixSize = $baseMatrixSize = 11 + $layers * 4;
			$center = intval($matrixSize / 2);
			$alignmentMap = [];
			for ($i = 0; $i < $matrixSize; $i++) {
				$alignmentMap[] = $i;
			}
		} else {
			$baseMatrixSize = 14 + $layers * 4;
			$matrixSize = $baseMatrixSize + 1 + 2 * intval((intval($baseMatrixSize / 2) - 1) / 15);
			$alignmentMap = array_fill(0, $baseMatrixSize, 0);
			$origCenter = intval($baseMatrixSize / 2);
			$center = intval($matrixSize / 2);
			for ($i = 0; $i < $origCenter; $i++) {
				$newOffset = $i + intval($i / 15);
				$alignmentMap[$origCenter - $i - 1] = $center - $newOffset - 1;
				$alignmentMap[$origCenter + $i] = $center + $newOffset + 1;
			}
		}

		$this->MATRIX = [new \SplFixedArray($matrixSize), new \SplFixedArray($matrixSize)];

		// draw mode and data bits
		for ($i = 0, $rowOffset = 0; $i < $layers; $i++) {
			if ($this->compact) {
				$rowSize = ($layers - $i) * 4 + 9;
			} else {
				$rowSize = ($layers - $i) * 4 + 12;
			}
			for ($j = 0; $j < $rowSize; $j++) {
				$columnOffset = $j * 2;
				for ($k = 0; $k < 2; $k++) {
					if ($messageBits[$rowOffset + $columnOffset + $k]) {
						$this->mSet($alignmentMap[$i * 2 + $k], $alignmentMap[$i * 2 + $j]);
					}
					if ($messageBits[$rowOffset + $rowSize * 2 + $columnOffset + $k]) {
						$this->mSet($alignmentMap[$i * 2 + $j], $alignmentMap[$baseMatrixSize - 1 - $i * 2 - $k]);
					}
					if ($messageBits[$rowOffset + $rowSize * 4 + $columnOffset + $k]) {
						$this->mSet($alignmentMap[$baseMatrixSize - 1 - $i * 2 - $k], $alignmentMap[$baseMatrixSize - 1 - $i * 2 - $j]);
					}
					if ($messageBits[$rowOffset + $rowSize * 6 + $columnOffset + $k]) {
						$this->mSet($alignmentMap[$baseMatrixSize - 1 - $i * 2 - $j], $alignmentMap[$i * 2 + $k]);
					}
				}
			}
			$rowOffset += $rowSize * 8;
		}

		$this->drawModeMessage($center, $layers, $messageSizeInWords);

		// draw alignment marks
		if ($this->compact) {
			$this->drawBullsEye($center, 5);
		} else {
			$this->drawBullsEye($center, 7);
			for ($i = 0, $j = 0; $i < intval($baseMatrixSize / 2) - 1; $i += 15, $j += 16) {
				for ($k = $center & 1; $k < $matrixSize; $k += 2) {
					$this->mSet($center - $j, $k);
					$this->mSet($center + $j, $k);
					$this->mSet($k, $center - $j);
					$this->mSet($k, $center + $j);
				}
			}
		}

		return $this->MATRIX;
	}

	private function drawBullsEye($center, $size)
	{
		for ($i = 0; $i < $size; $i += 2) {
			for ($j = $center - $i; $j <= $center + $i; $j++) {
				$this->mSet($j, $center - $i);
				$this->mSet($j, $center + $i);
				$this->mSet($center - $i, $j);
				$this->mSet($center + $i, $j);
			}
		}
		$this->mSet($center - $size, $center - $size);
		$this->mSet($center - $size + 1, $center - $size);
		$this->mSet($center - $size, $center - $size + 1);
		$this->mSet($center + $size, $center - $size);
		$this->mSet($center + $size, $center - $size + 1);
		$this->mSet($center + $size, $center + $size - 1);
	}

	private function drawModeMessage($center, $layers, $messageSizeInWords)
	{
		// generate mode message
		$modeMessage = [];
		if ($this->compact) {
			$this->appendBstream($modeMessage, $layers - 1, 2);
			$this->appendBstream($modeMessage, $messageSizeInWords - 1, 6);
			$modeMessage = $this->generateCheckWords($modeMessage, 28, 4);
		} else {
			$this->appendBstream($modeMessage, $layers - 1, 5);
			$this->appendBstream($modeMessage, $messageSizeInWords - 1, 11);
			$modeMessage = $this->generateCheckWords($modeMessage, 40, 4);
		}

		if ($this->compact) {
			for ($i = 0; $i < 7; $i++) {
				if ($modeMessage[$i]) {
					$this->mSet($center - 3 + $i, $center - 5);
				}
				if ($modeMessage[$i + 7]) {
					$this->mSet($center + 5, $center - 3 + $i);
				}
				if ($modeMessage[20 - $i]) {
					$this->mSet($center - 3 + $i, $center + 5);
				}
				if ($modeMessage[27 - $i]) {
					$this->mSet($center - 5, $center - 3 + $i);
				}
			}
		} else {
			for ($i = 0; $i < 10; $i++) {
				if ($modeMessage[$i]) {
					$this->mSet($center - 5 + $i + intval($i / 5), $center - 7);
				}
				if ($modeMessage[$i + 10]) {
					$this->mSet($center + 7, $center - 5 + $i + intval($i / 5));
				}
				if ($modeMessage[29 - $i]) {
					$this->mSet($center - 5 + $i + intval($i / 5), $center + 7);
				}
				if ($modeMessage[39 - $i]) {
					$this->mSet($center - 7, $center - 5 + $i + intval($i / 5));
				}
			}
		}
	}

	private function generateCheckWords(array $stuffedBits, $totalSymbolBits, $wordSize)
	{
		$messageSizeInWords = intval((count($stuffedBits) + $wordSize - 1) / $wordSize);
		for ($i = $messageSizeInWords * $wordSize - count($stuffedBits); $i > 0; $i--) {
			$stuffedBits[] = 1;
		}
		$totalSizeInFullWords = intval($totalSymbolBits / $wordSize);
		$messageWords = $this->bitsToWords($stuffedBits, $wordSize, $totalSizeInFullWords);

		$rs = new EncoderReedSolomon($wordSize);
		$messageWords = $rs->encodePadded($messageWords, $totalSizeInFullWords - $messageSizeInWords);

		$startPad = $totalSymbolBits % $wordSize;
		$messageBits = [[0, $startPad]];

		foreach ($messageWords as $messageWord) {
			$messageBits[] = [$messageWord, $wordSize];
		}

		return $this->toByte($messageBits);
	}

	private function bitsToWords(array $stuffedBits, $wordSize, $totalWords)
	{
		$message = array_fill(0, $totalWords, 0);
		$n = intval(count($stuffedBits) / $wordSize);
		for ($i = 0; $i < $n; $i++) {
			$value = 0;
			for ($j = 0; $j < $wordSize; $j++) {
				$value |= $stuffedBits[$i * $wordSize + $j] ? (1 << $wordSize - $j - 1) : 0;
			}
			$message[$i] = $value;
		}

		return $message;
	}

	private function stuffBits($bits, $wordSize)
	{
		$out = [];

		$n = count($bits);
		$mask = (1 << $wordSize) - 2;
		for ($i = 0; $i < $n; $i += $wordSize) {
			$word = 0;
			for ($j = 0; $j < $wordSize; $j++) {
				if ($i + $j >= $n || $bits[$i + $j]) {
					$word |= 1 << ($wordSize - 1 - $j);
				}
			}
			if (($word & $mask) == $mask) {
				$out[] = [$word & $mask, $wordSize];
				$i--;
			} elseif (($word & $mask) == 0) {
				$out[] = [$word | 1, $wordSize];
				$i--;
			} else {
				$out[] = [$word, $wordSize];
			}
		}

		$out = $this->toByte($out);

		$n = count($out);
		$remainder = $n % $wordSize;

		if ($remainder != 0) {
			$j = 1;
			for ($i = 0; $i < $remainder; $i++) {
				if (!$out[$n - 1 - $i]) {
					$j = 0;
				}
			}
			for ($i = $remainder; $i < $wordSize - 1; $i++) {
				$out[] = 1;
			}
			$out[] = ($j ^ 1);
		}

		return $out;
	}
}
