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

namespace Aztec\ReedSolomon;

use Aztec\ReedSolomon\GenericGF;

class ReedSolomonEncoder
{
    private $field;
    private $cachedGenerators;

    public function __construct($wordSize)
    {
        $this->field = $this->getGF($wordSize);
        $this->cachedGenerators = [];
        $this->cachedGenerators[] = new GenericGFPoly($this->field, [1]);
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

    private function buildGenerator($degree)
    {
        if ($degree >= count($this->cachedGenerators)) {
            $lastGenerator = end($this->cachedGenerators);
            for ($d = count($this->cachedGenerators); $d <= $degree; $d++) {
                $nextCoefficent = $this->field->exp($d - 1 + $this->field->getGeneratorBase());
                $nextGenerator = $lastGenerator->multiply(new GenericGFPoly($this->field, [1, $nextCoefficent]));
                $this->cachedGenerators[] = $nextGenerator;
                $lastGenerator = $nextGenerator;
            }
        }

        return $this->cachedGenerators[$degree];
    }

    public function encode(array $data, $ecBytes)
    {
        if ($ecBytes == 0) {
            throw new \InvalidArgumentException('No error correction bytes');
        }
        if (count($data) == 0) {
            throw new \InvalidArgumentException('No data bytes provided');
        }

        $generator = $this->buildGenerator($ecBytes);
        $info = new GenericGFPoly($this->field, $data);
        $info = $info->multiplyByMonomial($ecBytes, 1);

        $remainder = $info->divide($generator);
        $coefficients = $remainder->getCoefficients();
        $paddedCoefficients = array_pad($coefficients, -$ecBytes, 0);

        return array_merge($data, $paddedCoefficients);
    }

    public function encodePadded(array $paddedData, $ecBytes)
    {
        $dataLength = count($paddedData) - $ecBytes;

        return $this->encode(array_splice($paddedData, 0, $dataLength), $ecBytes);
    }
}
