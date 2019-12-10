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

use \Aztec\azException;

class ReedSolomon
{
	private $expTable;
	private $logTable;
	private $size;

	public function __construct($wordSize)
	{
		$this->size = pow(2, $wordSize);
		$primitive = $this->getGF($wordSize);
		$this->initialize($primitive);
	}

	private function initialize($primitive)
	{
		$this->expTable = array_fill(0, $this->size, 0);
		$this->logTable = $this->expTable;
		$x = 1;
		for ($i = 0; $i < $this->size; $i++) {
			$this->expTable[$i] = $x;
			$x <<= 1;
			if ($x >= $this->size) {
				$x ^= $primitive;
				$x &= ($this->size - 1);
			}
		}
		for ($i = 0; $i < $this->size; $i++) {
			$this->logTable[$this->expTable[$i]] = $i;
		}
	}

	private function field_multiply($a, $b)
	{
		if ($a == 0 || $b == 0) {
			return 0;
		}

		return $this->expTable[($this->logTable[$a] + $this->logTable[$b]) % ($this->size - 1)];
	}

	private function getGF($wordSize)
	{
		switch ($wordSize) {
			case 4:
				$primitive = 19;
				break;
			case 6:
				$primitive = 67;
				break;
			case 8:
				$primitive = 301;
				break;
			case 10:
				$primitive = 1033;
				break;
			case 12:
				$primitive = 4201;
				break;
			default:
				throw azException::InvalidInput("Word size of $wordSize was unexpected");
		}

		return $primitive;
	}

	private function getPoly(array $coefficients)
	{
		while (!empty($coefficients) && $coefficients[0] == 0) {
			array_shift($coefficients);
		}

		return $coefficients;
	}

	private function buildGenerator($ecBytes)
	{
		$lastGenerator = [1];
		$Generators = [[$lastGenerator]];
		for ($d = count($Generators); $d <= $ecBytes; $d++) {
			$nextCoefficent = $this->expTable[$d];
			$lastGenerator = $this->multiply([1, $nextCoefficent], $lastGenerator);
			$Generators[] = $lastGenerator;
		}

		return [$d, $Generators[$ecBytes]];	
	}

	private function multiply(array $bCoefficients, array $aCoefficients)
	{
		# Coefficients are prepended 1 so can't be 0
		$aLength = count($aCoefficients);
		$bLength = count($bCoefficients);
		$product = array_fill(0, ($aLength + $bLength - 1), 0);

		for ($i = 0; $i < $aLength; $i++) {
			$aCoeff = $aCoefficients[$i];
			for ($j = 0; $j < $bLength; $j++) {
				$product[$i + $j] ^= ($this->field_multiply($aCoeff, $bCoefficients[$j]));
			}
		}

		if ($this->isZero($product)) {
			throw azException::InvalidInput('Divide by 0');
		}

		return $this->getPoly($product);
	}

	private function isZero($coefficients)
	{
		return $coefficients[0] == 0;
	}

	private function addOrSubtract(array $largerCoefficients, array $smallerCoefficients)
	{
		if ($this->isZero($smallerCoefficients)) {
			return $largerCoefficients;
		}
		if ($this->isZero($largerCoefficients)) {
			return $smallerCoefficients;
		}

		if (count($smallerCoefficients) > count($largerCoefficients)) {
			list($smallerCoefficients, $largerCoefficients) = [$largerCoefficients, $smallerCoefficients];
		}

		$lengthDiff = count($largerCoefficients) - count($smallerCoefficients);
		$sumDiff = array_slice($largerCoefficients, 0, $lengthDiff);

		for ($i = $lengthDiff; $i < count($largerCoefficients); $i++) {
			$sumDiff[$i] = $smallerCoefficients[$i - $lengthDiff] ^ $largerCoefficients[$i];
		}

		return $this->getPoly($sumDiff);
	}

	private function multiplyByMonomial($degree, $coefficient, $coefficients)
	{
		if ($coefficient == 0) {
			return [0];
		}

		$count = count($coefficients);
		$product = array_fill(0, ($count + $degree), 0);

		for ($i = 0; $i < $count; $i++) {
			$product[$i] = $this->field_multiply($coefficients[$i], $coefficient);
		}

		return $this->getPoly($product);
	}

	private function divide($ecBytes, $data)
	{
		list($otherDegree, $otherCoefficient) = $this->buildGenerator($ecBytes);

		$one = $this->multiplyByMonomial($ecBytes, 1, $data);

		while (count($one) >= $otherDegree && !$this->isZero($one)) {
			$degreeDifference = count($one) - $otherDegree;
			$scale = $this->field_multiply($one[0], 1);
			$largerCoefficients = $this->multiplyByMonomial($degreeDifference, $scale, $otherCoefficient);

			$one = $this->addOrSubtract($largerCoefficients, $one);
		}

		return $one;
	}

	public function encodePadded(array $paddedData, $ecBytes)
	{
		$dataLength = count($paddedData) - $ecBytes;

		if ($ecBytes == 0) {
			throw azException::InvalidInput('No error correction bytes');
		}
		if ($dataLength == 0) {
			throw azException::InvalidInput('No data bytes provided');
		}

		$data = array_splice($paddedData, 0, $dataLength);
		$coefficients = $this->divide($ecBytes, $data);
		$paddedCoefficients = array_pad($coefficients, -$ecBytes, 0);

		return array_merge($data, $paddedCoefficients);
	}
}
