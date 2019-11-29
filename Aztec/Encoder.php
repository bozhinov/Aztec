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

use Aztec\Encoder\DynamicDataEncoder;
use Aztec\Encoder\BinaryDataEncoder;
use Aztec\Encoder\StringDataEncoder;
use Aztec\ReedSolomon\GenericGF;
use Aztec\ReedSolomon\ReedSolomonEncoder;
use Aztec\BitArray;

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

	private function mSet($x, $y)
	{
		$this->MATRIX[$x][$y] = 1;
	}

	private function toByte($bstream)
	{
		$dataStr = "";
		foreach($bstream as $d){
			$dataStr .= str_pad(decbin($d[0]), $d[1], "0", STR_PAD_LEFT);
		}

		return array_map('intval', str_split($dataStr));
	}

	public function encode(string $content, int $eccPercent = 33, $hint = "dynamic")
	{
		switch ($hint) {
			case "dynamic":
				$dataEncoder = new DynamicDataEncoder();
				break;
			case "binary":
				$dataEncoder = new BinaryDataEncoder();
				break;
		}

		$bstream = $dataEncoder->encode($content);
		$bits = $this->toByte($bstream);
		$bitCount = count($bits);

		$eccBits = intval($bitCount * $eccPercent / 100 + 11);
		$totalSizeBits = $bitCount + $eccBits;

		$layers = 0;
		$wordSize = 0;
		$totalSymbolBits = 0;
		$stuffedBits = null;
		for ($layers = 1; $layers < $this->LAYERS_COMPACT; $layers++) {
			if ($this->getBitsPerLayer($layers, false) >= $totalSizeBits) {
				if ($wordSize != $this->wordSize[$layers]) {
					$wordSize = $this->wordSize[$layers];
					$stuffedBits = $this->stuffBits($bits, $wordSize);
				}

				$totalSymbolBits = $this->getBitsPerLayer($layers, false);
				if ($stuffedBits->getLength() + $eccBits <= $totalSymbolBits) {
					break;
				}
			}
		}

		if ($layers == $this->LAYERS_COMPACT) {
			$this->compact = false;
			for ($layers = 1; $layers < $this->LAYERS_FULL; $layers++) {
				if ($this->getBitsPerLayer($layers, true) >= $totalSizeBits) {
					if ($wordSize != $this->wordSize[$layers]) {
						$wordSize = $this->wordSize[$layers];
						$stuffedBits = $this->stuffBits($bits, $wordSize);
					}
					$totalSymbolBits = $this->getBitsPerLayer($layers, true);
					if ($stuffedBits->getLength() + $eccBits <= $totalSymbolBits) {
						break;
					}
				}
			}
		}

		if ($layers == $this->LAYERS_FULL) {
			throw new \InvalidArgumentException('Data too large');
		}

		$messageSizeInWords = intval(($stuffedBits->getLength() + $wordSize - 1) / $wordSize);

		// generate check words
		$messageBits = $this->generateCheckWords($stuffedBits, $totalSymbolBits, $wordSize);

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
					if ($messageBits->get($rowOffset + $columnOffset + $k)) {
						$this->mSet($alignmentMap[$i * 2 + $k], $alignmentMap[$i * 2 + $j]);
					}
					if ($messageBits->get($rowOffset + $rowSize * 2 + $columnOffset + $k)) {
						$this->mSet($alignmentMap[$i * 2 + $j], $alignmentMap[$baseMatrixSize - 1 - $i * 2 - $k]);
					}
					if ($messageBits->get($rowOffset + $rowSize * 4 + $columnOffset + $k)) {
						$this->mSet($alignmentMap[$baseMatrixSize - 1 - $i * 2 - $k], $alignmentMap[$baseMatrixSize - 1 - $i * 2 - $j]);
					}
					if ($messageBits->get($rowOffset + $rowSize * 6 + $columnOffset + $k)) {
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
		$modeMessage = new BitArray();
		if ($this->compact) {
			$modeMessage->append($layers - 1, 2);
			$modeMessage->append($messageSizeInWords - 1, 6);
			$modeMessage = $this->generateCheckWords($modeMessage, 28, 4);
		} else {
			$modeMessage->append($layers - 1, 5);
			$modeMessage->append($messageSizeInWords - 1, 11);
			$modeMessage = $this->generateCheckWords($modeMessage, 40, 4);
		}

		if ($this->compact) {
			for ($i = 0; $i < 7; $i++) {
				if ($modeMessage->get($i)) {
					$this->mSet($center - 3 + $i, $center - 5);
				}
				if ($modeMessage->get($i + 7)) {
					$this->mSet($center + 5, $center - 3 + $i);
				}
				if ($modeMessage->get(20 - $i)) {
					$this->mSet($center - 3 + $i, $center + 5);
				}
				if ($modeMessage->get(27 - $i)) {
					$this->mSet($center - 5, $center - 3 + $i);
				}
			}
		} else {
			for ($i = 0; $i < 10; $i++) {
				if ($modeMessage->get($i)) {
					$this->mSet($center - 5 + $i + intval($i / 5), $center - 7);
				}
				if ($modeMessage->get($i + 10)) {
					$this->mSet($center + 7, $center - 5 + $i + intval($i / 5));
				}
				if ($modeMessage->get(29 - $i)) {
					$this->mSet($center - 5 + $i + intval($i / 5), $center + 7);
				}
				if ($modeMessage->get(39 - $i)) {
					$this->mSet($center - 7, $center - 5 + $i + intval($i / 5));
				}
			}
		}
	}

	private function generateCheckWords(BitArray $stuffedBits, $totalSymbolBits, $wordSize)
	{
		$messageSizeInWords = intval(($stuffedBits->getLength() + $wordSize - 1) / $wordSize);
		for ($i = $messageSizeInWords * $wordSize - $stuffedBits->getLength(); $i > 0; $i--) {
			$stuffedBits->append(1);
		}
		$totalSizeInFullWords = intval($totalSymbolBits / $wordSize);
		$messageWords = $this->bitsToWords($stuffedBits, $wordSize, $totalSizeInFullWords);

		$rs = new ReedSolomonEncoder($this->getGF($wordSize));
		$messageWords = $rs->encodePadded($messageWords, $totalSizeInFullWords - $messageSizeInWords);

		$startPad = $totalSymbolBits % $wordSize;
		$messageBits = new BitArray();
		$messageBits->append(0, $startPad);

		foreach ($messageWords as $messageWord) {
			$messageBits->append($messageWord, $wordSize);
		}

		return $messageBits;
	}

	private function getBitsPerLayer($layer, $full = true)
	{
		if ($full) {
			return (112 + 16 * $layer) * $layer;
		} else {
			return (88 + 16 * $layer) * $layer;
		}
	}

	private function bitsToWords(BitArray $stuffedBits, $wordSize, $totalWords)
	{
		$message = array_fill(0, $totalWords, 0);
		$n = intval($stuffedBits->getLength() / $wordSize);
		for ($i = 0; $i < $n; $i++) {
			$value = 0;
			for ($j = 0; $j < $wordSize; $j++) {
				$value |= $stuffedBits->get($i * $wordSize + $j) ? (1 << $wordSize - $j - 1) : 0;
			}
			$message[$i] = $value;
		}

		return $message;
	}

	private function getGF($wordSize)
	{
		switch ($wordSize) {
			case 4:
				return GenericGF::getInstance(GenericGF::AZTEC_PARAM);
			case 6:
				return GenericGF::getInstance(GenericGF::AZTEC_DATA_6);
			case 8:
				return GenericGF::getInstance(GenericGF::AZTEC_DATA_8);
			case 10:
				return GenericGF::getInstance(GenericGF::AZTEC_DATA_10);
			case 12:
				return GenericGF::getInstance(GenericGF::AZTEC_DATA_12);
			default:
				return null;
		}
	}

	private function stuffBits($bits, $wordSize)
	{
		$out = new BitArray();

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
				$out->append($word & $mask, $wordSize);
				$i--;
			} elseif (($word & $mask) == 0) {
				$out->append($word | 1, $wordSize);
				$i--;
			} else {
				$out->append($word, $wordSize);
			}
		}

		$n = $out->getLength();
		$remainder = $n % $wordSize;
		if ($remainder != 0) {
			$j = 1;
			for ($i = 0; $i < $remainder; $i++) {
				if (!$out->get($n - 1 - $i)) {
					$j = 0;
				}
			}
			for ($i = $remainder; $i < $wordSize - 1; $i++) {
				$out->append(1);
			}
			$out->append((($j == 0) ? 1 : 0));
		}

		return $out;
	}
}
